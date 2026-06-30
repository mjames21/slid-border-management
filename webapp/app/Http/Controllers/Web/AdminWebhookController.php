<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOutboundWebhookRequest;
use App\Jobs\DeliverSubmissionWebhook;
use App\Models\Country;
use App\Models\DynamicForm;
use App\Models\OutboundWebhook;
use App\Models\WebhookDelivery;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminWebhookController extends Controller
{
    public function index(): View
    {
        $webhooks = OutboundWebhook::query()
            ->with('country')
            ->withCount([
                'deliveries',
                'deliveries as succeeded_deliveries_count' => fn ($query) => $query->where('status', WebhookDelivery::STATUS_SUCCEEDED),
                'deliveries as failed_deliveries_count' => fn ($query) => $query->where('status', WebhookDelivery::STATUS_FAILED),
                'deliveries as pending_deliveries_count' => fn ($query) => $query->where('status', WebhookDelivery::STATUS_PENDING),
            ])
            ->latest()
            ->paginate(15);

        $recentDeliveries = WebhookDelivery::query()
            ->with(['outboundWebhook.country', 'mobileSubmission'])
            ->latest('updated_at')
            ->limit(12)
            ->get();

        return view('admin.webhooks.index', [
            'webhooks' => $webhooks,
            'recentDeliveries' => $recentDeliveries,
            'countries' => Country::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'moduleLabels' => DynamicForm::moduleLabels(),
        ]);
    }

    public function store(StoreOutboundWebhookRequest $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validated();

        $webhook = OutboundWebhook::query()->create([
            'country_code' => strtoupper($validated['country_code']),
            'name' => $validated['name'],
            'endpoint_url' => trim($validated['endpoint_url']),
            'signing_secret' => $validated['signing_secret'] ?: Str::random(48),
            'reporting_module' => $validated['reporting_module'] ?: null,
            'form_id' => $validated['form_id'] ?: null,
            'is_active' => (bool) ($validated['is_active'] ?? false),
            'timeout_seconds' => (int) $validated['timeout_seconds'],
            'created_by' => $request->user()?->id,
        ]);

        $audit->record('admin.rest_service_created', $request->user(), null, $webhook, [
            'country_code' => $webhook->country_code,
            'endpoint_host' => parse_url($webhook->endpoint_url, PHP_URL_HOST),
            'scope' => [
                'reporting_module' => $webhook->reporting_module,
                'form_id' => $webhook->form_id,
            ],
        ], $request);

        return redirect()->route('admin.webhooks.index')->with('status', 'REST service created. New accepted submissions matching this scope will be pushed as signed JSON.');
    }

    public function toggle(Request $request, OutboundWebhook $webhook, AuditLogger $audit): RedirectResponse
    {
        $webhook->forceFill(['is_active' => ! $webhook->is_active])->save();

        $audit->record('admin.rest_service_toggled', $request->user(), null, $webhook, [
            'is_active' => $webhook->is_active,
        ], $request);

        return redirect()->route('admin.webhooks.index')->with('status', $webhook->is_active ? 'REST service activated.' : 'REST service paused.');
    }

    public function retry(Request $request, WebhookDelivery $delivery, AuditLogger $audit): RedirectResponse
    {
        $delivery->forceFill([
            'status' => WebhookDelivery::STATUS_PENDING,
            'error_message' => null,
            'delivered_at' => null,
        ])->save();

        DeliverSubmissionWebhook::dispatch($delivery)->afterResponse();

        $audit->record('admin.rest_service_delivery_retried', $request->user(), null, $delivery, [
            'delivery_id' => $delivery->id,
            'webhook_id' => $delivery->outbound_webhook_id,
        ], $request);

        return redirect()->route('admin.webhooks.index')->with('status', 'Delivery retry queued.');
    }
}

<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\DynamicForm;
use App\Models\MobileSubmission;
use App\Services\MobileSubmissionValidator;
use App\Services\SubmissionWebhookDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class WebFormController extends Controller
{
    public function show(Request $request, DynamicForm $form): View
    {
        $this->authorizeWebCollection($request, $form);

        $version = $form->publishedVersion;
        abort_if(! $version, 404);

        return view('collect.form', [
            'form' => $form,
            'version' => $version,
            'schema' => $version->compiled_schema,
        ]);
    }

    public function store(
        Request $request,
        DynamicForm $form,
        MobileSubmissionValidator $validator,
        SubmissionWebhookDispatcher $webhooks
    ): RedirectResponse {
        $this->authorizeWebCollection($request, $form);

        $version = $form->publishedVersion;
        abort_if(! $version, 404);

        $answers = $this->answersFromRequest($request, $version->compiled_schema);
        $errors = $validator->validate($version, $answers);

        if ($errors) {
            return back()
                ->withInput()
                ->withErrors(['answers' => implode(' ', $errors)]);
        }

        $user = $request->user()->load('borderPost');
        $now = now();
        $localId = 'web-'.(string) Str::uuid();
        $hasLocation = filled($request->input('device_latitude')) && filled($request->input('device_longitude'));

        $submission = MobileSubmission::query()->create([
            'server_uid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'mobile_device_id' => null,
            'border_post_id' => $user->border_post_id,
            'country_code' => $user->operationalCountryCode(),
            'border_post_code' => $user->borderPost?->code,
            'border_post_digital_address' => $user->borderPost?->digital_address,
            'region' => $user->borderPost?->region,
            'reporting_module' => DynamicForm::normalizeModule($form->reporting_module),
            'device_latitude' => $hasLocation ? $request->input('device_latitude') : null,
            'device_longitude' => $hasLocation ? $request->input('device_longitude') : null,
            'device_location_accuracy_meters' => $hasLocation ? $request->input('device_location_accuracy_meters') : null,
            'device_location_captured_at' => $hasLocation ? $now : null,
            'device_id' => 'web-'.$user->id,
            'local_id' => $localId,
            'form_id' => $form->form_id,
            'form_version' => $version->version,
            'answers' => $answers,
            'client_created_at' => $now,
            'client_updated_at' => $now,
            'client_synced_at' => $now,
            'received_at' => $now,
            'status' => 'accepted',
            'rejection_reason' => null,
            'source_ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $webhooks->queueFor($submission);

        return redirect()
            ->route('collect.forms.show', $form)
            ->with('status', 'Submission saved and synced as receipt '.$submission->server_uid.'.');
    }

    private function authorizeWebCollection(Request $request, DynamicForm $form): void
    {
        $user = $request->user();

        abort_if(! $user || ! $user->is_active, 403);
        abort_if($form->is_template || ! $form->published_version_id, 404);
        abort_if($form->country_code !== $user->operationalCountryCode(), 403);
    }

    private function answersFromRequest(Request $request, array $schema): array
    {
        $submitted = $request->input('answers', []);
        $submitted = is_array($submitted) ? $submitted : [];
        $answers = [];

        foreach ($schema['fields'] ?? [] as $field) {
            $id = $field['id'] ?? null;
            $type = $field['type'] ?? 'text';

            if (! $id || in_array($type, ['calculate', 'note'], true)) {
                continue;
            }

            $value = array_key_exists($id, $submitted) ? $submitted[$id] : null;

            if ($type === 'select_multiple') {
                $answers[$id] = collect(is_array($value) ? $value : [$value])
                    ->filter(static fn ($item) => filled($item))
                    ->values()
                    ->all();

                continue;
            }

            $answers[$id] = is_array($value) ? null : $value;
        }

        return $answers;
    }
}

<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeploymentRequest;
use App\Models\DeploymentRequest;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;

class DeploymentRequestController extends Controller
{
    public function store(StoreDeploymentRequest $request, AuditLogger $audit): RedirectResponse
    {
        $redirectTo = $this->redirectTarget($request);

        if ($request->filled('website')) {
            return redirect($redirectTo)->with('status', 'Thanks. We received your deployment request.');
        }

        $validated = $request->validated();
        unset($validated['website']);

        $deploymentRequest = DeploymentRequest::query()->create([
            ...$validated,
            'status' => DeploymentRequest::STATUS_NEW,
            'source_ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
        ]);

        $audit->record('public.deployment_request_created', auditable: $deploymentRequest, metadata: [
            'country_name' => $deploymentRequest->country_name,
            'agency_name' => $deploymentRequest->agency_name,
            'deployment_plan' => $deploymentRequest->deployment_plan,
            'deployment_type' => $deploymentRequest->deployment_type,
        ], request: $request);

        return redirect($redirectTo)->with('status', 'Thanks. We received your deployment request.');
    }

    private function redirectTarget(StoreDeploymentRequest $request): string
    {
        return $request->input('return_to') === 'get-started'
            ? route('get-started').'#deployment-request'
            : url('/#deployment');
    }
}

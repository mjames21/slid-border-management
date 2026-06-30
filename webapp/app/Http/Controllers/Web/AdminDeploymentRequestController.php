<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\DeploymentRequest;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminDeploymentRequestController extends Controller
{
    public function index(): View
    {
        $deploymentRequests = DeploymentRequest::query()
            ->latest()
            ->paginate(25);

        return view('admin.deployment-requests.index', [
            'deploymentRequests' => $deploymentRequests,
            'statusLabels' => DeploymentRequest::statusLabels(),
        ]);
    }

    public function update(Request $request, DeploymentRequest $deploymentRequest, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(DeploymentRequest::statusLabels()))],
        ]);

        $deploymentRequest->forceFill(['status' => $validated['status']])->save();

        $audit->record('admin.deployment_request_updated', $request->user(), auditable: $deploymentRequest, metadata: [
            'status' => $deploymentRequest->status,
        ], request: $request);

        return back()->with('status', 'Deployment request updated.');
    }
}

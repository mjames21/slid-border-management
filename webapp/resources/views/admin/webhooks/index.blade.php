@extends('layouts.admin')

@section('content')
    <div class="workspace-bar">
        <div>
            <div class="workspace-crumb">BorderReach / Integrations</div>
            <h1 class="workspace-title">REST Services</h1>
            <p class="subtitle">Push accepted submissions to external systems as signed JSON events.</p>
        </div>
        <div class="workspace-meta">
            <span class="tag">{{ number_format($webhooks->total()) }} services</span>
            <span class="tag">HMAC signed</span>
            <span class="tag">One event per accepted record</span>
        </div>
    </div>

    <nav class="workspace-tabs" aria-label="Integration workspace tabs">
        <a class="workspace-tab" href="{{ route('admin.projects.index') }}">Projects</a>
        <a class="workspace-tab" href="{{ route('admin.submissions.index') }}">Data</a>
        <a class="workspace-tab" href="{{ route('admin.dashboard.index') }}">Analysis</a>
        <a class="workspace-tab active" href="{{ route('admin.webhooks.index') }}">REST Services</a>
        <a class="workspace-tab" href="{{ route('admin.users.index') }}">Team</a>
    </nav>

    <div class="workspace-tool-shell">
        <nav class="workspace-tool-nav" aria-label="Project settings">
            <a href="{{ route('admin.projects.index') }}"><span class="workspace-tool-icon">GN</span>General</a>
            <a href="{{ route('admin.forms.index') }}"><span class="workspace-tool-icon">FM</span>Forms</a>
            <a href="{{ route('admin.users.index') }}"><span class="workspace-tool-icon">SH</span>Sharing</a>
            <a href="{{ route('admin.dashboard.index') }}"><span class="workspace-tool-icon">CN</span>Connect Projects</a>
            <a class="active" href="{{ route('admin.webhooks.index') }}"><span class="workspace-tool-icon">RS</span>REST Services</a>
            <a href="{{ route('admin.deployment-requests.index') }}"><span class="workspace-tool-icon">AC</span>Activity</a>
        </nav>
        <section class="workspace-tool-content">
    <div class="grid">
        <div class="panel">
            <div class="panel-head">
                <div>
                    <h2 class="panel-title">Create REST service</h2>
                    <p class="panel-subtitle">Send matching future submissions to another platform, data warehouse, or workflow engine.</p>
                </div>
            </div>
            <form method="POST" action="{{ route('admin.webhooks.store') }}" style="padding:18px;">
                @csrf
                <div style="margin-bottom:14px;">
                    <label for="country_code">Country tenant</label>
                    <select id="country_code" name="country_code" required>
                        @foreach($countries as $country)
                            <option value="{{ $country->code }}" @selected(old('country_code') === $country->code)>
                                {{ $country->name }} ({{ $country->code }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div style="margin-bottom:14px;">
                    <label for="name">Service name</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" placeholder="National data warehouse" required>
                </div>
                <div style="margin-bottom:14px;">
                    <label for="endpoint_url">Endpoint URL</label>
                    <input id="endpoint_url" name="endpoint_url" type="text" value="{{ old('endpoint_url') }}" placeholder="https://example.gov/api/borderreach/submissions" required>
                    <div class="field-help">Use a public HTTPS endpoint in production. Local/private network targets are blocked for safety.</div>
                </div>
                <div style="margin-bottom:14px;">
                    <label for="signing_secret">Signing secret</label>
                    <input id="signing_secret" name="signing_secret" type="password" value="{{ old('signing_secret') }}" placeholder="Leave blank to generate">
                    <div class="field-help">BorderReach signs each payload with HMAC SHA-256. Keep this secret in the receiving system.</div>
                </div>
                <div class="grid" style="margin-bottom:14px;">
                    <div>
                        <label for="reporting_module">Report type scope</label>
                        <select id="reporting_module" name="reporting_module">
                            <option value="">All report types</option>
                            @foreach($moduleLabels as $module => $label)
                                <option value="{{ $module }}" @selected(old('reporting_module') === $module)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="form_id">Project/form scope</label>
                        <input id="form_id" name="form_id" type="text" value="{{ old('form_id') }}" placeholder="Optional form id">
                    </div>
                </div>
                <div class="grid" style="margin-bottom:16px;">
                    <div>
                        <label for="timeout_seconds">Timeout seconds</label>
                        <input id="timeout_seconds" name="timeout_seconds" type="number" min="2" max="30" value="{{ old('timeout_seconds', 10) }}" required>
                    </div>
                    <label style="display:flex;align-items:center;gap:10px;margin-top:28px;">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true)) style="width:auto;">
                        Active
                    </label>
                </div>
                <button type="submit">Create REST Service</button>
            </form>
        </div>

        <div class="panel">
            <div class="panel-head">
                <div>
                    <h2 class="panel-title">Payload contract</h2>
                    <p class="panel-subtitle">The receiver gets one clean event for each accepted submission.</p>
                </div>
            </div>
            <div style="padding:18px;">
                <p style="margin-top:0;color:var(--muted);">
                    Payloads include country tenant, form id/version, reporting module, officer, device, border post, digital address, GPS, timestamps, and the submission answers as JSON.
                </p>
                <div class="mono">POST /your-endpoint
Content-Type: application/json
X-BorderReach-Event: submission.accepted
X-BorderReach-Delivery: 123
X-BorderReach-Timestamp: 1782000000
X-BorderReach-Signature: sha256=&lt;hmac&gt;</div>
                <p class="field-help">Signature base string: timestamp + "." + raw JSON body. This allows the receiving system to verify the event came from BorderReach and was not changed in transit.</p>
            </div>
        </div>
    </div>

    <div class="panel" style="margin-top:20px;">
        <div class="panel-head">
            <div>
                <h2 class="panel-title">Configured services</h2>
                <p class="panel-subtitle">Each service is tenant scoped and can be narrowed to one report type or project.</p>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Service</th>
                    <th>Tenant</th>
                    <th>Endpoint</th>
                    <th>Scope</th>
                    <th>Deliveries</th>
                    <th>Status</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse($webhooks as $webhook)
                    <tr>
                        <td>
                            <div class="record-title">{{ $webhook->name }}</div>
                            <div class="record-muted">Created {{ $webhook->created_at?->diffForHumans() }}</div>
                        </td>
                        <td>
                            <span class="tag">{{ $webhook->country_code }}</span>
                            <div class="record-muted">{{ $webhook->country?->name }}</div>
                        </td>
                        <td><span class="mono inline" style="max-width:320px;">{{ $webhook->endpoint_url }}</span></td>
                        <td>
                            <div>{{ $webhook->reporting_module ? ($moduleLabels[$webhook->reporting_module] ?? $webhook->reporting_module) : 'All report types' }}</div>
                            <div class="record-muted">{{ $webhook->form_id ?: 'All projects' }}</div>
                        </td>
                        <td>
                            <span class="tag accepted">{{ $webhook->succeeded_deliveries_count }} sent</span>
                            <span class="tag queued">{{ $webhook->pending_deliveries_count }} pending</span>
                            <span class="tag failed">{{ $webhook->failed_deliveries_count }} failed</span>
                        </td>
                        <td><span class="tag {{ $webhook->is_active ? 'accepted' : 'draft' }}">{{ $webhook->is_active ? 'Active' : 'Paused' }}</span></td>
                        <td>
                            <form class="inline" method="POST" action="{{ route('admin.webhooks.toggle', $webhook) }}">
                                @csrf
                                <button class="button light" type="submit">{{ $webhook->is_active ? 'Pause' : 'Activate' }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7">No REST services configured yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{ $webhooks->links() }}

    <div class="panel" style="margin-top:20px;">
        <div class="panel-head">
            <div>
                <h2 class="panel-title">Recent delivery log</h2>
                <p class="panel-subtitle">Failed deliveries never block mobile sync; review and retry them here.</p>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Delivery</th>
                    <th>Service</th>
                    <th>Submission</th>
                    <th>Status</th>
                    <th>Attempt</th>
                    <th>Last result</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse($recentDeliveries as $delivery)
                    <tr>
                        <td>
                            <div class="record-title">#{{ $delivery->id }}</div>
                            <div class="record-muted">{{ $delivery->event_type }}</div>
                        </td>
                        <td>
                            {{ $delivery->outboundWebhook?->name ?? 'Deleted service' }}
                            <div class="record-muted">{{ $delivery->outboundWebhook?->country_code }}</div>
                        </td>
                        <td>
                            @if($delivery->mobileSubmission)
                                <a href="{{ route('admin.submissions.show', $delivery->mobileSubmission) }}">Submission #{{ $delivery->mobileSubmission->id }}</a>
                                <div class="record-muted mono inline">{{ $delivery->mobileSubmission->local_id }}</div>
                            @else
                                Deleted submission
                            @endif
                        </td>
                        <td><span class="tag {{ $delivery->status }}">{{ ucfirst($delivery->status) }}</span></td>
                        <td>{{ $delivery->attempts }}</td>
                        <td>
                            <div>{{ $delivery->last_status_code ? 'HTTP '.$delivery->last_status_code : 'No response code' }}</div>
                            @if($delivery->error_message)
                                <div class="record-muted">{{ $delivery->error_message }}</div>
                            @endif
                        </td>
                        <td>
                            @if($delivery->status === \App\Models\WebhookDelivery::STATUS_FAILED)
                                <form class="inline" method="POST" action="{{ route('admin.webhook-deliveries.retry', $delivery) }}">
                                    @csrf
                                    <button class="button light" type="submit">Retry</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7">No deliveries have been recorded yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

        </section>
    </div>
@endsection

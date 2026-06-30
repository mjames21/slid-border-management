@extends('layouts.admin')

@section('content')
    @php
        $visibleSubmissions = $submissions->getCollection();
        $acceptedVisible = $visibleSubmissions->where('status', 'accepted')->count();
        $rejectedVisible = $visibleSubmissions->where('status', 'rejected')->count();
        $gpsVisible = $visibleSubmissions->filter(fn ($submission) => $submission->device_latitude !== null && $submission->device_longitude !== null)->count();
    @endphp

    <div class="workspace-bar">
        <div>
            <div class="workspace-crumb">BorderReach / Project data</div>
            <h1 class="workspace-title">Submission Data</h1>
            <p class="subtitle">Review, filter, map, and export synced reports across country tenants and border posts.</p>
        </div>
        <div class="workspace-meta">
            <span class="tag">{{ number_format($submissions->total()) }} records</span>
            <a class="tool-button" href="{{ route('admin.submissions.export.csv', request()->query()) }}">Export CSV</a>
            <a class="tool-button" href="{{ route('admin.submissions.export.json', request()->query()) }}">Export JSON</a>
        </div>
    </div>

    <nav class="workspace-tabs" aria-label="Submission workspace tabs">
        <a class="workspace-tab" href="{{ route('admin.dashboard.index') }}">Summary</a>
        <a class="workspace-tab" href="{{ route('admin.forms.index') }}">Forms</a>
        <a class="workspace-tab active" href="{{ route('admin.submissions.index') }}">Data</a>
        <a class="workspace-tab" href="{{ route('admin.map.index') }}">Map</a>
        <a class="workspace-tab" href="{{ route('admin.users.index') }}">Team</a>
    </nav>

    <div class="workspace-tool-shell">
        <nav class="workspace-tool-nav" aria-label="Data tools">
            <a class="active" href="{{ route('admin.submissions.index') }}"><span class="workspace-tool-icon">TB</span>Table</a>
            <a href="{{ route('admin.dashboard.index') }}"><span class="workspace-tool-icon">RP</span>Reports</a>
            <a href="{{ route('admin.submissions.export.csv', request()->query()) }}"><span class="workspace-tool-icon">DL</span>Downloads</a>
            <a href="{{ route('admin.map.index') }}"><span class="workspace-tool-icon">MP</span>Map</a>
        </nav>
        <section class="workspace-tool-content">
    <div class="metric-strip">
        <div class="metric-card"><span>Total matching</span><strong>{{ number_format($submissions->total()) }}</strong></div>
        <div class="metric-card"><span>Accepted on page</span><strong>{{ number_format($acceptedVisible) }}</strong></div>
        <div class="metric-card"><span>Rejected on page</span><strong>{{ number_format($rejectedVisible) }}</strong></div>
        <div class="metric-card"><span>With GPS on page</span><strong>{{ number_format($gpsVisible) }}</strong></div>
    </div>

    <div class="panel" style="margin-bottom:20px;">
        <div class="panel-head">
            <div>
                <h2 class="panel-title">Filters</h2>
                <p class="panel-subtitle">Build the current data view before review, export, or map analysis.</p>
            </div>
        </div>
        <form method="GET" action="{{ route('admin.submissions.index') }}" class="filter-grid" style="padding:18px;">
            <div>
                <label for="country_code">Country</label>
                <select id="country_code" name="country_code">
                    <option value="">Any</option>
                    @foreach($countries as $country)
                        <option value="{{ $country->code }}" @selected(($filters['country_code'] ?? '') === $country->code)>
                            {{ $country->name }} ({{ $country->code }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="reporting_module">Module</label>
                <select id="reporting_module" name="reporting_module">
                    <option value="">Any</option>
                    @foreach($moduleLabels as $module => $label)
                        <option value="{{ $module }}" @selected(($filters['reporting_module'] ?? '') === $module)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="form_id">Form ID</label>
                <input id="form_id" name="form_id" type="text" value="{{ $filters['form_id'] ?? '' }}">
            </div>
            <div>
                <label for="device_id">Device ID</label>
                <input id="device_id" name="device_id" type="text" value="{{ $filters['device_id'] ?? '' }}">
            </div>
            <div>
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">Any</option>
                    @foreach(['accepted', 'rejected'] as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>
            <div><button type="submit" style="width:100%;">Apply Filters</button></div>
        </form>
    </div>

    <div class="panel">
        <div class="panel-head">
            <div>
                <h2 class="panel-title">Table</h2>
                <p class="panel-subtitle">Each row is one synced report with custody, tenant, device, GPS, and answers stored as JSON.</p>
            </div>
            <span class="tag">{{ $submissions->firstItem() ?? 0 }}-{{ $submissions->lastItem() ?? 0 }} of {{ $submissions->total() }}</span>
        </div>
        <div class="data-toolbar">
            <div class="toolbar-group">
                <label style="display:flex;align-items:center;gap:8px;margin:0;font-size:13px;">
                    <input type="checkbox" aria-label="Select all visible submissions">
                    Select visible
                </label>
                <span class="selected-count">0 selected</span>
                <button type="button" class="tool-button">Hide fields</button>
                <button type="button" class="tool-button">Change status</button>
            </div>
            <div class="toolbar-group">
                <input type="search" aria-label="Search visible submissions" placeholder="Search visible records" style="min-width:240px;">
                <a class="tool-button primary" href="{{ route('admin.submissions.export.csv', request()->query()) }}">Download data</a>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th class="checkbox-cell"></th>
                    <th>Receipt</th>
                    <th>Tenant</th>
                    <th>Report Type</th>
                    <th>Border Post</th>
                    @foreach($answerColumns as $column)
                        <th>{{ $column['label'] }}</th>
                    @endforeach
                    <th>Form Version</th>
                    <th>Device</th>
                    <th>Status</th>
                    <th>Received</th>
                    <th></th>
                </tr>
                <tr class="column-filter-row" aria-label="Column filters">
                    <th class="checkbox-cell"></th>
                    <th><input type="text" placeholder="Receipt"></th>
                    <th><input type="text" placeholder="Tenant"></th>
                    <th><input type="text" placeholder="Type"></th>
                    <th><input type="text" placeholder="Post"></th>
                    @foreach($answerColumns as $column)
                        <th><input type="text" placeholder="{{ $column['label'] }}"></th>
                    @endforeach
                    <th><input type="text" placeholder="Version"></th>
                    <th><input type="text" placeholder="Device"></th>
                    <th>
                        <select aria-label="Status column filter">
                            <option>Any</option>
                            <option>Accepted</option>
                            <option>Rejected</option>
                        </select>
                    </th>
                    <th><input type="text" placeholder="Received"></th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse($submissions as $submission)
                    <tr>
                        <td class="checkbox-cell"><input type="checkbox" aria-label="Select submission {{ $submission->id }}"></td>
                        <td>
                            <div class="record-title">#{{ $submission->id }}</div>
                            <div class="record-muted mono inline">{{ $submission->local_id }}</div>
                        </td>
                        <td>
                            <span class="tag">{{ $submission->country_code }}</span>
                            <div class="record-muted">{{ $submission->region ?: 'No region' }}</div>
                        </td>
                        <td>{{ $submission->reportingModuleLabel() }}</td>
                        <td>
                            <div class="record-title">{{ $submission->border_post_code ?: '-' }}</div>
                            <div class="record-muted">{{ $submission->border_post_digital_address ?: 'No digital address' }}</div>
                        </td>
                        @foreach($answerColumns as $column)
                            @php
                                $answers = is_array($submission->answers) ? $submission->answers : [];
                                $answerValue = array_key_exists($column['id'], $answers) ? $answers[$column['id']] : null;
                            @endphp
                            <td>
                                @if(is_array($answerValue) || is_object($answerValue))
                                    <span class="mono inline">{{ json_encode($answerValue, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</span>
                                @elseif(is_bool($answerValue))
                                    {{ $answerValue ? 'Yes' : 'No' }}
                                @elseif($answerValue === null || $answerValue === '')
                                    -
                                @else
                                    {{ $answerValue }}
                                @endif
                            </td>
                        @endforeach
                        <td>
                            <span class="mono inline">{{ $submission->form_id }}</span>
                            <div class="record-muted">Version {{ $submission->form_version }}</div>
                        </td>
                        <td><span class="mono inline">{{ $submission->device_id }}</span></td>
                        <td><span class="tag {{ $submission->status }}">{{ ucfirst($submission->status) }}</span></td>
                        <td>{{ $submission->received_at }}</td>
                        <td>
                            <div class="row-actions">
                                <a class="button light" href="{{ route('admin.submissions.show', $submission) }}">Open</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ 10 + count($answerColumns) }}">No submissions match these filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{ $submissions->links() }}
        </section>
    </div>
@endsection

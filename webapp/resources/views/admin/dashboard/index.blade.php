@extends('layouts.admin')

@section('content')
    <style>
        .dashboard-controls{display:flex;gap:12px;flex-wrap:wrap;align-items:end}.dashboard-controls>div{min-width:170px}.dashboard-controls .wide{min-width:240px;flex:1}.dashboard-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center}.dashboard-message{color:#0f766e;font-size:13px;min-height:18px}.filter-toolbar{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center;margin-top:16px}.filter-builder{display:grid;gap:10px;margin-top:12px}.filter-row{display:grid;grid-template-columns:minmax(160px,1fr) minmax(140px,.8fr) minmax(180px,1fr) auto;gap:10px;align-items:end}.filter-remove{background:#fff;color:#b91c1c;border:1px solid #fecaca}.kpi-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-bottom:18px}.kpi{background:#fff;border:1px solid #dbe3ea;border-radius:8px;padding:14px}.kpi .value{font-size:28px;font-weight:700;margin-top:4px}.kpi .hint{color:#64748b;font-size:12px;margin-top:2px}.dashboard-grid{display:grid;grid-template-columns:1fr;gap:18px}.map-wrap{height:520px;min-height:360px}.map-svg{width:100%;height:100%;background:#eef6f3;border:1px solid #dbe3ea;border-radius:8px}.map-boundary{fill:#d9f2e5;stroke:#0f766e;stroke-width:1.5;fill-rule:evenodd}.map-point{cursor:pointer;stroke:#fff;stroke-width:1.4}.map-point.accepted{fill:#2563eb}.map-point.rejected,.map-point.failed{fill:#dc2626}.map-empty{color:#64748b;padding:24px;text-align:center}.panel-stack{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.panel[draggable=true]{cursor:grab}.panel[data-panel="discover"],.panel[data-panel="detail"],.panel[data-panel="reports"],.panel[data-panel="aggregates"]{grid-column:1/-1}.analysis-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.analysis-section h3{font-size:14px;margin:0 0 8px}.timeline-chart{display:flex;align-items:end;gap:3px;height:128px;padding:12px 4px 4px;border-bottom:1px solid #cbd5e1;overflow-x:auto}.timeline-bar{position:relative;min-width:18px;flex:1;height:100%;display:flex;align-items:end}.timeline-bar span{display:block;width:100%;min-height:3px;background:#0f766e;border-radius:3px 3px 0 0}.timeline-bar strong{position:absolute;left:50%;bottom:-28px;transform:translateX(-50%);white-space:nowrap;color:#64748b;font-size:10px;font-weight:600}.aggregate-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;align-items:center;margin:8px 0}.aggregate-row span:first-child{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.bar{grid-column:1/3;height:8px;background:#e2e8f0;border-radius:999px;overflow:hidden}.bar span{display:block;height:100%;background:#0f766e}.report-list{max-height:360px;overflow:auto}.report-item{border-bottom:1px solid #e5e7eb;padding:10px 0;cursor:pointer}.report-item:hover,.report-item.selected{background:#f8fafc}.report-meta{color:#64748b;font-size:12px}.detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}.detail-grid .mono{padding:10px}.status-dot{display:inline-block;width:9px;height:9px;border-radius:999px;background:#2563eb;margin-right:6px}.status-dot.rejected,.status-dot.failed{background:#dc2626}.discover-table-wrap{max-height:420px;overflow:auto;border:1px solid #e2e8f0;border-radius:8px}.discover-table{width:100%;border-collapse:collapse;font-size:13px}.discover-table th{position:sticky;top:0;background:#f8fafc;text-align:left;color:#334155}.discover-table th,.discover-table td{padding:9px 10px;border-bottom:1px solid #e2e8f0;vertical-align:top;white-space:nowrap}.discover-table td.wrap{white-space:normal;min-width:180px}.receipt{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12px;color:#475569}.quality-note{font-size:13px;color:#64748b;margin:0 0 10px}@media(max-width:1100px){.kpi-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.panel-stack{grid-template-columns:1fr}.panel[data-panel="discover"],.panel[data-panel="detail"],.panel[data-panel="reports"],.panel[data-panel="aggregates"]{grid-column:auto}}@media(max-width:700px){.dashboard-grid,.kpi-grid,.filter-row,.analysis-grid,.detail-grid{grid-template-columns:1fr}.map-wrap{height:420px}.timeline-bar{min-width:22px}}
    </style>

    <div id="operations-dashboard"
         data-dashboard
         data-data-url="{{ route('admin.dashboard.data') }}"
         data-save-view-url="{{ route('admin.dashboard.views.store') }}"
         data-delete-view-url="{{ route('admin.dashboard.views.destroy', ['dashboardView' => '__ID__']) }}"
         data-dashboard-views="{{ e(json_encode($dashboardViews)) }}"
         data-filter-fields="{{ e(json_encode($filterFields)) }}"
         data-operator-labels="{{ e(json_encode($operatorLabels)) }}">
    <div class="header">
        <div>
            <h1 class="title">Live Border Operations</h1>
            <p class="subtitle">Map reports, monitor sync, and review field activity.</p>
        </div>
        <div class="mono" id="last-updated">Waiting for data</div>
    </div>

    <div class="card">
        <div class="dashboard-controls">
            <div class="wide">
                <label for="dashboard_view">Operations View</label>
                <select id="dashboard_view">
                    <option value="">Unsaved live view</option>
                </select>
            </div>
            <div class="wide">
                <label for="view_name">View Name</label>
                <input id="view_name" type="text" maxlength="80" value="Sierra Leone Live Operations">
            </div>
            <div>
                <label><input id="view_default" type="checkbox"> Default</label>
                <div class="dashboard-message" id="view_status"></div>
            </div>
            <div class="dashboard-actions">
                <button type="button" id="save_view_button">Save View</button>
                <button type="button" class="button secondary" id="delete_view_button">Delete</button>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="dashboard-controls">
            <div>
                <label for="country_code">Country</label>
                <select id="country_code">
                    @foreach($countries as $country)
                        <option value="{{ $country->code }}" @selected($country->code === 'SLE')>{{ $country->name }} ({{ $country->code }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="hours">Window</label>
                <select id="hours">
                    <option value="1">Last hour</option>
                    <option value="24" selected>Last 24 hours</option>
                    <option value="72">Last 3 days</option>
                    <option value="168">Last 7 days</option>
                </select>
            </div>
            <div class="wide">
                <label for="discover_search">Discover Search</label>
                <input id="discover_search" type="search" maxlength="120" placeholder="Receipt, device, form, post, traveller, document">
            </div>
            <div>
                <label><input id="auto_refresh" type="checkbox" checked> Auto refresh</label>
                <div class="field-help">Refreshes every 10 seconds.</div>
            </div>
            <div>
                <button type="button" id="refresh_button">Refresh</button>
            </div>
        </div>
        <div class="filter-toolbar">
            <strong>Visual Filters</strong>
            <div class="dashboard-actions">
                <button type="button" class="button light" id="add_filter_button">Add Filter</button>
                <button type="button" class="button light" id="clear_filters_button">Clear</button>
                <button type="button" id="apply_filters_button">Apply</button>
            </div>
        </div>
        <div id="filter-builder" class="filter-builder"></div>
    </div>

    <div class="kpi-grid">
        <div class="kpi"><label>Total Reports</label><div class="value" id="kpi-total">0</div></div>
        <div class="kpi"><label>GPS Coverage</label><div class="value" id="kpi-gps-rate">0%</div><div class="hint" id="kpi-with-location">0 located</div></div>
        <div class="kpi"><label>Today</label><div class="value" id="kpi-today">0</div></div>
        <div class="kpi"><label>Last Hour</label><div class="value" id="kpi-last-hour">0</div></div>
        <div class="kpi"><label>Devices</label><div class="value" id="kpi-devices">0</div></div>
        <div class="kpi"><label>Review Queue</label><div class="value" id="kpi-rejected">0</div></div>
    </div>

    <div class="dashboard-grid" id="dashboard-grid">
        <div class="card panel" draggable="true" data-panel="map">
            <h2 class="title" style="font-size:20px;">Country Map</h2>
            <p class="subtitle" id="map-subtitle">Upload a country boundary from Country Profiles to draw the base map.</p>
            <div class="map-wrap" id="map-wrap">
                <svg class="map-svg" id="map-svg" viewBox="0 0 1000 620" role="img" aria-label="Report map"></svg>
            </div>
        </div>

        <div class="panel-stack">
            <div class="card panel" draggable="true" data-panel="timeline">
                <h2 class="title" style="font-size:20px;">Submission Timeline</h2>
                <div id="timeline-chart" class="timeline-chart"></div>
            </div>

            <div class="card panel" draggable="true" data-panel="breakdowns">
                <h2 class="title" style="font-size:20px;">Breakdowns</h2>
                <div class="analysis-grid">
                    <div class="analysis-section">
                        <h3>Status</h3>
                        <div id="analysis-status"></div>
                    </div>
                    <div class="analysis-section">
                        <h3>Module</h3>
                        <div id="analysis-module"></div>
                    </div>
                    <div class="analysis-section">
                        <h3>Movement</h3>
                        <div id="analysis-movement"></div>
                    </div>
                    <div class="analysis-section">
                        <h3>Decision</h3>
                        <div id="analysis-decision"></div>
                    </div>
                    <div class="analysis-section">
                        <h3>Document Type</h3>
                        <div id="analysis-document"></div>
                    </div>
                </div>
            </div>

            <div class="card panel" draggable="true" data-panel="quality">
                <h2 class="title" style="font-size:20px;">Sync Quality</h2>
                <p class="quality-note">Monitor offline delay, GPS completeness, and form version mix for field reliability.</p>
                <div class="analysis-grid">
                    <div class="analysis-section">
                        <h3>Sync Delay</h3>
                        <div id="analysis-sync-latency"></div>
                    </div>
                    <div class="analysis-section">
                        <h3>Quality Flags</h3>
                        <div id="analysis-data-quality"></div>
                    </div>
                    <div class="analysis-section">
                        <h3>Form Versions</h3>
                        <div id="analysis-form-version"></div>
                    </div>
                    <div class="analysis-section">
                        <h3>Nationality</h3>
                        <div id="analysis-nationality"></div>
                    </div>
                </div>
            </div>

            <div class="card panel" draggable="true" data-panel="discover">
                <h2 class="title" style="font-size:20px;">Discover Records</h2>
                <p class="quality-note">Search and inspect the latest matching submissions with receipt, device, post, digital address, document, status, and sync delay.</p>
                <div id="discover-table"></div>
            </div>

            <div class="card panel" draggable="true" data-panel="devices">
                <h2 class="title" style="font-size:20px;">Device and GPS Quality</h2>
                <div class="analysis-grid">
                    <div class="analysis-section">
                        <h3>Top Devices</h3>
                        <div id="analysis-devices"></div>
                    </div>
                    <div class="analysis-section">
                        <h3>GPS Quality</h3>
                        <div id="analysis-gps"></div>
                    </div>
                </div>
            </div>

            <div class="card panel" draggable="true" data-panel="detail">
                <h2 class="title" style="font-size:20px;">Selected Report</h2>
                <div id="selected-report" class="map-empty">Click a point or report row.</div>
            </div>

            <div class="card panel" draggable="true" data-panel="reports">
                <h2 class="title" style="font-size:20px;">Latest Reports</h2>
                <div id="report-list" class="report-list"></div>
            </div>

            <div class="card panel" draggable="true" data-panel="aggregates">
                <h2 class="title" style="font-size:20px;">Aggregates</h2>
                <h3>By Module</h3>
                <div id="agg-module"></div>
                <h3>By Border Post</h3>
                <div id="agg-border"></div>
                <h3>By Form</h3>
                <div id="agg-form"></div>
                <h3>By Region</h3>
                <div id="agg-region"></div>
            </div>
        </div>
    </div>

    </div>
@endsection

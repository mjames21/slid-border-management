@extends('layouts.admin')

@section('content')
    <style>
        .map-page-grid{display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:18px}.map-controls{display:flex;gap:12px;flex-wrap:wrap;align-items:end}.map-controls>div{min-width:180px}.map-shell{border:1px solid #dce3ee;background:#fff}.map-stage{height:640px;min-height:420px;padding:14px}.report-map-svg{width:100%;height:100%;border:1px solid #dbe3ea;border-radius:8px;background:#eef6f3}.report-map-boundary{fill:#d9f2e5;stroke:#0f766e;stroke-width:1.5;fill-rule:evenodd}.report-map-point{cursor:pointer;stroke:#fff;stroke-width:1.5}.report-map-point.accepted{fill:#2563eb}.report-map-point.rejected,.report-map-point.failed{fill:#dc2626}.report-map-point.selected{stroke:#111827;stroke-width:2.5}.map-side{display:grid;gap:14px;align-content:start}.map-stat-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}.map-stat{border:1px solid #dce3ee;background:#fff;padding:13px}.map-stat label{margin:0;color:#64748b}.map-stat strong{display:block;margin-top:5px;color:#1f2937;font-size:24px;line-height:1}.map-report-list{max-height:420px;overflow:auto}.map-report-item{display:block;border-bottom:1px solid #e5e7eb;color:#1f2937;text-decoration:none;padding:11px 0}.map-report-item:hover,.map-report-item.selected{background:#f8fafc}.map-report-item strong{display:block}.map-report-item span{display:block;color:#64748b;font-size:12px;margin-top:2px}.map-empty{color:#64748b;padding:24px;text-align:center}.map-empty strong{display:block;color:#1f2937;margin-bottom:6px}.map-detail-row{display:grid;grid-template-columns:120px minmax(0,1fr);gap:10px;border-bottom:1px solid #eef2f6;padding:8px 0}.map-detail-row dt{color:#64748b;font-weight:800}.map-detail-row dd{margin:0;word-break:break-word}.legend{display:flex;gap:12px;flex-wrap:wrap;color:#64748b;font-size:12px}.legend span{display:inline-flex;align-items:center;gap:6px}.legend i{display:inline-block;width:10px;height:10px;border-radius:999px;background:#2563eb}.legend .review i{background:#dc2626}.map-config-grid{display:grid;grid-template-columns:1.1fr 1fr;gap:14px;margin-bottom:18px}.map-config-card{border:1px solid #dce3ee;background:#fff;padding:16px}.map-config-card h3{margin:0 0 6px;color:#1f2937;font-size:17px}.map-config-card p{margin:0 0 12px;color:#64748b;line-height:1.45}.compact-upload{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;align-items:end}.compact-upload input[type=file]{padding:10px;background:#f8fafc}.upload-progress{display:none;margin-top:12px;border:1px solid #bbf7d0;background:#f0fdf4;color:#14532d;padding:12px}.upload-progress.active{display:block}.upload-progress strong{display:flex;align-items:center;gap:8px;margin-bottom:8px}.spinner{width:16px;height:16px;border:2px solid #bbf7d0;border-top-color:#15803d;border-radius:999px;animation:spin .8s linear infinite}.upload-bar{height:8px;overflow:hidden;border-radius:999px;background:#d1fae5}.upload-bar span{display:block;width:42%;height:100%;border-radius:999px;background:#15803d;animation:progress-slide 1.1s ease-in-out infinite}button.is-loading{cursor:wait;opacity:.8}@keyframes spin{to{transform:rotate(360deg)}}@keyframes progress-slide{0%{transform:translateX(-110%)}100%{transform:translateX(250%)}}@media(max-width:1100px){.map-page-grid,.map-config-grid{grid-template-columns:1fr}.map-side{grid-template-columns:repeat(2,minmax(0,1fr))}.map-side .panel{min-width:0}}@media(max-width:720px){.map-controls,.map-page-grid,.map-side,.map-stat-grid,.compact-upload{grid-template-columns:1fr}.map-stage{height:460px;padding:10px}.map-detail-row{grid-template-columns:1fr}}
    </style>

    <div id="report-map-page"
         data-map-page
         data-data-url="{{ route('admin.dashboard.data') }}"
         data-country-edit-url-template="{{ route('admin.countries.edit', ['country' => '__CODE__']) }}"
         data-boundary-upload-url-template="{{ route('admin.countries.boundary.update', ['country' => '__CODE__']) }}">
        <div class="workspace-bar">
            <div>
                <div class="workspace-crumb">BorderReach / Map</div>
                <h1 class="workspace-title">View Reports on Map</h1>
                <p class="subtitle">Plot synced GPS reports against the uploaded country boundary or shapefile.</p>
            </div>
            <div class="workspace-meta">
                <span class="tag {{ $selectedCountry?->boundary_geojson_path ? 'published' : 'draft' }}" id="map-boundary-status">
                    {{ $selectedCountry?->boundary_geojson_path ? 'Boundary uploaded' : 'No boundary uploaded' }}
                </span>
                <span class="tag" id="map-point-status">0 plotted</span>
                <a class="tool-button" href="{{ route('admin.countries.index') }}">Country Config</a>
                <a class="tool-button" href="{{ route('admin.users.index') }}">Mobile Setup QR</a>
            </div>
        </div>

        <div class="map-config-grid">
            <div class="map-config-card">
                <h3>Map Boundary Configuration</h3>
                <p>Upload a country GeoJSON or zipped polygon shapefile. BorderReach stores it on the country tenant and uses it to draw this operational map.</p>
                @if($selectedCountry?->boundary_geojson_path)
                    <div class="tag published" style="margin-bottom:12px;">
                        {{ $selectedCountry->name }} boundary: {{ $selectedCountry->boundary_source_name ?: $selectedCountry->boundary_geojson_path }}
                    </div>
                @endif
                <form method="POST" enctype="multipart/form-data" id="map_boundary_upload_form" action="{{ route('admin.countries.boundary.update', ['country' => 'SLE']) }}">
                    @csrf
                    <div class="compact-upload">
                        <div>
                            <label for="map_boundary_file">Boundary file</label>
                            <input id="map_boundary_file" name="boundary_file" type="file" accept=".geojson,.json,.zip,.shp,application/geo+json,application/json,application/zip" required>
                            <div class="field-help">Accepted: GeoJSON, JSON, ZIP shapefile, or SHP. Max 20MB.</div>
                            @error('boundary_file')
                                <div class="field-error">{{ $message }}</div>
                            @enderror
                        </div>
                        <button type="submit" data-loading-text="Uploading boundary...">Upload Boundary</button>
                    </div>
                    <div class="upload-progress" id="map_boundary_upload_progress" role="status" aria-live="polite">
                        <strong><span class="spinner" aria-hidden="true"></span>Your country shapefile is uploading and processing.</strong>
                        <div class="upload-bar" aria-hidden="true"><span></span></div>
                        <div class="field-help">Please keep this page open. Large GIS ZIP files can take a moment while BorderReach reads the boundary layer.</div>
                    </div>
                </form>
            </div>
            <div class="map-config-card">
                <h3>Android App Configuration</h3>
                <p>Set the app title, subtitle, loading/splash logo, and login logo from the country profile. Then generate a setup QR for each officer from Users.</p>
                <div class="actions">
                    <a class="button light" id="map_country_config_link" href="{{ route('admin.countries.edit', ['country' => 'SLE']) }}">Configure App Branding</a>
                    <a class="button light" href="{{ route('admin.users.index') }}">Generate Setup QR</a>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="map-controls">
                <div>
                    <label for="map_country_code">Country</label>
                    <select id="map_country_code">
                        @foreach($countries as $country)
                            <option value="{{ $country->code }}" @selected($selectedCountry?->code === $country->code)>{{ $country->name }} ({{ $country->code }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="map_hours">Window</label>
                    <select id="map_hours">
                        <option value="1">Last hour</option>
                        <option value="24" selected>Last 24 hours</option>
                        <option value="72">Last 3 days</option>
                        <option value="168">Last 7 days</option>
                        <option value="this_month">This month</option>
                        <option value="last_month">Last month</option>
                        <option value="last_3_months">Last 3 months</option>
                        <option value="last_6_months">Last 6 months</option>
                        <option value="this_year">This year</option>
                        <option value="last_year">Last year</option>
                        <option value="all">All records</option>
                    </select>
                </div>
                <div style="flex:1;min-width:260px;">
                    <label for="map_search">Search</label>
                    <input id="map_search" type="search" maxlength="120" placeholder="Receipt, device, form, post, traveller, document">
                </div>
                <div class="actions">
                    <button type="button" id="map_refresh_button">Refresh</button>
                    <a class="button light" id="map_boundary_link" href="{{ route('admin.countries.edit', ['country' => 'SLE']) }}">Full Country Config</a>
                </div>
            </div>
            <div class="field-help">Upload a GeoJSON or zipped polygon shapefile from the country profile. Reports without GPS remain in the table, but only reports with longitude and latitude plot here.</div>
        </div>

        <div class="map-page-grid">
            <div class="map-shell">
                <div class="panel-head">
                    <div>
                        <h2 class="panel-title">Operational Map</h2>
                        <p class="panel-subtitle" id="map-subtitle">Loading country boundary and GPS reports.</p>
                    </div>
                    <div class="legend">
                        <span><i></i>Accepted</span>
                        <span class="review"><i></i>Review queue</span>
                    </div>
                </div>
                <div class="map-stage">
                    <svg class="report-map-svg" id="report-map-svg" viewBox="0 0 1000 620" role="img" aria-label="Country boundary with GPS report points"></svg>
                </div>
            </div>

            <aside class="map-side">
                <div class="map-stat-grid">
                    <div class="map-stat"><label>Total</label><strong id="map-total">0</strong></div>
                    <div class="map-stat"><label>With GPS</label><strong id="map-with-gps">0</strong></div>
                    <div class="map-stat"><label>GPS coverage</label><strong id="map-gps-rate">0%</strong></div>
                    <div class="map-stat"><label>Missing GPS</label><strong id="map-missing-gps">0</strong></div>
                </div>

                <div class="panel">
                    <div class="panel-head">
                        <div>
                            <h2 class="panel-title">Selected Report</h2>
                            <p class="panel-subtitle">Click a point or latest report.</p>
                        </div>
                    </div>
                    <div id="map-selected-report" style="padding:14px;">
                        <div class="map-empty">No report selected.</div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-head">
                        <div>
                            <h2 class="panel-title">Latest Located Reports</h2>
                            <p class="panel-subtitle">Most recent submissions with GPS.</p>
                        </div>
                    </div>
                    <div id="map-report-list" class="map-report-list" style="padding:0 14px;"></div>
                </div>
            </aside>
        </div>
    </div>

    <script nonce="{{ request()->attributes->get('csp_nonce') }}">
        (() => {
            const page = document.querySelector('[data-map-page]');
            if (!page) return;

            const svg = document.getElementById('report-map-svg');
            const state = { data: null, selectedId: null };
            const countrySelect = document.getElementById('map_country_code');
            const hoursSelect = document.getElementById('map_hours');
            const searchInput = document.getElementById('map_search');
            const boundaryLink = document.getElementById('map_boundary_link');
            const countryConfigLink = document.getElementById('map_country_config_link');
            const boundaryUploadForm = document.getElementById('map_boundary_upload_form');
            const boundaryUploadProgress = document.getElementById('map_boundary_upload_progress');

            document.getElementById('map_refresh_button').addEventListener('click', loadMap);
            countrySelect.addEventListener('change', loadMap);
            hoursSelect.addEventListener('change', loadMap);
            searchInput.addEventListener('keydown', event => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    loadMap();
                }
            });
            boundaryUploadForm.addEventListener('submit', lockBoundaryUpload);

            loadMap();

            function lockBoundaryUpload(event) {
                if (boundaryUploadForm.dataset.submitting === 'true') {
                    event.preventDefault();
                    return;
                }

                const fileInput = boundaryUploadForm.querySelector('input[type="file"]');
                if (!fileInput?.files?.length) {
                    return;
                }

                boundaryUploadForm.dataset.submitting = 'true';
                boundaryUploadProgress?.classList.add('active');
                boundaryUploadForm.querySelectorAll('button, a').forEach(control => {
                    control.classList.add('is-loading');
                    if (control.tagName === 'BUTTON') {
                        control.disabled = true;
                        control.textContent = control.dataset.loadingText || 'Uploading...';
                    }
                });
            }

            async function loadMap() {
                updateBoundaryLink();
                document.getElementById('map-subtitle').textContent = 'Loading boundary and GPS reports...';
                document.getElementById('map-point-status').textContent = 'Loading';
                svg.innerHTML = '<text x="500" y="310" text-anchor="middle" fill="#64748b">Loading map...</text>';
                const params = new URLSearchParams({
                    view: 'map',
                    country_code: countrySelect.value,
                    hours: hoursSelect.value,
                    q: searchInput.value.trim(),
                    filters: JSON.stringify([{ field: 'location', operator: 'equals', value: true }]),
                });

                try {
                    const response = await fetch(`${page.dataset.dataUrl}?${params.toString()}`, { headers: { Accept: 'application/json' } });
                    if (!response.ok) throw new Error(`Map request failed with status ${response.status}`);
                    state.data = await response.json();
                    state.selectedId = state.data.points?.[0]?.id || null;
                    render();
                } catch (error) {
                    svg.innerHTML = '<text x="500" y="310" text-anchor="middle" fill="#64748b">Unable to load map data</text>';
                    document.getElementById('map-subtitle').textContent = 'Map data unavailable. Check the server and try again.';
                    console.error(error);
                }
            }

            function updateBoundaryLink() {
                boundaryLink.href = page.dataset.countryEditUrlTemplate.replace('__CODE__', countrySelect.value);
                countryConfigLink.href = page.dataset.countryEditUrlTemplate.replace('__CODE__', countrySelect.value);
                boundaryUploadForm.action = page.dataset.boundaryUploadUrlTemplate.replace('__CODE__', countrySelect.value);
            }

            function render() {
                const data = state.data;
                const metrics = data.metrics || {};
                document.getElementById('map-total').textContent = metrics.total ?? 0;
                document.getElementById('map-with-gps').textContent = metrics.withLocation ?? 0;
                document.getElementById('map-gps-rate').textContent = `${metrics.gpsCoveragePercent ?? 0}%`;
                document.getElementById('map-missing-gps').textContent = metrics.withoutLocation ?? 0;
                document.getElementById('map-boundary-status').textContent = data.country?.hasBoundary ? 'Boundary loaded' : 'No boundary uploaded';
                document.getElementById('map-point-status').textContent = `${data.points?.length || 0} plotted`;
                const simplifiedNote = data.boundaryMeta?.simplified ? ' Optimized for fast display.' : '';
                const windowLabel = data.window?.label ? ` (${data.window.label})` : '';
                document.getElementById('map-subtitle').textContent = data.country?.hasBoundary
                    ? `${data.country.name} boundary with ${data.points.length} GPS report(s)${windowLabel}.${simplifiedNote}`
                    : 'No boundary uploaded yet. Use Map Boundary Configuration above to upload GeoJSON or a zipped shapefile.';

                renderSvg(data);
                renderList(data.points || []);
                renderSelected((data.points || []).find(point => point.id === state.selectedId) || null);
            }

            function renderSvg(data) {
                clear(svg);
                const points = data.points || [];
                const coordinates = collectCoordinates(data.boundary).concat(points.map(point => [point.longitude, point.latitude]));
                if (!coordinates.length) {
                    svg.innerHTML = '<text x="500" y="295" text-anchor="middle" fill="#1f2937" font-weight="700">No map data yet</text><text x="500" y="322" text-anchor="middle" fill="#64748b">Upload a country boundary and sync reports with GPS coordinates.</text>';
                    return;
                }

                const bounds = computeBounds(coordinates);
                const project = ([lon, lat]) => [
                    34 + ((lon - bounds.minLon) / Math.max(bounds.maxLon - bounds.minLon, 0.000001)) * 932,
                    34 + (1 - ((lat - bounds.minLat) / Math.max(bounds.maxLat - bounds.minLat, 0.000001))) * 552,
                ];

                geoJsonPaths(data.boundary, project).forEach(pathD => {
                    const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                    path.setAttribute('d', pathD);
                    path.setAttribute('class', 'report-map-boundary');
                    svg.appendChild(path);
                });

                points.forEach(point => {
                    if (point.longitude === null || point.latitude === null) return;
                    const [x, y] = project([point.longitude, point.latitude]);
                    const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
                    circle.setAttribute('cx', x);
                    circle.setAttribute('cy', y);
                    circle.setAttribute('r', point.id === state.selectedId ? 8 : 5);
                    circle.setAttribute('class', `report-map-point ${point.status || 'accepted'} ${point.id === state.selectedId ? 'selected' : ''}`);
                    circle.addEventListener('click', () => {
                        state.selectedId = point.id;
                        render();
                    });
                    const title = document.createElementNS('http://www.w3.org/2000/svg', 'title');
                    title.textContent = `${point.borderPostCode || 'Unknown post'} / ${point.formId || 'Unknown form'}`;
                    circle.appendChild(title);
                    svg.appendChild(circle);
                });
            }

            function renderList(points) {
                const list = document.getElementById('map-report-list');
                if (!points.length) {
                    list.innerHTML = '<div class="map-empty"><strong>No GPS reports in this window.</strong>When Android submissions include latitude and longitude, they will appear here.</div>';
                    return;
                }

                list.innerHTML = points.slice(0, 80).map(point => `
                    <a href="#" class="map-report-item ${point.id === state.selectedId ? 'selected' : ''}" data-report-id="${point.id}">
                        <strong>#${escapeHtml(point.id)} ${escapeHtml(point.borderPostCode || 'No post')}</strong>
                        <span>${escapeHtml(point.reportingModuleLabel || point.reportingModule || 'Report')} · ${escapeHtml(point.receivedAt || '')}</span>
                        <span>${escapeHtml(point.latitude)}, ${escapeHtml(point.longitude)}</span>
                    </a>
                `).join('');

                list.querySelectorAll('[data-report-id]').forEach(link => {
                    link.addEventListener('click', event => {
                        event.preventDefault();
                        state.selectedId = Number(link.dataset.reportId);
                        render();
                    });
                });
            }

            function renderSelected(point) {
                const target = document.getElementById('map-selected-report');
                if (!point) {
                    target.innerHTML = '<div class="map-empty">No report selected.</div>';
                    return;
                }

                target.innerHTML = `
                    <dl>
                        ${detailRow('Receipt', `#${point.id}`)}
                        ${detailRow('Post', point.borderPostCode || '-')}
                        ${detailRow('Digital address', point.borderPostDigitalAddress || '-')}
                        ${detailRow('Module', point.reportingModuleLabel || point.reportingModule || '-')}
                        ${detailRow('Form', `${point.formId || '-'} v${point.formVersion || '-'}`)}
                        ${detailRow('GPS', `${point.latitude}, ${point.longitude}`)}
                        ${detailRow('Accuracy', point.locationAccuracyMeters ? `${point.locationAccuracyMeters}m` : '-')}
                        ${detailRow('Device', point.deviceId || '-')}
                        ${detailRow('Received', point.receivedAt || '-')}
                    </dl>
                    <a class="tool-button primary" href="/admin/submissions/${point.id}">Open report</a>
                `;
            }

            function detailRow(label, value) {
                return `<div class="map-detail-row"><dt>${escapeHtml(label)}</dt><dd>${escapeHtml(value)}</dd></div>`;
            }

            function collectCoordinates(geojson) {
                const coords = [];
                walkGeoJson(geojson, pair => coords.push(pair));
                return coords;
            }

            function walkGeoJson(node, visitor) {
                if (!node) return;
                const type = node.type;
                if (type === 'FeatureCollection') (node.features || []).forEach(feature => walkGeoJson(feature, visitor));
                else if (type === 'Feature') walkGeoJson(node.geometry, visitor);
                else if (type === 'GeometryCollection') (node.geometries || []).forEach(geometry => walkGeoJson(geometry, visitor));
                else if (type === 'Polygon') node.coordinates.forEach(ring => ring.forEach(visitor));
                else if (type === 'MultiPolygon') node.coordinates.forEach(poly => poly.forEach(ring => ring.forEach(visitor)));
            }

            function geoJsonPaths(geojson, project) {
                const paths = [];
                function ringPath(ring) {
                    return ring.map((coord, index) => {
                        const [x, y] = project(coord);
                        return `${index === 0 ? 'M' : 'L'}${x.toFixed(2)},${y.toFixed(2)}`;
                    }).join(' ') + ' Z';
                }
                function walk(node) {
                    if (!node) return;
                    if (node.type === 'FeatureCollection') (node.features || []).forEach(walk);
                    else if (node.type === 'Feature') walk(node.geometry);
                    else if (node.type === 'GeometryCollection') (node.geometries || []).forEach(walk);
                    else if (node.type === 'Polygon') paths.push(node.coordinates.map(ringPath).join(' '));
                    else if (node.type === 'MultiPolygon') node.coordinates.forEach(poly => paths.push(poly.map(ringPath).join(' ')));
                }
                walk(geojson);
                return paths;
            }

            function computeBounds(coords) {
                return coords.reduce((bounds, [lon, lat]) => ({
                    minLon: Math.min(bounds.minLon, lon),
                    maxLon: Math.max(bounds.maxLon, lon),
                    minLat: Math.min(bounds.minLat, lat),
                    maxLat: Math.max(bounds.maxLat, lat),
                }), { minLon: Infinity, maxLon: -Infinity, minLat: Infinity, maxLat: -Infinity });
            }

            function clear(element) {
                while (element.firstChild) element.removeChild(element.firstChild);
            }

            function escapeHtml(value) {
                return String(value ?? '').replace(/[&<>"']/g, character => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;',
                }[character]));
            }
        })();
    </script>
@endsection

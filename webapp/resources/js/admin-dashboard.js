const dashboardRoot = document.querySelector('[data-dashboard]');

if (dashboardRoot) {
    const dataUrl = dashboardRoot.dataset.dataUrl;
    const saveViewUrl = dashboardRoot.dataset.saveViewUrl;
    const deleteViewUrl = dashboardRoot.dataset.deleteViewUrl;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const filterFields = safeJson(dashboardRoot.dataset.filterFields, []);
    const operatorLabels = safeJson(dashboardRoot.dataset.operatorLabels, {});
    const defaultLayout = ['map', 'timeline', 'breakdowns', 'quality', 'discover', 'devices', 'detail', 'reports', 'aggregates'];
    const fallbackFilterOptions = {
        statuses: [
            { value: 'accepted', label: 'Accepted' },
            { value: 'rejected', label: 'Rejected' },
            { value: 'failed', label: 'Failed' },
        ],
        modules: [
            { value: 'immigration', label: 'Immigration' },
            { value: 'customs', label: 'Customs' },
            { value: 'security', label: 'Security / Incident' },
            { value: 'health', label: 'Health / Quarantine' },
            { value: 'other', label: 'Other Border Service' },
        ],
    };
    const fieldByKey = new Map(filterFields.map(field => [field.key, field]));
    const state = {
        data: null,
        selectedId: null,
        timer: null,
        filters: [],
        currentViewId: null,
        dashboardViews: safeJson(dashboardRoot.dataset.dashboardViews, []),
        filterOptions: {},
    };
    const svg = document.getElementById('map-svg');

    bindDashboardControls();
    bindPanelDragging();
    renderSavedViews();

    const defaultView = state.dashboardViews.find(view => view.isDefault);
    if (defaultView) {
        applyView(defaultView, false);
    } else {
        renderFilterRows();
    }

    scheduleRefresh();
    loadDashboard();

    function bindDashboardControls() {
        document.getElementById('refresh_button')?.addEventListener('click', loadDashboard);
        document.getElementById('apply_filters_button')?.addEventListener('click', loadDashboard);
        document.getElementById('add_filter_button')?.addEventListener('click', () => {
            const firstField = filterFields[0];
            if (!firstField) {
                return;
            }

            state.filters.push({
                field: firstField.key,
                operator: firstField.operators[0],
                value: firstField.valueType === 'boolean' ? true : '',
            });
            renderFilterRows();
        });
        document.getElementById('clear_filters_button')?.addEventListener('click', () => {
            state.filters = [];
            renderFilterRows();
            loadDashboard();
        });
        document.getElementById('country_code')?.addEventListener('change', loadDashboard);
        document.getElementById('hours')?.addEventListener('change', loadDashboard);
        document.getElementById('discover_search')?.addEventListener('keydown', event => {
            if (event.key === 'Enter') {
                event.preventDefault();
                loadDashboard();
            }
        });
        document.getElementById('auto_refresh')?.addEventListener('change', scheduleRefresh);
        document.getElementById('save_view_button')?.addEventListener('click', saveCurrentView);
        document.getElementById('delete_view_button')?.addEventListener('click', deleteCurrentView);
        document.getElementById('dashboard_view')?.addEventListener('change', event => {
            const viewId = Number(event.target.value);
            const view = state.dashboardViews.find(candidate => candidate.id === viewId);
            if (view) {
                applyView(view);
                return;
            }

            state.currentViewId = null;
            document.getElementById('view_status').textContent = '';
        });
    }

    function bindPanelDragging() {
        document.querySelectorAll('.panel[draggable=true]').forEach(panel => {
            panel.addEventListener('dragstart', event => event.dataTransfer.setData('text/plain', panel.dataset.panel));
            panel.addEventListener('dragover', event => event.preventDefault());
            panel.addEventListener('drop', event => {
                event.preventDefault();
                const source = document.querySelector(`[data-panel="${event.dataTransfer.getData('text/plain')}"]`);
                if (source && source !== panel && source.parentNode === panel.parentNode) {
                    panel.parentNode.insertBefore(source, panel);
                }
            });
        });
    }

    function renderSavedViews() {
        const select = document.getElementById('dashboard_view');
        if (!select) {
            return;
        }

        select.innerHTML = '<option value="">Unsaved live view</option>';
        state.dashboardViews.forEach(view => {
            const option = document.createElement('option');
            option.value = view.id;
            option.textContent = `${view.name}${view.isDefault ? ' (default)' : ''}`;
            select.appendChild(option);
        });
        select.value = state.currentViewId || '';
    }

    function applyView(view, shouldLoad = true) {
        state.currentViewId = view.id;
        state.filters = normalizeClientFilters(view.filters || []);
        setValue('country_code', view.countryCode);
        setValue('hours', String(view.timeWindowHours || 24));
        setValue('view_name', view.name || '');
        document.getElementById('view_default').checked = Boolean(view.isDefault);
        applyLayout(view.layout || defaultLayout);
        renderSavedViews();
        renderFilterRows();
        document.getElementById('view_status').textContent = view.isDefault ? 'Default view loaded' : 'View loaded';

        if (shouldLoad) {
            loadDashboard();
        }
    }

    async function saveCurrentView() {
        const name = document.getElementById('view_name')?.value.trim();
        if (!name) {
            document.getElementById('view_status').textContent = 'Name required';
            return;
        }

        const payload = {
            id: state.currentViewId,
            name,
            country_code: document.getElementById('country_code').value,
            time_window_hours: Number(document.getElementById('hours').value),
            filters: collectFiltersFromRows(),
            layout: currentLayout(),
            is_default: document.getElementById('view_default').checked,
        };

        const response = await fetch(saveViewUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify(payload),
        });

        if (!response.ok) {
            document.getElementById('view_status').textContent = 'Save failed';
            return;
        }

        const body = await response.json();
        state.dashboardViews = body.views || [];
        state.currentViewId = body.view?.id || null;
        state.filters = body.view?.filters || payload.filters;
        renderSavedViews();
        renderFilterRows();
        document.getElementById('view_status').textContent = 'View saved';
        loadDashboard();
    }

    async function deleteCurrentView() {
        if (!state.currentViewId) {
            document.getElementById('view_status').textContent = 'No saved view selected';
            return;
        }

        const response = await fetch(deleteViewUrl.replace('__ID__', state.currentViewId), {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
        });

        if (!response.ok) {
            document.getElementById('view_status').textContent = 'Delete failed';
            return;
        }

        const body = await response.json();
        state.dashboardViews = body.views || [];
        state.currentViewId = null;
        document.getElementById('dashboard_view').value = '';
        document.getElementById('view_default').checked = false;
        document.getElementById('view_status').textContent = 'View deleted';
        renderSavedViews();
    }

    function renderFilterRows() {
        const builder = document.getElementById('filter-builder');
        if (!builder) {
            return;
        }

        clear(builder);

        if (!state.filters.length) {
            const empty = document.createElement('div');
            empty.className = 'map-empty';
            empty.textContent = 'No filters';
            builder.appendChild(empty);
            return;
        }

        state.filters.forEach((filter, index) => {
            const row = document.createElement('div');
            row.className = 'filter-row';
            row.dataset.index = index;

            const fieldWrap = fieldContainer('Field', fieldSelect(filter.field));
            const operatorWrap = fieldContainer('Operator', operatorSelect(filter.field, filter.operator));
            const valueWrap = fieldContainer('Value', valueControl(filter));
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'filter-remove';
            remove.textContent = 'Remove';
            remove.addEventListener('click', () => {
                state.filters = collectFiltersFromRows();
                state.filters.splice(index, 1);
                renderFilterRows();
            });

            row.append(fieldWrap, operatorWrap, valueWrap, remove);
            builder.appendChild(row);
        });
    }

    function fieldSelect(value) {
        const select = document.createElement('select');
        select.className = 'filter-field';
        filterFields.forEach(field => {
            const option = document.createElement('option');
            option.value = field.key;
            option.textContent = field.label;
            select.appendChild(option);
        });
        select.value = fieldByKey.has(value) ? value : filterFields[0]?.key;
        select.addEventListener('change', () => {
            state.filters = collectFiltersFromRows();
            const filter = state.filters[Number(select.closest('.filter-row').dataset.index)];
            const field = fieldByKey.get(select.value);
            filter.field = select.value;
            filter.operator = field?.operators[0] || 'equals';
            filter.value = field?.valueType === 'boolean' ? true : '';
            renderFilterRows();
        });

        return select;
    }

    function operatorSelect(fieldKey, value) {
        const field = fieldByKey.get(fieldKey) || filterFields[0];
        const select = document.createElement('select');
        select.className = 'filter-operator';
        (field?.operators || ['equals']).forEach(operator => {
            const option = document.createElement('option');
            option.value = operator;
            option.textContent = operatorLabels[operator] || operator;
            select.appendChild(option);
        });
        select.value = (field?.operators || []).includes(value) ? value : field?.operators[0];
        select.addEventListener('change', () => {
            state.filters = collectFiltersFromRows();
        });

        return select;
    }

    function valueControl(filter) {
        const field = fieldByKey.get(filter.field) || filterFields[0];
        const operatorNeedsValue = !['empty', 'not_empty'].includes(filter.operator);
        if (!operatorNeedsValue) {
            const input = document.createElement('input');
            input.className = 'filter-value';
            input.type = 'text';
            input.disabled = true;
            input.value = '';
            return input;
        }

        if (field?.valueType === 'boolean') {
            const select = document.createElement('select');
            select.className = 'filter-value';
            [['true', 'With GPS'], ['false', 'Missing GPS']].forEach(([value, label]) => {
                const option = document.createElement('option');
                option.value = value;
                option.textContent = label;
                select.appendChild(option);
            });
            select.value = String(Boolean(filter.value));
            select.addEventListener('change', () => {
                state.filters = collectFiltersFromRows();
            });

            return select;
        }

        if (field?.valueType === 'select') {
            const select = document.createElement('select');
            select.className = 'filter-value';
            const options = optionsForField(field);
            options.forEach(item => {
                const option = document.createElement('option');
                option.value = item.value;
                option.textContent = item.label;
                select.appendChild(option);
            });
            select.value = filter.value || options[0]?.value || '';
            select.addEventListener('change', () => {
                state.filters = collectFiltersFromRows();
            });

            return select;
        }

        const input = document.createElement('input');
        input.className = 'filter-value';
        input.type = 'text';
        input.value = filter.value ?? '';
        input.placeholder = field?.label || 'Value';
        input.addEventListener('input', () => {
            state.filters = collectFiltersFromRows();
        });

        return input;
    }

    function optionsForField(field) {
        return state.filterOptions[field.optionSource] || fallbackFilterOptions[field.optionSource] || [];
    }

    function fieldContainer(label, control) {
        const wrap = document.createElement('div');
        const labelElement = document.createElement('label');
        labelElement.textContent = label;
        wrap.append(labelElement, control);

        return wrap;
    }

    function collectFiltersFromRows() {
        const rows = Array.from(document.querySelectorAll('.filter-row'));

        return normalizeClientFilters(rows.map(row => ({
            field: row.querySelector('.filter-field')?.value,
            operator: row.querySelector('.filter-operator')?.value,
            value: row.querySelector('.filter-value')?.value,
        })));
    }

    function normalizeClientFilters(filters) {
        return filters.map(filter => {
            const field = fieldByKey.get(filter.field) || filterFields[0];
            const operator = (field?.operators || []).includes(filter.operator) ? filter.operator : field?.operators[0];
            let value = filter.value;

            if (field?.valueType === 'boolean') {
                value = value === true || value === 'true' || value === '1';
            } else {
                value = String(value ?? '').trim();
            }

            return { field: field.key, operator, value };
        }).filter(filter => {
            const field = fieldByKey.get(filter.field);
            return field?.valueType === 'boolean' || ['empty', 'not_empty'].includes(filter.operator) || filter.value !== '';
        });
    }

    async function loadDashboard() {
        try {
            state.filters = collectFiltersFromRows();
            const params = new URLSearchParams({
                country_code: document.getElementById('country_code').value,
                hours: document.getElementById('hours').value,
                q: document.getElementById('discover_search')?.value.trim() || '',
                filters: JSON.stringify(state.filters),
            });
            const response = await fetch(`${dataUrl}?${params.toString()}`, {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                throw new Error(`Dashboard request failed with status ${response.status}`);
            }

            state.data = await response.json();
            state.filterOptions = state.data.filterOptions || {};
            renderDashboard();
        } catch (error) {
            document.getElementById('last-updated').textContent = 'Dashboard data unavailable';
            document.getElementById('report-list').innerHTML = '<div class="map-empty">Unable to load live reports.</div>';
            console.error(error);
        }
    }

    function scheduleRefresh() {
        if (state.timer) {
            clearInterval(state.timer);
        }

        if (document.getElementById('auto_refresh')?.checked) {
            state.timer = setInterval(loadDashboard, 10000);
        }
    }

    function renderDashboard() {
        const data = state.data;
        document.getElementById('last-updated').textContent = `Updated ${new Date(data.generatedAt).toLocaleString()}`;
        document.getElementById('map-subtitle').textContent = data.country?.hasBoundary
            ? `${data.country.name} boundary with ${data.points.length} plotted report(s).`
            : 'No boundary uploaded. Points still plot from GPS when available.';

        document.getElementById('kpi-total').textContent = data.metrics.total;
        document.getElementById('kpi-gps-rate').textContent = `${data.metrics.gpsCoveragePercent}%`;
        document.getElementById('kpi-with-location').textContent = `${data.metrics.withLocation} located / ${data.metrics.withoutLocation} missing`;
        document.getElementById('kpi-today').textContent = data.metrics.today;
        document.getElementById('kpi-last-hour').textContent = data.metrics.lastHour;
        document.getElementById('kpi-devices').textContent = data.metrics.uniqueDevices;
        document.getElementById('kpi-rejected').textContent = data.metrics.rejected;

        const reports = data.latestReports || data.points || [];
        renderMap(data);
        renderAnalysis(data.analysis || {});
        renderAggregates(data.aggregates);
        renderReportList(reports);
        renderDiscoverTable(reports);
        renderSelected(reports.find(point => point.id === state.selectedId) || reports[0] || null);
    }

    function renderMap(data) {
        clear(svg);
        const coords = collectCoordinates(data.boundary).concat(data.points.map(point => [point.longitude, point.latitude]));
        if (!coords.length) {
            svg.innerHTML = '<text x="500" y="310" text-anchor="middle" fill="#64748b">No boundary or GPS reports yet</text>';
            return;
        }

        const bounds = boundsOf(coords);
        const project = ([lon, lat]) => {
            const pad = 34;
            const width = 1000 - pad * 2;
            const height = 620 - pad * 2;
            const x = pad + ((lon - bounds.minLon) / Math.max(bounds.maxLon - bounds.minLon, 0.000001)) * width;
            const y = pad + (1 - ((lat - bounds.minLat) / Math.max(bounds.maxLat - bounds.minLat, 0.000001))) * height;
            return [x, y];
        };

        geoJsonPaths(data.boundary, project).forEach(pathD => {
            const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            path.setAttribute('d', pathD);
            path.setAttribute('class', 'map-boundary');
            svg.appendChild(path);
        });

        data.points.forEach(point => {
            if (point.longitude === null || point.latitude === null) {
                return;
            }

            const [x, y] = project([point.longitude, point.latitude]);
            const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
            circle.setAttribute('cx', x);
            circle.setAttribute('cy', y);
            circle.setAttribute('r', point.id === state.selectedId ? 8 : 5);
            circle.setAttribute('class', `map-point ${point.status || 'accepted'}`);
            circle.addEventListener('click', () => {
                state.selectedId = point.id;
                renderDashboard();
            });
            svg.appendChild(circle);
        });
    }

    function renderAggregates(aggregates) {
        renderAggregate('agg-module', aggregates?.byModule || []);
        renderAggregate('agg-border', aggregates?.byBorderPost || []);
        renderAggregate('agg-form', aggregates?.byForm || []);
        renderAggregate('agg-region', aggregates?.byRegion || []);
    }

    function renderAnalysis(analysis) {
        renderTimeline(analysis.timeline || []);
        renderAggregate('analysis-status', analysis.statusBreakdown || []);
        renderAggregate('analysis-module', analysis.modules || []);
        renderAggregate('analysis-movement', analysis.movementTypes || []);
        renderAggregate('analysis-decision', analysis.decisions || []);
        renderAggregate('analysis-document', analysis.documentTypes || []);
        renderAggregate('analysis-nationality', analysis.nationalities || []);
        renderAggregate('analysis-form-version', analysis.formVersions || []);
        renderAggregate('analysis-sync-latency', analysis.syncLatency || []);
        renderAggregate('analysis-data-quality', analysis.dataQuality || []);
        renderAggregate('analysis-devices', analysis.topDevices || []);
        renderAggregate('analysis-gps', analysis.gpsQuality || []);
    }

    function renderTimeline(rows) {
        const container = document.getElementById('timeline-chart');
        if (!container) {
            return;
        }

        const max = Math.max(...rows.map(row => row.total), 1);
        container.innerHTML = rows.length ? rows.map((row, index) => {
            const height = Math.max(3, row.total / max * 100);
            const showLabel = index === 0 || index === rows.length - 1 || index % Math.max(1, Math.ceil(rows.length / 6)) === 0;

            return `
                <div class="timeline-bar" title="${escapeHtml(row.label)}: ${row.total}">
                    <span style="height:${height}%"></span>
                    ${showLabel ? `<strong>${escapeHtml(row.label)}</strong>` : ''}
                </div>
            `;
        }).join('') : '<div class="map-empty">No timeline data</div>';
    }

    function renderAggregate(id, rows) {
        const container = document.getElementById(id);
        if (!container) {
            return;
        }

        rows = rows || [];
        const max = Math.max(...rows.map(row => row.total), 1);
        container.innerHTML = rows.length ? rows.map(row => `
            <div class="aggregate-row">
                <span>${escapeHtml(row.label)}</span>
                <strong>${row.total}</strong>
                <div class="bar"><span style="width:${Math.max(6, row.total / max * 100)}%"></span></div>
            </div>
        `).join('') : '<div class="map-empty">No data</div>';
    }

    function renderReportList(points) {
        document.getElementById('report-list').innerHTML = points.length ? points.map(point => `
            <div class="report-item ${point.id === state.selectedId ? 'selected' : ''}" data-id="${point.id}">
                <div><span class="status-dot ${point.status}"></span><strong>${escapeHtml(point.travellerName || point.formId)}</strong></div>
                <div class="report-meta">${escapeHtml(point.reportingModuleLabel || 'Border')} / ${escapeHtml(point.borderPostDigitalAddress || point.borderPostCode || 'Unknown post')} / ${escapeHtml(point.documentNumber || 'No document')} / ${new Date(point.receivedAt).toLocaleString()}</div>
            </div>
        `).join('') : '<div class="map-empty">No reports in this window.</div>';

        document.querySelectorAll('.report-item').forEach(item => {
            item.addEventListener('click', () => {
                state.selectedId = Number(item.dataset.id);
                renderDashboard();
            });
        });
    }

    function renderDiscoverTable(points) {
        const container = document.getElementById('discover-table');
        if (!container) {
            return;
        }

        if (!points.length) {
            container.innerHTML = '<div class="map-empty">No records match this search window.</div>';
            return;
        }

        container.innerHTML = `
            <div class="discover-table-wrap">
                <table class="discover-table">
                    <thead>
                        <tr>
                            <th>Received</th>
                            <th>Status</th>
                            <th>Module</th>
                            <th>Post</th>
                            <th>Digital Address</th>
                            <th>Traveller</th>
                            <th>Document</th>
                            <th>Device</th>
                            <th>Receipt</th>
                            <th>Delay</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${points.map(point => `
                            <tr data-id="${point.id}">
                                <td>${point.receivedAt ? escapeHtml(new Date(point.receivedAt).toLocaleString()) : '-'}</td>
                                <td><span class="status-dot ${escapeHtml(point.status || '')}"></span>${escapeHtml(point.status || '-')}</td>
                                <td>${escapeHtml(point.reportingModuleLabel || point.reportingModule || '-')}</td>
                                <td>${escapeHtml(point.borderPostCode || '-')}</td>
                                <td>${escapeHtml(point.borderPostDigitalAddress || '-')}</td>
                                <td class="wrap">${escapeHtml(point.travellerName || '-')}</td>
                                <td>${escapeHtml(point.documentNumber || '-')}</td>
                                <td class="receipt">${escapeHtml(point.deviceId || '-')}</td>
                                <td class="receipt">${escapeHtml(point.serverId || point.localId || '-')}</td>
                                <td>${formatDelay(point.syncDelayMinutes)}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;

        container.querySelectorAll('tr[data-id]').forEach(row => {
            row.addEventListener('click', () => {
                state.selectedId = Number(row.dataset.id);
                renderDashboard();
            });
        });
    }

    function renderSelected(point) {
        const container = document.getElementById('selected-report');
        if (!point) {
            container.innerHTML = 'Click a point or report row.';
            return;
        }

        state.selectedId = point.id;
        const lonLat = point.longitude !== null && point.latitude !== null ? `${point.longitude}, ${point.latitude}` : '-';
        const receivedAt = point.receivedAt ? new Date(point.receivedAt).toLocaleString() : '-';
        container.innerHTML = `
            <div class="detail-grid">
                <div><label>Report</label><div class="mono">#${point.id}</div></div>
                <div><label>Status</label><div class="mono">${escapeHtml(point.status)}</div></div>
                <div><label>Module</label><div class="mono">${escapeHtml(point.reportingModuleLabel || point.reportingModule || '-')}</div></div>
                <div><label>Receipt</label><div class="mono">${escapeHtml(point.serverId || '-')}</div></div>
                <div><label>Border Post</label><div class="mono">${escapeHtml(point.borderPostCode || '-')}</div></div>
                <div><label>Digital Address</label><div class="mono">${escapeHtml(point.borderPostDigitalAddress || '-')}</div></div>
                <div><label>Movement</label><div class="mono">${escapeHtml(point.movementType || '-')}</div></div>
                <div><label>Lon/Lat</label><div class="mono">${lonLat}</div></div>
                <div><label>Accuracy</label><div class="mono">${point.accuracyMeters ?? '-'} m</div></div>
                <div><label>Document</label><div class="mono">${escapeHtml(point.documentNumber || '-')}</div></div>
                <div><label>Sync Delay</label><div class="mono">${formatDelay(point.syncDelayMinutes)}</div></div>
                <div><label>Received</label><div class="mono">${receivedAt}</div></div>
                ${point.rejectionReason ? `<div><label>Review Reason</label><div class="mono">${escapeHtml(point.rejectionReason)}</div></div>` : ''}
            </div>
        `;
    }

    function currentLayout() {
        const stackPanels = Array.from(document.querySelectorAll('.panel-stack .panel')).map(panel => panel.dataset.panel);
        return ['map', ...stackPanels.filter(panel => panel !== 'map')];
    }

    function applyLayout(layout) {
        const stack = document.querySelector('.panel-stack');
        if (!stack) {
            return;
        }

        [...layout, ...defaultLayout].forEach(panelName => {
            if (panelName === 'map') {
                return;
            }

            const panel = stack.querySelector(`[data-panel="${panelName}"]`);
            if (panel) {
                stack.appendChild(panel);
            }
        });
    }

    function collectCoordinates(geojson) {
        const coords = [];
        if (!geojson) {
            return coords;
        }

        walkGeoJson(geojson, ring => ring.forEach(point => coords.push(point)));
        return coords;
    }

    function geoJsonPaths(geojson, project) {
        const paths = [];
        if (!geojson) {
            return paths;
        }

        walkGeoJson(geojson, ring => {
            if (!ring.length) {
                return;
            }

            const projected = ring.map(project);
            paths.push(projected.map(([x, y], index) => `${index === 0 ? 'M' : 'L'}${x.toFixed(2)},${y.toFixed(2)}`).join(' ') + ' Z');
        });
        return paths;
    }

    function walkGeoJson(node, onRing) {
        if (!node) {
            return;
        }

        if (node.type === 'FeatureCollection') {
            return (node.features || []).forEach(feature => walkGeoJson(feature, onRing));
        }

        if (node.type === 'Feature') {
            return walkGeoJson(node.geometry, onRing);
        }

        if (node.type === 'Polygon') {
            return (node.coordinates || []).forEach(onRing);
        }

        if (node.type === 'MultiPolygon') {
            return (node.coordinates || []).forEach(polygon => polygon.forEach(onRing));
        }
    }

    function boundsOf(coords) {
        return coords.reduce((bounds, [lon, lat]) => ({
            minLon: Math.min(bounds.minLon, lon),
            maxLon: Math.max(bounds.maxLon, lon),
            minLat: Math.min(bounds.minLat, lat),
            maxLat: Math.max(bounds.maxLat, lat),
        }), { minLon: Infinity, maxLon: -Infinity, minLat: Infinity, maxLat: -Infinity });
    }

    function safeJson(value, fallback) {
        try {
            return JSON.parse(value || '');
        } catch {
            return fallback;
        }
    }

    function setValue(id, value) {
        const element = document.getElementById(id);
        if (element) {
            element.value = value;
        }
    }

    function clear(node) {
        while (node.firstChild) {
            node.removeChild(node.firstChild);
        }
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, char => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;',
        }[char]));
    }

    function formatDelay(minutes) {
        if (minutes === null || minutes === undefined) {
            return '-';
        }

        if (minutes < 60) {
            return `${minutes} min`;
        }

        if (minutes < 1440) {
            return `${Math.floor(minutes / 60)}h ${minutes % 60}m`;
        }

        return `${Math.floor(minutes / 1440)}d ${Math.floor((minutes % 1440) / 60)}h`;
    }
}

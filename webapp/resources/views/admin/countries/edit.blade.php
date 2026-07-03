@extends('layouts.admin')

@section('content')
    <style>
        .upload-progress{display:none;margin-top:12px;border:1px solid #bbf7d0;background:#f0fdf4;color:#14532d;padding:12px}.upload-progress.active{display:block}.upload-progress strong{display:flex;align-items:center;gap:8px;margin-bottom:8px}.spinner{width:16px;height:16px;border:2px solid #bbf7d0;border-top-color:#15803d;border-radius:999px;animation:spin .8s linear infinite}.upload-bar{height:8px;overflow:hidden;border-radius:999px;background:#d1fae5}.upload-bar span{display:block;width:42%;height:100%;border-radius:999px;background:#15803d;animation:progress-slide 1.1s ease-in-out infinite}button.is-loading{cursor:wait;opacity:.8}@keyframes spin{to{transform:rotate(360deg)}}@keyframes progress-slide{0%{transform:translateX(-110%)}100%{transform:translateX(250%)}}
    </style>

    <div class="header">
        <div>
            <h1 class="title">Configure {{ $country->name }}</h1>
            <p class="subtitle">Configure the tenant, mobile branding, map boundary, and deployment model for this country.</p>
        </div>
        <div class="actions">
            <a class="button light" href="{{ route('admin.map.index', ['country_code' => $country->code]) }}">View Map</a>
            <a class="button light" href="{{ route('admin.users.index', ['country_code' => $country->code]) }}">Officer Setup QR</a>
            <a class="button light" href="{{ route('admin.countries.index') }}">Back</a>
        </div>
    </div>

    <div class="summary-grid" style="margin-bottom:18px;">
        <div class="summary-card">
            <span>Android branding</span>
            <strong>{{ $country->app_title ?: $country->name.' Border Reporting' }}</strong>
            <small>App title, subtitle, splash/loading logo, login logo, and top logo.</small>
        </div>
        <div class="summary-card">
            <span>Map boundary</span>
            <strong>{{ $country->boundary_geojson_path ? 'Uploaded' : 'Missing' }}</strong>
            <small>{{ $country->boundary_source_name ?: 'Upload GeoJSON or a zipped polygon shapefile.' }}</small>
        </div>
        <div class="summary-card">
            <span>Mobile setup</span>
            <strong>Officer QR</strong>
            <small>Generate per-user setup QR codes from Users after branding is saved.</small>
        </div>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('admin.countries.update', $country) }}" enctype="multipart/form-data" id="country_config_form">
            @csrf
            <div class="grid">
                <div>
                    <label>Country Code</label>
                    <input type="text" value="{{ $country->code }}" readonly>
                </div>
                <div>
                    <label for="name">Country Name</label>
                    <input id="name" name="name" type="text" value="{{ old('name', $country->name) }}" required>
                </div>
                <div>
                    <label for="tenant_slug">Tenant Slug</label>
                    <input id="tenant_slug" name="tenant_slug" type="text" value="{{ old('tenant_slug', $country->tenant_slug ?: str($country->name)->slug()) }}" required>
                    <div class="field-help">Stable tenant identifier used for domains, support, and deployment operations.</div>
                </div>
                <div>
                    <label for="tenant_status">Tenant Status</label>
                    <select id="tenant_status" name="tenant_status" required>
                        @foreach(\App\Models\Country::tenantStatusLabels() as $status => $label)
                            <option value="{{ $status }}" @selected(old('tenant_status', $country->tenant_status ?: \App\Models\Country::TENANT_STATUS_IMPLEMENTATION) === $status)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="deployment_plan">Deployment Plan</label>
                    <select id="deployment_plan" name="deployment_plan" required>
                        @foreach(\App\Models\Country::deploymentPlanLabels() as $plan => $label)
                            <option value="{{ $plan }}" @selected(old('deployment_plan', $country->deployment_plan ?: \App\Models\Country::PLAN_PROGRAM) === $plan)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="deployment_type">Deployment Type</label>
                    <select id="deployment_type" name="deployment_type" required>
                        @foreach(\App\Models\Country::deploymentTypeLabels() as $type => $label)
                            <option value="{{ $type }}" @selected(old('deployment_type', $country->deployment_type ?: \App\Models\Country::DEPLOYMENT_HOSTED) === $type)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="support_tier">Support Tier</label>
                    <input id="support_tier" name="support_tier" type="text" value="{{ old('support_tier', $country->support_tier ?: 'standard') }}" required>
                </div>
                <div>
                    <label for="data_region">Data Region</label>
                    <input id="data_region" name="data_region" type="text" value="{{ old('data_region', $country->data_region) }}" placeholder="West Africa, EU, national data center">
                </div>
                <div>
                    <label for="primary_domain">Primary Domain</label>
                    <input id="primary_domain" name="primary_domain" type="text" value="{{ old('primary_domain', $country->primary_domain) }}" placeholder="borderreach.example.gov">
                </div>
                <div>
                    <label for="immigration_agency">Immigration Agency</label>
                    <input id="immigration_agency" name="immigration_agency" type="text" value="{{ old('immigration_agency', $country->immigration_agency) }}">
                </div>
                <div>
                    <label for="timezone">Timezone</label>
                    <input id="timezone" name="timezone" type="text" value="{{ old('timezone', $country->timezone) }}" required>
                </div>
                <div style="grid-column:1/-1;">
                    <h2 class="panel-title">Android App Branding</h2>
                    <p class="panel-subtitle">These values are returned by mobile login/config sync. The Android app uses the logo on splash/loading, login, and the top header.</p>
                </div>
                <div>
                    <label for="app_title">Mobile App Title</label>
                    <input id="app_title" name="app_title" type="text" value="{{ old('app_title', $country->app_title ?: $country->name.' Border Reporting') }}" maxlength="80" required>
                    <div class="field-help">Shown on the Android login, home, and form screens after sync/login.</div>
                </div>
                <div>
                    <label for="app_subtitle">Mobile App Subtitle</label>
                    <input id="app_subtitle" name="app_subtitle" type="text" value="{{ old('app_subtitle', $country->app_subtitle ?: $country->immigration_agency) }}" maxlength="120">
                </div>
                <div>
                    <label for="logo">Splash / Login / Header Logo</label>
                    <input id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp">
                    <div class="field-help">PNG, JPG, or WebP. This is cached offline by Android after login/config sync and used on loading, login, and top navigation.</div>
                </div>
                <div>
                    <label>Current Logo</label>
                    @if($country->logo_path)
                        <div style="display:flex;align-items:center;gap:12px;">
                            <img src="{{ asset('storage/'.$country->logo_path) }}" alt="{{ $country->name }} logo" style="width:64px;height:64px;object-fit:contain;border:1px solid #dce3ee;border-radius:8px;background:#fff;">
                            <div class="mono">{{ $country->logo_path }}</div>
                        </div>
                    @else
                        <div class="mono">Using default BorderReach logo</div>
                    @endif
                </div>

                <div style="grid-column:1/-1;">
                    <h2 class="panel-title">Operational Map Boundary</h2>
                    <p class="panel-subtitle">Upload a country boundary once. GPS reports submitted by Android are plotted inside this boundary on the map page.</p>
                </div>
                <div>
                    <label for="boundary_file">Country Boundary</label>
                    <input id="boundary_file" name="boundary_file" type="file" accept=".geojson,.json,.zip,.shp,application/geo+json,application/json,application/zip">
                    <div class="field-help">Upload GeoJSON or a zipped polygon shapefile. This powers the live reports map.</div>
                    @error('boundary_file')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>
                <div>
                    <label>Current Boundary</label>
                    @if($country->boundary_geojson_path)
                        <div class="mono">{{ $country->boundary_source_name ?: $country->boundary_geojson_path }}{{ $country->boundary_imported_at ? "\nImported ".$country->boundary_imported_at : '' }}</div>
                    @else
                        <div class="mono">No map boundary uploaded</div>
                    @endif
                </div>
                <div>
                    <label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $country->is_active))> Active deployment profile</label>
                </div>
            </div>
            <div class="upload-progress" id="country_boundary_upload_progress" role="status" aria-live="polite">
                <strong><span class="spinner" aria-hidden="true"></span>Your country shapefile is uploading and processing.</strong>
                <div class="upload-bar" aria-hidden="true"><span></span></div>
                <div class="field-help">Please keep this page open. BorderReach is reading the boundary layer and updating the operational map.</div>
            </div>
            <p style="margin-top: 16px;"><button type="submit" data-saving-text="Saving..." data-boundary-loading-text="Uploading boundary...">Save Country Tenant</button></p>
        </form>
    </div>

    <script nonce="{{ request()->attributes->get('csp_nonce') }}">
        (() => {
            const form = document.getElementById('country_config_form');
            if (!form) return;

            const boundaryInput = document.getElementById('boundary_file');
            const progress = document.getElementById('country_boundary_upload_progress');

            form.addEventListener('submit', event => {
                if (form.dataset.submitting === 'true') {
                    event.preventDefault();
                    return;
                }

                form.dataset.submitting = 'true';
                const hasBoundaryFile = Boolean(boundaryInput?.files?.length);

                if (hasBoundaryFile) {
                    progress?.classList.add('active');
                }

                form.querySelectorAll('button[type="submit"]').forEach(button => {
                    button.classList.add('is-loading');
                    button.disabled = true;
                    button.textContent = hasBoundaryFile
                        ? button.dataset.boundaryLoadingText || 'Uploading boundary...'
                        : button.dataset.savingText || 'Saving...';
                });
            });
        })();
    </script>
@endsection

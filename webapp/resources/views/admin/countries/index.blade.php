@extends('layouts.admin')

@section('content')
    <div class="header">
        <div>
            <h1 class="title">Country Profiles</h1>
            <p class="subtitle">Each country profile is a tenant: branding, forms, posts, users, maps, and submissions stay scoped to that country.</p>
        </div>
    </div>

    <div class="card">
        <table>
            <thead>
            <tr>
                <th>Code</th>
                <th>Country</th>
                <th>Tenant</th>
                <th>Plan</th>
                <th>Deployment</th>
                <th>App Title</th>
                <th>Map Boundary</th>
                <th>Mobile Setup</th>
                <th>Agency</th>
                <th>Status</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @foreach($countries as $country)
                <tr>
                    <td class="mono">{{ $country->code }}</td>
                    <td>{{ $country->name }}</td>
                    <td>
                        <div class="mono">{{ $country->tenant_slug ?: '-' }}</div>
                        <div class="field-help">{{ $country->tenantStatusLabel() }}</div>
                    </td>
                    <td>{{ $country->deploymentPlanLabel() }}</td>
                    <td>{{ $country->deploymentTypeLabel() }}</td>
                    <td>{{ $country->app_title ?: $country->name.' Border Reporting' }}</td>
                    <td>
                        @if($country->boundary_geojson_path)
                            <span class="tag published">Uploaded</span>
                            <div class="field-help">{{ $country->boundary_source_name ?: 'Boundary file' }}</div>
                        @else
                            <span class="tag draft">Missing</span>
                            <div class="field-help">Upload GeoJSON or shapefile</div>
                        @endif
                    </td>
                    <td>
                        <div class="field-help">Logo, app name, splash/loading branding</div>
                        <a class="button light" href="{{ route('admin.users.index', ['country_code' => $country->code]) }}">Officer QR</a>
                    </td>
                    <td>{{ $country->immigration_agency }}</td>
                    <td><span class="tag {{ $country->is_active ? 'published' : 'draft' }}">{{ $country->is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td><a class="button light" href="{{ route('admin.countries.edit', $country) }}">Configure</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endsection

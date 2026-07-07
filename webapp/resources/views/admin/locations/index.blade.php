@extends('layouts.admin')

@section('content')
    <div class="header">
        <div>
            <h1 class="title">Frequent Locations</h1>
            <p class="subtitle">Upload common from/to places by country so officers can select them in field reports.</p>
        </div>
    </div>

    @if(session('location_import_errors'))
        <div class="error">
            @foreach(session('location_import_errors') as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="card">
        <h2 class="title" style="font-size: 20px;">Upload Location List</h2>
        <p class="subtitle">Accepted formats: CSV, XLS, XLSX. Required columns: country and name. Optional columns: code, district, admin_area, category, aliases, sort_order.</p>
        <form method="POST" action="{{ route('admin.locations.store') }}" enctype="multipart/form-data" style="margin-top: 16px;">
            @csrf
            <div class="grid">
                <div>
                    <label for="file">Location file</label>
                    <input id="file" type="file" name="file" required>
                    <div class="field-help">Country can be SLE, Sierra Leone, GIN, Guinea, Guinea Conakry, LBR, or Liberia. District ties choices to the officer's assigned border post. Use stable codes so old drafts still sync after label text changes.</div>
                </div>
                <div>
                    <label>Sample CSV columns</label>
                    <div class="mono">code,country,name,district,admin_area,category,aliases,sort_order
SLE-GBM-GBALAMUYA,SLE,Gbalamuya,Kambia,Kambia Guinea corridor,border post,,10
GIN-GBM-PAMELAP,GIN,Pamelap,Kambia,Kambia Guinea corridor,border town,,20
LBR-JDM-BO-WATERSIDE,LBR,Bo Waterside,Pujehun,Jendema Liberia corridor,border town,,30</div>
                </div>
            </div>
            <div class="actions" style="margin-top: 16px;">
                <button type="submit">Upload Locations</button>
            </div>
        </form>
    </div>

    <div class="card">
        <form method="GET" action="{{ route('admin.locations.index') }}" class="grid">
            <div>
                <label for="country_code">Country</label>
                <select id="country_code" name="country_code">
                    <option value="">All countries</option>
                    @foreach($countries as $country)
                        <option value="{{ $country->code }}" @selected(($filters['country_code'] ?? '') === $country->code)>
                            {{ $country->name }} ({{ $country->code }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div style="align-self:end;" class="actions">
                <button type="submit">Apply</button>
                <a class="button light" href="{{ route('admin.locations.index') }}">Clear</a>
            </div>
        </form>
    </div>

    <div class="grid">
        @foreach($countries as $country)
            <div class="card">
                <label>{{ $country->name }} active locations</label>
                <div class="mono">{{ $counts[$country->code] ?? 0 }}</div>
            </div>
        @endforeach
        <div class="card">
            <label>Mobile usage</label>
            <div class="mono">Use option source locations:all or locations:COUNTRY_CODE in the form builder. From Location and To Location share this catalog and are filtered by the officer's border-post district.</div>
        </div>
    </div>

    <div class="card">
        <h2 class="title" style="font-size: 20px;">Current Catalog</h2>
        <table>
            <thead>
            <tr>
                <th>Country</th>
                <th>Location</th>
                <th>District</th>
                <th>Admin Area</th>
                <th>Category</th>
                <th>Status</th>
            </tr>
            </thead>
            <tbody>
            @forelse($locations as $location)
                <tr>
                    <td class="mono">{{ $location->country_code }}</td>
                    <td>{{ $location->name }}</td>
                    <td>{{ $location->district }}</td>
                    <td>{{ $location->admin_area }}</td>
                    <td>{{ $location->category }}</td>
                    <td>{{ $location->is_active ? 'Active' : 'Inactive' }}</td>
                </tr>
            @empty
                <tr><td colspan="6">No frequent locations have been uploaded yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{ $locations->links() }}
@endsection

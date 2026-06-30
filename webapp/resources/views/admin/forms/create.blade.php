@extends('layouts.admin')

@section('content')
    <div class="header">
        <div>
            <h1 class="title">Upload XLSForm</h1>
            <p class="subtitle">Upload a Kobo-compatible XLSForm to compile a new runtime version.</p>
        </div>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('admin.forms.store') }}" enctype="multipart/form-data">
            @csrf
            <div style="margin-bottom: 16px;">
                <label for="country_code">Country Profile</label>
                <select id="country_code" name="country_code" required>
                    @foreach($countries as $country)
                        <option value="{{ $country->code }}" @selected(old('country_code', 'SLE') === $country->code)>{{ $country->name }} ({{ $country->code }})</option>
                    @endforeach
                </select>
            </div>
            <div style="margin-bottom: 16px;">
                <label for="reporting_module">Reporting Module</label>
                <select id="reporting_module" name="reporting_module" required>
                    @foreach($moduleLabels as $module => $label)
                        <option value="{{ $module }}" @selected(old('reporting_module', 'immigration') === $module)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div style="margin-bottom: 16px;">
                <label for="standard_reference">Standards Baseline</label>
                <input id="standard_reference" name="standard_reference" type="text" value="{{ old('standard_reference', $defaultStandardReference) }}" maxlength="255">
            </div>
            <div style="margin-bottom: 16px;">
                <label for="title">Display Title</label>
                <input id="title" name="title" type="text" value="{{ old('title') }}" maxlength="120">
            </div>
            <div style="margin-bottom: 16px;">
                <label for="file">XLSForm File</label>
                <input id="file" name="file" type="file" accept=".xlsx,.xls" required>
            </div>
            <div style="margin-bottom: 20px;">
                <label><input type="checkbox" name="publish" value="1" {{ old('publish') ? 'checked' : '' }}> Publish this version after import</label>
            </div>
            <button type="submit">Import</button>
        </form>
    </div>
@endsection

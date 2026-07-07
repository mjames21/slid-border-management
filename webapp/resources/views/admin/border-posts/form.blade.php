@extends('layouts.admin')

@section('content')
    <div class="header">
        <div>
            <h1 class="title">{{ $borderPost->exists ? 'Edit Border Post' : 'Create Border Post' }}</h1>
            <p class="subtitle">Coordinates are sent to Android with the officer's border assignment.</p>
        </div>
        <a class="button light" href="{{ route('admin.border-posts.index') }}">Back</a>
    </div>

    <div class="card">
        <form method="POST" action="{{ $borderPost->exists ? route('admin.border-posts.update', $borderPost) : route('admin.border-posts.store') }}">
            @csrf
            <div class="grid">
                <div>
                    <label for="country_code">Country</label>
                    <select id="country_code" name="country_code" required>
                        @foreach($countries as $country)
                            <option value="{{ $country->code }}" @selected(old('country_code', $borderPost->country_code ?: 'SLE') === $country->code)>
                                {{ $country->code }} / {{ $country->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="code">Border Post Code</label>
                    <input id="code" name="code" type="text" value="{{ old('code', $borderPost->code) }}" required>
                    <div class="field-help">Example: FAL_FALABA or BOW_LBR.</div>
                </div>
                <div>
                    <label for="digital_address">Digital Address</label>
                    <input id="digital_address" name="digital_address" type="text" value="{{ old('digital_address', $borderPost->digital_address) }}" placeholder="SLE-BP-BEN-LND">
                    <div class="field-help">Stable digital address for reports, dashboard search, and mobile assignment. Leave blank to generate from country and post code.</div>
                </div>
                <div>
                    <label for="name">Name</label>
                    <input id="name" name="name" type="text" value="{{ old('name', $borderPost->name) }}" required>
                </div>
                <div>
                    <label for="region">Region</label>
                    <select id="region" name="region">
                        <option value="">Select region / district</option>
                        @foreach($regionOptions as $group => $options)
                            <optgroup label="{{ $group }}">
                                @foreach($options as $option)
                                    <option value="{{ $option }}" @selected(old('region', $borderPost->region) === $option)>{{ $option }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="longitude">Longitude</label>
                    <input id="longitude" name="longitude" type="text" value="{{ old('longitude', $borderPost->longitude) }}" placeholder="-11.1234567">
                </div>
                <div>
                    <label for="latitude">Latitude</label>
                    <input id="latitude" name="latitude" type="text" value="{{ old('latitude', $borderPost->latitude) }}" placeholder="9.1234567">
                </div>
                <div>
                    <label for="allowed_radius_meters">Allowed Radius Meters</label>
                    <input id="allowed_radius_meters" name="allowed_radius_meters" type="text" value="{{ old('allowed_radius_meters', $borderPost->allowed_radius_meters) }}">
                    <div class="field-help">Optional. Useful later for location QA/geofence warnings.</div>
                </div>
                <div>
                    <label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $borderPost->is_active))> Active border post</label>
                </div>
            </div>
            <p style="margin-top:16px;"><button type="submit">Save Border Post</button></p>
        </form>
    </div>
@endsection

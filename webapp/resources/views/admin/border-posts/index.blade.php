@extends('layouts.admin')

@section('content')
    <div class="header">
        <div>
            <h1 class="title">Border Posts</h1>
            <p class="subtitle">Maintain officer assignment points, coordinates, and optional location radius.</p>
        </div>
        <a class="button" href="{{ route('admin.border-posts.create') }}">Create Border Post</a>
    </div>

    <div class="card">
        <form method="GET" action="{{ route('admin.border-posts.index') }}" class="grid">
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
                <a class="button light" href="{{ route('admin.border-posts.index') }}">Clear</a>
            </div>
        </form>
    </div>

    <div class="card">
        <table>
            <thead>
            <tr>
                <th>Code</th>
                <th>Digital Address</th>
                <th>Country</th>
                <th>Name</th>
                <th>Region</th>
                <th>Lon/Lat</th>
                <th>Radius</th>
                <th>Status</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse($borderPosts as $post)
                <tr>
                    <td class="mono">{{ $post->code }}</td>
                    <td class="mono">{{ $post->digital_address ?: '-' }}</td>
                    <td>{{ $post->country?->name ?? $post->country_code }}</td>
                    <td>{{ $post->name }}</td>
                    <td>{{ $post->region ?: '-' }}</td>
                    <td class="mono">
                        @if($post->longitude !== null && $post->latitude !== null)
                            {{ $post->longitude }}, {{ $post->latitude }}
                        @else
                            Not set
                        @endif
                    </td>
                    <td>{{ $post->allowed_radius_meters ? $post->allowed_radius_meters.' m' : '-' }}</td>
                    <td><span class="tag {{ $post->is_active ? 'published' : 'draft' }}">{{ $post->is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td><a class="button light" href="{{ route('admin.border-posts.edit', $post) }}">Edit</a></td>
                </tr>
            @empty
                <tr><td colspan="9">No border posts have been configured yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{ $borderPosts->links() }}
@endsection

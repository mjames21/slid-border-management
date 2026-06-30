@extends('layouts.admin')

@section('content')
    <div class="header">
        <div>
            <h1 class="title">Users</h1>
            <p class="subtitle">Create and review border officer logins and post assignments.</p>
        </div>
        <a class="button" href="{{ route('admin.users.create') }}">Create User</a>
    </div>

    <div class="card">
        <form method="GET" action="{{ route('admin.users.index') }}" class="grid">
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
                <a class="button light" href="{{ route('admin.users.index') }}">Clear</a>
            </div>
        </form>
    </div>

    <div class="card">
        <table>
            <thead><tr><th>Name</th><th>Email</th><th>Country</th><th>Role</th><th>Border Post</th><th>Digital Address</th><th>Status</th><th>Setup</th></tr></thead>
            <tbody>
            @forelse($users as $user)
                <tr>
                    <td>{{ $user->name }}</td>
                    <td class="mono">{{ $user->email }}</td>
                    <td>{{ $user->country?->name ?? $user->country_code }}</td>
                    <td>{{ str_replace('_', ' ', ucfirst($user->role ?? '')) }}</td>
                    <td>{{ $user->borderPost?->name ?? 'Not assigned' }}</td>
                    <td class="mono">{{ $user->borderPost?->digital_address ?? '-' }}</td>
                    <td><span class="tag {{ $user->is_active ? 'published' : 'draft' }}">{{ $user->is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td>
                        @if(!$user->is_admin)
                            <a class="button light" href="{{ route('admin.users.setup-qr', $user) }}">Setup QR</a>
                        @else
                            <span class="tag">Admin</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8">No users have been created yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{ $users->links() }}
@endsection

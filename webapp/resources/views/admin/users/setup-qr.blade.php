@extends('layouts.admin')

@section('content')
    <div class="header">
        <div>
            <h1 class="title">Mobile Setup QR</h1>
            <p class="subtitle">Generate a one-time setup QR for {{ $user->name }}.</p>
        </div>
        <a class="button light" href="{{ route('admin.users.index') }}">Back</a>
    </div>

    <div class="card">
        <div class="grid">
            <div>
                <label>User</label>
                <div class="mono">{{ $user->name }} / {{ $user->email }}</div>
            </div>
            <div>
                <label>Assignment</label>
                <div class="mono">{{ $user->country?->name ?? $user->country_code }} / {{ $user->borderPost?->name ?? 'Not assigned' }}</div>
                @if($user->borderPost?->digital_address)
                    <div class="field-help">Digital address: <span class="mono">{{ $user->borderPost->digital_address }}</span></div>
                @endif
            </div>
        </div>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('admin.users.setup-qr.generate', $user) }}">
            @csrf
            <div class="grid">
                <div>
                    <label for="server_url">Server URL</label>
                    <input id="server_url" name="server_url" type="text" value="{{ old('server_url', $defaultServerUrl) }}" required>
                    <div class="field-help">Use the URL the phone can reach, for example http://192.168.1.242:8010/ or your production domain.</div>
                </div>
                <div>
                    <label for="device_name">Suggested Device Name</label>
                    <input id="device_name" name="device_name" type="text" value="{{ old('device_name') }}" placeholder="Optional">
                    <div class="field-help">Leave blank when each phone should keep its own generated device ID.</div>
                </div>
            </div>
            <p style="margin-top:16px;"><button type="submit">Generate Setup QR</button></p>
        </form>
    </div>

    @if($setup)
        <div class="card">
            <div class="grid">
                <div>
                    <h2 class="title" style="font-size:20px;">Scan On Android</h2>
                    <div style="max-width:340px;margin-top:16px;">{!! $setup['qrSvg'] !!}</div>
                </div>
                <div>
                    <h2 class="title" style="font-size:20px;">Temporary Login</h2>
                    <p class="subtitle">This QR reset the user's password. Give this to the officer only during setup.</p>
                    <label>Email</label>
                    <div class="mono">{{ $user->email }}</div>
                    <label style="margin-top:12px;">Temporary Password</label>
                    <div class="mono">{{ $setup['temporaryPassword'] }}</div>
                    <label style="margin-top:12px;">Server URL</label>
                    <div class="mono">{{ $setup['payload']['serverUrl'] }}</div>
                    @if($user->borderPost?->digital_address)
                        <label style="margin-top:12px;">Digital Address</label>
                        <div class="mono">{{ $user->borderPost->digital_address }}</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="card">
            <h2 class="title" style="font-size:20px;">Fallback Setup Code</h2>
            <p class="subtitle">If the phone has no QR scanner app, copy this setup text into the Android setup code field when available.</p>
            <div class="mono">{{ $setup['payloadJson'] }}</div>
        </div>
    @endif
@endsection

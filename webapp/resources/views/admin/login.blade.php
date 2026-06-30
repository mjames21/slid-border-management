@extends('layouts.admin')

@section('content')
    <div class="card" style="max-width: 480px; margin: 80px auto;">
        <h1 class="title">SLID Border Reporting</h1>
        <p class="subtitle">Administrator sign-in</p>
        <form method="POST" action="{{ route('admin.login.submit') }}" style="margin-top: 24px;">
            @csrf
            <div style="margin-bottom: 16px;">
                <label for="email">Email Address</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" maxlength="255">
            </div>
            <div style="margin-bottom: 16px;">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" required autocomplete="current-password" maxlength="1024">
            </div>
            <div style="margin-bottom: 20px;">
                <label><input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}> Remember this session</label>
            </div>
            <button type="submit">Sign in</button>
        </form>
    </div>
@endsection

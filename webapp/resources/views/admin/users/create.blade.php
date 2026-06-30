@extends('layouts.admin')

@section('content')
    <div class="header">
        <div>
            <h1 class="title">Create Border User</h1>
            <p class="subtitle">Provision a mobile login for an assigned border post.</p>
        </div>
        <a class="button light" href="{{ route('admin.users.index') }}">Back</a>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('admin.users.store') }}">
            @csrf
            <div class="grid">
                <div>
                    <label for="name">Name</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" required>
                </div>
                <div>
                    <label for="email">Email</label>
                    <input id="email" name="email" type="text" value="{{ old('email') }}" required>
                </div>
                <div>
                    <label for="role">Role</label>
                    <select id="role" name="role" required>
                        @foreach($roles as $value => $label)
                            <option value="{{ $value }}" @selected(old('role') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="border_post_id">Border Post</label>
                    <select id="border_post_id" name="border_post_id" required>
                        @foreach($borderPosts as $post)
                            <option value="{{ $post->id }}" @selected((string) old('border_post_id') === (string) $post->id)>
                                {{ $post->country_code }} / {{ $post->name }} ({{ $post->code }}){{ $post->digital_address ? ' / '.$post->digital_address : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" required>
                </div>
                <div>
                    <label for="password_confirmation">Confirm Password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required>
                </div>
                <div>
                    <label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))> Active</label>
                </div>
            </div>
            <p style="margin-top:16px;"><button type="submit">Create User</button></p>
        </form>
    </div>
@endsection

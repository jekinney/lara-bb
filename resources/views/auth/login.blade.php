@extends('layouts.app')
@section('title', 'Log in')
@section('content')
<form method="post" action="{{ url('/login') }}" class="card narrow">
    @csrf
    <h1>Log in</h1>
    <div class="field">
        <label for="login">Username or email</label>
        <input id="login" name="login" value="{{ old('login') }}" autocomplete="username" autofocus required>
        @error('login')<span class="err">{{ $message }}</span>@enderror
    </div>
    <div class="field">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        @error('password')<span class="err">{{ $message }}</span>@enderror
    </div>
    <label class="check"><input type="checkbox" name="remember" value="1">Keep me logged in</label>
    <div class="row">
        <a href="{{ route('password.request') }}">Forgot your password?</a>
        <button class="btn pri" type="submit">Log in</button>
    </div>
    <p class="muted">No account yet? <a href="{{ route('register') }}">Register</a></p>
</form>
@endsection

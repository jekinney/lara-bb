@extends('layouts.app')
@section('title', 'Register')
@section('content')
@if ($mode === \App\Auth\RegistrationMode::Closed)
    <div class="card narrow">
        <h1>Registration is closed</h1>
        <p class="lead">This board is not accepting new members right now.</p>
    </div>
@else
<form method="post" action="{{ url('/register') }}" class="card narrow">
    @csrf
    <h1>Create an account</h1>
    @if ($mode === \App\Auth\RegistrationMode::Email)
        <p class="lead">We will email you a link to confirm your address before you can log in.</p>
    @endif
    <div class="field">
        <label for="name">Username</label>
        <input id="name" name="name" value="{{ old('name') }}" autocomplete="username" autofocus required>
        @error('name')<span class="err">{{ $message }}</span>@enderror
    </div>
    <div class="field">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required>
        @error('email')<span class="err">{{ $message }}</span>@enderror
    </div>
    <div class="two">
        <div class="field">
            <label for="password">Password (12 characters or more)</label>
            <input id="password" name="password" type="password" autocomplete="new-password" required>
            @error('password')<span class="err">{{ $message }}</span>@enderror
        </div>
        <div class="field">
            <label for="password_confirmation">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
        </div>
    </div>
    <div class="field">
        <label for="timezone">Time zone</label>
        <select id="timezone" name="timezone">
            @foreach ($timezones as $zone)
                <option value="{{ $zone }}" @selected(old('timezone', 'UTC') === $zone)>{{ $zone }}</option>
            @endforeach
        </select>
        @error('timezone')<span class="err">{{ $message }}</span>@enderror
    </div>
    <div class="hp" aria-hidden="true">
        <label for="website_url">Leave this empty</label>
        <input id="website_url" name="website_url" tabindex="-1" autocomplete="off">
        @error('website_url')<span class="err">{{ $message }}</span>@enderror
    </div>
    <div class="row">
        <a href="{{ route('login') }}">I already have an account</a>
        <button class="btn pri" type="submit">Register</button>
    </div>
</form>
@endif
@endsection

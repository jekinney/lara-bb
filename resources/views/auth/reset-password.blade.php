@extends('layouts.app')
@section('title', 'Choose a new password')
@section('content')
<form method="post" action="{{ route('password.update') }}" class="card narrow">
    @csrf
    <h1>Choose a new password</h1>
    <input type="hidden" name="token" value="{{ $token }}">
    <div class="field">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email', $email) }}" autocomplete="email" required>
        @error('email')<span class="err">{{ $message }}</span>@enderror
    </div>
    <div class="field">
        <label for="password">New password (12 characters or more)</label>
        <input id="password" name="password" type="password" autocomplete="new-password" autofocus required>
        @error('password')<span class="err">{{ $message }}</span>@enderror
    </div>
    <div class="field">
        <label for="password_confirmation">Confirm password</label>
        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
    </div>
    <div class="row"><span></span><button class="btn pri" type="submit">Change password</button></div>
</form>
@endsection

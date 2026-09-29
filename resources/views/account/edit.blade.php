@extends('layouts.app')
@section('title', 'User control panel')
@section('content')
@php($v = fn (string $key) => old($key, $user->{$key}))
<form method="post" action="{{ route('account.update') }}" class="card">
    @csrf
    @method('PUT')
    <h1>Your profile</h1>
    <div class="two">
        <div class="field">
            <label for="timezone">Time zone</label>
            <select id="timezone" name="timezone">
                @foreach ($timezones as $zone)
                    <option value="{{ $zone }}" @selected($v('timezone') === $zone)>{{ $zone }}</option>
                @endforeach
            </select>
            @error('timezone')<span class="err">{{ $message }}</span>@enderror
        </div>
        <div class="field">
            <label for="preferred_mode">Appearance</label>
            <select id="preferred_mode" name="preferred_mode">
                @foreach (['auto' => 'Match my device', 'light' => 'Light', 'dark' => 'Dark'] as $value => $label)
                    <option value="{{ $value }}" @selected($v('preferred_mode') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('preferred_mode')<span class="err">{{ $message }}</span>@enderror
        </div>
        <div class="field">
            <label for="preferred_theme">Theme</label>
            <select id="preferred_theme" name="preferred_theme">
                <option value="">Board default</option>
                <option value="default" @selected($v('preferred_theme') === 'default')>laraBB Default</option>
            </select>
            @error('preferred_theme')<span class="err">{{ $message }}</span>@enderror
        </div>
        <div class="field">
            <label for="preferred_editor">Editor</label>
            <select id="preferred_editor" name="preferred_editor">
                <option value="">Board default</option>
                <option value="markdown" @selected($v('preferred_editor') === 'markdown')>Markdown</option>
                <option value="bbcode" @selected($v('preferred_editor') === 'bbcode')>BBCode</option>
            </select>
            @error('preferred_editor')<span class="err">{{ $message }}</span>@enderror
        </div>
        <div class="field">
            <label for="location">Location</label>
            <input id="location" name="location" value="{{ $v('location') }}" maxlength="100">
            @error('location')<span class="err">{{ $message }}</span>@enderror
        </div>
        <div class="field">
            <label for="website">Website</label>
            <input id="website" name="website" type="url" value="{{ $v('website') }}" placeholder="https://">
            @error('website')<span class="err">{{ $message }}</span>@enderror
        </div>
    </div>
    <div class="field">
        <label for="about">About you</label>
        <textarea id="about" name="about" maxlength="2000">{{ $v('about') }}</textarea>
        @error('about')<span class="err">{{ $message }}</span>@enderror
    </div>
    <div class="field">
        <label for="signature">Signature</label>
        <input id="signature" name="signature" value="{{ $v('signature') }}" maxlength="255">
        @error('signature')<span class="err">{{ $message }}</span>@enderror
    </div>
    <div class="row"><a href="{{ route('members.show', $user->name) }}">View your public profile</a><button class="btn pri" type="submit">Save profile</button></div>
</form>

<form method="post" action="{{ route('account.password') }}" class="card">
    @csrf
    @method('PUT')
    <h2>Change your password</h2>
    <div class="field">
        <label for="current_password">Current password</label>
        <input id="current_password" name="current_password" type="password" autocomplete="current-password" required>
        @error('current_password')<span class="err">{{ $message }}</span>@enderror
    </div>
    <div class="two">
        <div class="field">
            <label for="new_password">New password (12 characters or more)</label>
            <input id="new_password" name="password" type="password" autocomplete="new-password" required>
            @error('password')<span class="err">{{ $message }}</span>@enderror
        </div>
        <div class="field">
            <label for="new_password_confirmation">Confirm new password</label>
            <input id="new_password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
        </div>
    </div>
    <div class="row"><span></span><button class="btn" type="submit">Change password</button></div>
</form>
@endsection

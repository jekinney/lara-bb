@extends('install.layout')
@section('content')
<h1>Your board and founder account</h1>
@php($v = fn (string $key) => old($key, $values[$key] ?? ''))
<form method="post" action="{{ route('install.site.submit') }}" class="card" style="padding:0;border:0;background:none;max-width:none">
    @csrf
    <div class="two">
        <div class="field">
            <label for="board_name">Board name</label>
            <input id="board_name" name="board_name" value="{{ $v('board_name') }}" required>
            @error('board_name')<span class="err">{{ $message }}</span>@enderror
        </div>
        <div class="field">
            <label for="board_url">Board URL</label>
            <input id="board_url" name="board_url" type="url" value="{{ $v('board_url') }}" required>
            @error('board_url')<span class="err">{{ $message }}</span>@enderror
        </div>
        <div class="field">
            <label for="timezone">Time zone</label>
            <select id="timezone" name="timezone">
                @foreach ($timezones as $zone)
                    <option value="{{ $zone }}" @selected($v('timezone') === $zone)>{{ $zone }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="theme">Default theme</label>
            <select id="theme" name="theme"><option value="default">laraBB Default (dark first, with light)</option></select>
        </div>
        <div class="field">
            <label for="editor">Default editor</label>
            <select id="editor" name="editor">
                <option value="markdown" @selected($v('editor') === 'markdown')>Markdown</option>
                <option value="bbcode" @selected($v('editor') === 'bbcode')>BBCode</option>
            </select>
        </div>
    </div>
    <h2 style="font-size:1rem;margin:0">Founder account</h2>
    <div class="two">
        <div class="field">
            <label for="founder_username">Username</label>
            <input id="founder_username" name="founder_username" autocomplete="username" value="{{ $v('founder_username') }}" required>
            @error('founder_username')<span class="err">{{ $message }}</span>@enderror
        </div>
        <div class="field">
            <label for="founder_email">Email</label>
            <input id="founder_email" name="founder_email" type="email" autocomplete="email" value="{{ $v('founder_email') }}" required>
            @error('founder_email')<span class="err">{{ $message }}</span>@enderror
        </div>
        <div class="field">
            <label for="founder_password">Password (12 characters or more)</label>
            <input id="founder_password" name="founder_password" type="password" autocomplete="new-password" required>
            @error('founder_password')<span class="err">{{ $message }}</span>@enderror
        </div>
        <div class="field">
            <label for="founder_password_confirmation">Confirm password</label>
            <input id="founder_password_confirmation" name="founder_password_confirmation" type="password" autocomplete="new-password" required>
        </div>
    </div>
    <div class="hint">The founder bypasses all permissions. Use a long passphrase.</div>
    <div class="row"><span></span><button class="btn pri" type="submit">Continue</button></div>
</form>
@endsection

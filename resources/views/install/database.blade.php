@extends('install.layout')
@section('content')
<h1>Database</h1>
<p class="lead">Use a local database or a managed one, such as DigitalOcean Managed MySQL. It must be empty.</p>
@include('install.probe', ['section' => 'database'])
<form method="post" action="{{ route('install.database.submit') }}" class="card" style="padding:0;border:0;background:none;max-width:none">
    @csrf
    <div class="two">
        <div class="field">
            <label for="driver">Type</label>
            <select id="driver" name="driver">
                @foreach (['mysql' => 'MySQL', 'mariadb' => 'MariaDB', 'sqlite' => 'SQLite (development only)'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('driver', $values['driver']) === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('driver')<span class="err">{{ $message }}</span>@enderror
        </div>
        <div class="field">
            <label for="database">Database name (SQLite: file path)</label>
            <input id="database" name="database" value="{{ old('database', $values['database'] ?? '') }}" required>
            @error('database')<span class="err">{{ $message }}</span>@enderror
        </div>
        <div class="field">
            <label for="host">Host</label>
            <input id="host" name="host" value="{{ old('host', $values['host'] ?? '') }}">
            @error('host')<span class="err">{{ $message }}</span>@enderror
        </div>
        <div class="field">
            <label for="port">Port</label>
            <input id="port" name="port" inputmode="numeric" value="{{ old('port', $values['port'] ?? '') }}">
            @error('port')<span class="err">{{ $message }}</span>@enderror
        </div>
        <div class="field">
            <label for="username">Username</label>
            <input id="username" name="username" autocomplete="off" value="{{ old('username', $values['username'] ?? '') }}">
            @error('username')<span class="err">{{ $message }}</span>@enderror
        </div>
        <div class="field">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="new-password" value="{{ old('password', $values['password'] ?? '') }}">
            @error('password')<span class="err">{{ $message }}</span>@enderror
        </div>
    </div>
    <label class="check"><input type="checkbox" name="tls" value="1" @checked(old('tls', $values['tls'] ?? false))>Require TLS and verify the server certificate</label>
    <div class="field">
        <label for="ca_cert">CA certificate (required with TLS)</label>
        <textarea id="ca_cert" name="ca_cert" spellcheck="false" placeholder="-----BEGIN CERTIFICATE-----">{{ old('ca_cert', $values['ca_cert'] ?? '') }}</textarea>
        @error('ca_cert')<span class="err">{{ $message }}</span>@enderror
    </div>
    <div class="row">
        <button class="btn" type="submit" name="action" value="test">Test connection</button>
        <button class="btn pri" type="submit" name="action" value="continue">Continue</button>
    </div>
</form>
@endsection

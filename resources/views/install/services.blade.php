@extends('install.layout')
@section('content')
<h1>Services</h1>
<p class="lead">Each service can be a local container or a managed one. Test them before you continue.</p>
@include('install.probe', ['section' => 'services'])
@php($v = fn (string $key) => old($key, $values[$key] ?? ''))
<form method="post" action="{{ route('install.services.submit') }}" class="card" style="padding:0;border:0;background:none;max-width:none">
    @csrf
    <div class="two">
        <div class="field">
            <label for="cache_driver">Cache, sessions and queue</label>
            <select id="cache_driver" name="cache_driver">
                <option value="database" @selected($v('cache_driver') === 'database')>Database (no extra service)</option>
                <option value="redis" @selected($v('cache_driver') === 'redis')>Redis</option>
            </select>
        </div>
        <div class="field">
            <label for="redis_host">Redis host</label>
            <input id="redis_host" name="redis_host" value="{{ $v('redis_host') }}">
            @error('redis_host')<span class="err">{{ $message }}</span>@enderror
        </div>
        <div class="field">
            <label for="redis_port">Redis port</label>
            <input id="redis_port" name="redis_port" inputmode="numeric" value="{{ $v('redis_port') }}">
            @error('redis_port')<span class="err">{{ $message }}</span>@enderror
        </div>
        <div class="field">
            <label for="redis_password">Redis password</label>
            <input id="redis_password" name="redis_password" type="password" autocomplete="new-password" value="{{ $v('redis_password') }}">
        </div>
    </div>
    <div class="two">
        <div class="field">
            <label for="mail_mailer">Mail</label>
            <select id="mail_mailer" name="mail_mailer">
                <option value="log" @selected($v('mail_mailer') === 'log')>Write to the log (no email sent)</option>
                <option value="smtp" @selected($v('mail_mailer') === 'smtp')>SMTP</option>
            </select>
        </div>
        <div class="field">
            <label for="mail_from">From address</label>
            <input id="mail_from" name="mail_from" type="email" value="{{ $v('mail_from') }}" required>
            @error('mail_from')<span class="err">{{ $message }}</span>@enderror
        </div>
        <div class="field">
            <label for="mail_host">SMTP host</label>
            <input id="mail_host" name="mail_host" value="{{ $v('mail_host') }}">
            @error('mail_host')<span class="err">{{ $message }}</span>@enderror
        </div>
        <div class="field">
            <label for="mail_port">SMTP port</label>
            <input id="mail_port" name="mail_port" inputmode="numeric" value="{{ $v('mail_port') }}">
            @error('mail_port')<span class="err">{{ $message }}</span>@enderror
        </div>
        <div class="field">
            <label for="mail_username">SMTP username</label>
            <input id="mail_username" name="mail_username" autocomplete="off" value="{{ $v('mail_username') }}">
        </div>
        <div class="field">
            <label for="mail_password">SMTP password</label>
            <input id="mail_password" name="mail_password" type="password" autocomplete="new-password" value="{{ $v('mail_password') }}">
        </div>
        <div class="field">
            <label for="mail_encryption">Encryption</label>
            <select id="mail_encryption" name="mail_encryption">
                @foreach (['tls' => 'STARTTLS (port 587)', 'ssl' => 'SSL/TLS (port 465)', 'none' => 'None'] as $value => $label)
                    <option value="{{ $value }}" @selected($v('mail_encryption') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="test_email">Send a test email to</label>
            <input id="test_email" name="test_email" type="email" value="{{ $v('test_email') }}">
            @error('test_email')<span class="err">{{ $message }}</span>@enderror
        </div>
    </div>
    <div class="two">
        <div class="field">
            <label for="storage">File storage</label>
            <select id="storage" name="storage">
                <option value="local" @selected($v('storage') === 'local')>Local volume</option>
                <option value="s3" @selected($v('storage') === 's3')>S3 compatible (DigitalOcean Spaces, AWS, R2)</option>
            </select>
        </div>
        <div class="field">
            <label for="s3_endpoint">S3 endpoint (leave blank for AWS)</label>
            <input id="s3_endpoint" name="s3_endpoint" value="{{ $v('s3_endpoint') }}" placeholder="https://nyc3.digitaloceanspaces.com">
            @error('s3_endpoint')<span class="err">{{ $message }}</span>@enderror
        </div>
        <div class="field">
            <label for="s3_key">Access key</label>
            <input id="s3_key" name="s3_key" autocomplete="off" value="{{ $v('s3_key') }}">
            @error('s3_key')<span class="err">{{ $message }}</span>@enderror
        </div>
        <div class="field">
            <label for="s3_secret">Secret key</label>
            <input id="s3_secret" name="s3_secret" type="password" autocomplete="new-password" value="{{ $v('s3_secret') }}">
            @error('s3_secret')<span class="err">{{ $message }}</span>@enderror
        </div>
        <div class="field">
            <label for="s3_region">Region</label>
            <input id="s3_region" name="s3_region" value="{{ $v('s3_region') }}">
            @error('s3_region')<span class="err">{{ $message }}</span>@enderror
        </div>
        <div class="field">
            <label for="s3_bucket">Bucket / Space name</label>
            <input id="s3_bucket" name="s3_bucket" value="{{ $v('s3_bucket') }}">
            @error('s3_bucket')<span class="err">{{ $message }}</span>@enderror
        </div>
    </div>
    <div class="row">
        <span style="display:flex;gap:8px;flex-wrap:wrap">
            <button class="btn" type="submit" name="action" value="test_redis">Test Redis</button>
            <button class="btn" type="submit" name="action" value="test_storage">Test storage</button>
            <button class="btn" type="submit" name="action" value="test_mail">Send test email</button>
        </span>
        <button class="btn pri" type="submit" name="action" value="continue">Continue</button>
    </div>
</form>
@endsection

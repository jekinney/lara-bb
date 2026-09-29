@extends('install.layout')
@section('content')
<h1>Confirm you own this server</h1>
<p class="lead">Enter the one-time setup token so nobody else can claim this site before you.</p>
<form method="post" action="{{ route('install.token.submit') }}" class="card" style="padding:0;border:0;background:none;max-width:none">
    @csrf
    <div class="field">
        <label for="token">Setup token</label>
        <input class="tok" id="token" name="token" value="{{ old('token') }}" autocomplete="off" spellcheck="false" autofocus required>
        @error('token')<span class="err">{{ $message }}</span>@enderror
    </div>
    <div class="hint">
        Find it in the application log, for example <code>docker compose logs web</code>, or in <code>{{ $tokenFile }}</code>.
        It expires in {{ config('larabb.install.token_ttl_minutes') }} minutes.
    </div>
    <div class="row"><span></span><button class="btn pri" type="submit">Continue</button></div>
</form>
@endsection

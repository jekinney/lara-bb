@extends('layouts.app')
@section('title', 'Confirm your email')
@section('content')
<div class="card narrow">
    <h1>Check your email</h1>
    <p class="lead">We sent a link to confirm your address. Open it, then log in. The link works for {{ config('auth.verification.expire', 60) }} minutes.</p>
    <form method="post" action="{{ route('verification.send') }}" class="field">
        @csrf
        <label for="email">Did not get it? Send it again to</label>
        <input id="email" name="email" type="email" value="{{ old('email', session('email')) }}" required>
        @error('email')<span class="err">{{ $message }}</span>@enderror
        <div class="row"><span></span><button class="btn" type="submit">Send again</button></div>
    </form>
</div>
@endsection

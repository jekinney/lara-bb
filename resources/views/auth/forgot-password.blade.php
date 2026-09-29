@extends('layouts.app')
@section('title', 'Forgot your password')
@section('content')
<form method="post" action="{{ route('password.email') }}" class="card narrow">
    @csrf
    <h1>Forgot your password?</h1>
    <p class="lead">Enter your email address and we will send you a link to choose a new one.</p>
    <div class="field">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" autofocus required>
        @error('email')<span class="err">{{ $message }}</span>@enderror
    </div>
    <div class="row"><a href="{{ route('login') }}">Back to log in</a><button class="btn pri" type="submit">Send the link</button></div>
</form>
@endsection

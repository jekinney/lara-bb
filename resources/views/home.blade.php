@extends('layouts.app')
@section('content')
<div class="card">
    <h1>{{ auth()->check() ? 'Welcome back, '.auth()->user()->name : 'Welcome to '.config('app.name') }}</h1>
    <p class="lead">The forums are coming next. Accounts, groups and profiles are ready.</p>
    @guest
        <div class="row" style="justify-content:flex-start">
            <a class="btn pri" href="{{ route('register') }}">Create an account</a>
            <a class="btn" href="{{ route('login') }}">Log in</a>
        </div>
    @endguest
</div>
@endsection

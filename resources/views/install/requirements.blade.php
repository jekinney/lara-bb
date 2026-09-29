@extends('install.layout')
@section('content')
<h1>Server check</h1>
<p class="lead">laraBB checked what it needs to run safely.</p>
<ul class="list">
    @foreach ($checks as $check)
        <li><span class="dot {{ $check['status'] }}" role="img" aria-label="{{ $check['status'] }}"></span>{{ $check['label'] }}<span class="d">{{ $check['detail'] }}</span></li>
    @endforeach
</ul>
@error('requirements')<div class="banner bad">{{ $message }}</div>@enderror
<form method="post" action="{{ route('install.requirements.submit') }}" class="row">
    @csrf
    <a class="btn" href="{{ route('install.requirements') }}">Check again</a>
    <button class="btn pri" type="submit">Continue</button>
</form>
@endsection

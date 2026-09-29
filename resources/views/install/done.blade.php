@extends('install.layout')
@section('content')
<h1>laraBB is ready</h1>
<ul class="list">
    @foreach ($tasks as $task)
        <li><span class="dot ok" role="img" aria-label="done"></span>{{ $task }}</li>
    @endforeach
</ul>
<div class="banner ok">The installer is locked and <code>/install</code> now returns 404. To reinstall, empty the database, delete the lock file and restart.</div>
<div class="row"><span></span><a class="btn pri" href="{{ $boardUrl }}">Open your board</a></div>
@endsection

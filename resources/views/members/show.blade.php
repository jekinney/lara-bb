@extends('layouts.app')
@section('title', $member->name)
@section('content')
<div class="card">
    <div class="who">
        <span class="av lg tone-{{ $member->avatarTone() }}" aria-hidden="true">{{ $member->initials() }}</span>
        <div>
            <h1>{{ $member->name }}</h1>
            <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:6px">
                @foreach ($member->groups as $group)
                    <span class="pill" @if ($group->color) style="--c:{{ $group->color }}" @endif>{{ $group->name }}</span>
                @endforeach
            </div>
        </div>
    </div>
    <dl>
        <dt>Joined</dt><dd>{{ $member->created_at->format('j M Y') }}</dd>
        <dt>Last visit</dt><dd>{{ $member->last_visit_at ? $member->last_visit_at->diffForHumans() : 'Never' }}</dd>
        @if ($member->location)
            <dt>Location</dt><dd>{{ $member->location }}</dd>
        @endif
        @if ($member->website)
            <dt>Website</dt><dd><a href="{{ $member->website }}" rel="nofollow ugc noopener" target="_blank">{{ $member->website }}</a></dd>
        @endif
    </dl>
    @if ($member->about)
        <div>
            <h2>About</h2>
            <p style="white-space:pre-line;margin:6px 0 0">{{ $member->about }}</p>
        </div>
    @endif
    @if ($member->signature)
        <p class="muted">{{ $member->signature }}</p>
    @endif
    @auth
        @if (auth()->id() === $member->id)
            <div class="row"><span></span><a class="btn" href="{{ route('account') }}">Edit your profile</a></div>
        @endif
    @endauth
</div>
@endsection

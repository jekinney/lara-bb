@extends('layouts.app')
@section('title', 'Members')
@section('content')
<div class="card">
    <h1>Members</h1>
    <ul class="list">
        @foreach ($members as $member)
            <li>
                <span class="av tone-{{ $member->avatarTone() }}" aria-hidden="true">{{ $member->initials() }}</span>
                <a href="{{ route('members.show', $member->name) }}"><strong>{{ $member->name }}</strong></a>
                <span class="muted" style="margin-left:auto">Joined {{ $member->created_at->format('M Y') }}</span>
            </li>
        @endforeach
    </ul>
    {{ $members->links('members.pager') }}
</div>
@endsection

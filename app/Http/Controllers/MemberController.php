<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class MemberController extends Controller
{
    public function index(): View
    {
        return view('members.index', [
            'members' => User::query()->where('status', User::STATUS_ACTIVE)->orderBy('username_clean')->paginate(25),
        ]);
    }

    public function show(string $username): View
    {
        $member = User::query()
            ->where('status', User::STATUS_ACTIVE)
            ->where('username_clean', Str::lower($username))
            ->with(['groups' => fn ($groups) => $groups->where('is_hidden', false)->wherePivot('is_pending', false)->orderBy('name')])
            ->firstOrFail();

        return view('members.show', ['member' => $member]);
    }
}

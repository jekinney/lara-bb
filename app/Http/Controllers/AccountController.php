<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

final class AccountController extends Controller
{
    public function edit(Request $request): View
    {
        return view('account.edit', [
            'user' => $this->member($request),
            'timezones' => timezone_identifiers_list(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'timezone' => ['required', Rule::in(timezone_identifiers_list())],
            'preferred_mode' => ['required', Rule::in(['auto', 'light', 'dark'])],
            'preferred_theme' => ['nullable', Rule::in(['default'])],
            'preferred_editor' => ['nullable', Rule::in(['markdown', 'bbcode'])],
            'signature' => ['nullable', 'string', 'max:255'],
            'about' => ['nullable', 'string', 'max:2000'],
            'location' => ['nullable', 'string', 'max:100'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
        ]);

        $this->member($request)->update($data);

        return back()->with('status', 'Your profile is saved.');
    }

    public function password(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(12)->max(200), 'different:current_password'],
        ]);

        $this->member($request)->forceFill(['password' => $data['password'], 'remember_token' => Str::random(60)])->save();

        return back()->with('status', 'Your password is changed.');
    }

    /** These routes sit behind the auth middleware, so a request always has a member. */
    private function member(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}

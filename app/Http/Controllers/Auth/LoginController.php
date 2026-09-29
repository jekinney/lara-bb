<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class LoginController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $input = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $key = Str::lower($input['login']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'login' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.',
            ]);
        }

        $user = User::query()
            ->where('username_clean', Str::lower($input['login']))
            ->orWhere('email', Str::lower($input['login']))
            ->first();

        // Always hash-check, even for an unknown login, so timing does not reveal which logins exist.
        $matches = Hash::check($input['password'], $user === null ? Hash::make(Str::random(32)) : $user->password);

        if ($user === null || ! $matches) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages(['login' => 'That username or password is not right.']);
        }

        if ($user->status === User::STATUS_AWAITING_EMAIL) {
            throw ValidationException::withMessages(['login' => 'Confirm your email address first. Use the link we emailed you.']);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages(['login' => 'This account is not active.']);
        }

        RateLimiter::clear($key);
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $user->forceFill(['last_visit_at' => now()])->save();

        return redirect()->intended('/');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}

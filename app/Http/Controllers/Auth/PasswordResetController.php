<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

final class PasswordResetController extends Controller
{
    public function request(): View
    {
        return view('auth.forgot-password');
    }

    /** Always answers the same, so this cannot be used to find out which emails have accounts. */
    public function email(Request $request): RedirectResponse
    {
        $email = Str::lower(trim((string) $request->validate(['email' => ['required', 'email']])['email']));

        PasswordBroker::sendResetLink(['email' => $email, 'status' => User::STATUS_ACTIVE]);

        return back()->with('status', 'If that address has an account, we emailed a link to reset the password.');
    }

    public function reset(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email', '')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Password::min(12)->max(200)],
        ]);

        $status = PasswordBroker::reset(
            ['email' => $data['email'], 'password' => $data['password'], 'password_confirmation' => $data['password'], 'token' => $data['token'], 'status' => User::STATUS_ACTIVE],
            function (User $user, string $password) {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                event(new PasswordReset($user));
            },
        );

        return $status === PasswordBroker::PASSWORD_RESET
            ? redirect()->route('login')->with('status', 'Your password is changed. You can log in now.')
            : back()->withInput($request->only('email'))->withErrors(['email' => 'This reset link is not valid, or it has expired. Ask for a new one.']);
    }
}

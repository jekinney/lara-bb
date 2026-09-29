<?php

namespace App\Http\Controllers\Auth;

use App\Auth\RegistrationMode;
use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\User;
use App\Rules\AvailableUsername;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

final class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register', [
            'mode' => RegistrationMode::current(),
            'timezones' => timezone_identifiers_list(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $mode = RegistrationMode::current();
        abort_if($mode === RegistrationMode::Closed, 403, 'Registration is closed.');

        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'name' => ['required', 'string', new AvailableUsername],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(12)->max(200)],
            'timezone' => ['required', Rule::in(timezone_identifiers_list())],
            // Honeypot: hidden from people, so only a bot fills it in.
            'website_url' => ['prohibited'],
        ]);

        $registered = Group::bySlug('registered');

        $user = new User(Arr::only($data, ['name', 'email', 'password', 'timezone']));
        $user->status = $mode === RegistrationMode::Email ? User::STATUS_AWAITING_EMAIL : User::STATUS_ACTIVE;
        $user->primary_group_id = $registered->id;
        $user->save();
        $user->groups()->attach($registered->id);

        if ($mode === RegistrationMode::Email) {
            $user->sendEmailVerificationNotification();

            return redirect()->route('verification.notice')->with('email', $user->email);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/')->with('status', 'Welcome to '.config('app.name').'.');
    }
}

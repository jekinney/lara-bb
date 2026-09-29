<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class EmailVerificationController extends Controller
{
    public function notice(): View
    {
        return view('auth.verify-email');
    }

    /** The link in the email. The route's signed middleware has already checked the signature. */
    public function verify(int $id, string $hash): RedirectResponse
    {
        $user = User::query()->findOrFail($id);
        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403);

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        if ($user->status === User::STATUS_AWAITING_EMAIL) {
            $user->forceFill(['status' => User::STATUS_ACTIVE])->save();
        }

        return redirect()->route('login')->with('status', 'Your email address is confirmed. You can log in now.');
    }

    /** Always answers the same, so this cannot be used to find out which emails have accounts. */
    public function resend(Request $request): RedirectResponse
    {
        $email = Str::lower(trim((string) $request->validate(['email' => ['required', 'email']])['email']));

        User::query()
            ->where('email', $email)
            ->where('status', User::STATUS_AWAITING_EMAIL)
            ->first()
            ?->sendEmailVerificationNotification();

        return back()->with('status', 'If that address is waiting for confirmation, we sent the link again.')->with('email', $email);
    }
}

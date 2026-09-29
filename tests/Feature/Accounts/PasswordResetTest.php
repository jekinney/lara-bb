<?php

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

const RESET_MESSAGE = 'If that address has an account, we emailed a link to reset the password.';

it('shows the forgot password form', function () {
    $this->get('/forgot-password')->assertOk()->assertSee('Forgot your password?');
});

it('emails a reset link to an active member', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'mira@example.com']);

    $this->post('/forgot-password', ['email' => 'Mira@Example.com'])->assertSessionHas('status', RESET_MESSAGE);

    Notification::assertSentTo($user, ResetPassword::class);
});

it('answers the same, and sends nothing, for unknown or unconfirmed addresses', function () {
    Notification::fake();
    User::factory()->awaitingEmail()->create(['email' => 'waiting@example.com']);

    $this->post('/forgot-password', ['email' => 'waiting@example.com'])->assertSessionHas('status', RESET_MESSAGE);
    $this->post('/forgot-password', ['email' => 'nobody@example.com'])->assertSessionHas('status', RESET_MESSAGE);

    Notification::assertNothingSent();
});

it('needs a valid email to send a link', function () {
    $this->post('/forgot-password', ['email' => 'nope'])->assertSessionHasErrors('email');
});

it('shows the reset form with the token and email from the link', function () {
    $this->get('/reset-password/abc123?email=mira@example.com')
        ->assertOk()->assertSee('Choose a new password')->assertSee('abc123')->assertSee('mira@example.com');
});

it('shows the reset form when the link has no email', function () {
    $this->get('/reset-password/abc123')->assertOk();
});

it('changes the password with a valid token', function () {
    Event::fake([PasswordReset::class]);
    $user = User::factory()->create(['email' => 'mira@example.com', 'remember_token' => 'old-token']);
    $token = Password::createToken($user);

    $this->post('/reset-password', [
        'token' => $token, 'email' => 'Mira@Example.com',
        'password' => 'a-brand-new-passphrase', 'password_confirmation' => 'a-brand-new-passphrase',
    ])->assertRedirect(route('login'))->assertSessionHas('status');

    $user->refresh();
    expect(Hash::check('a-brand-new-passphrase', $user->password))->toBeTrue()
        ->and($user->remember_token)->not->toBe('old-token');
    Event::assertDispatched(PasswordReset::class);
});

it('cannot use a token twice', function () {
    $user = User::factory()->create();
    $token = Password::createToken($user);
    $payload = ['token' => $token, 'email' => $user->email, 'password' => 'a-brand-new-passphrase', 'password_confirmation' => 'a-brand-new-passphrase'];

    $this->post('/reset-password', $payload)->assertRedirect(route('login'));
    $this->post('/reset-password', $payload)->assertSessionHasErrors('email');
});

it('rejects a wrong token without changing the password', function () {
    $user = User::factory()->create();

    $this->post('/reset-password', [
        'token' => 'not-a-real-token', 'email' => $user->email,
        'password' => 'a-brand-new-passphrase', 'password_confirmation' => 'a-brand-new-passphrase',
    ])->assertSessionHasErrors(['email' => 'This reset link is not valid, or it has expired. Ask for a new one.']);

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

it('does not reset the password of a member who has not confirmed their email', function () {
    $user = User::factory()->awaitingEmail()->create();
    $token = Password::createToken($user);

    $this->post('/reset-password', [
        'token' => $token, 'email' => $user->email,
        'password' => 'a-brand-new-passphrase', 'password_confirmation' => 'a-brand-new-passphrase',
    ])->assertSessionHasErrors('email');

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

it('holds the new password to the same rules as registration', function () {
    $user = User::factory()->create();

    $this->post('/reset-password', ['token' => Password::createToken($user), 'email' => $user->email, 'password' => 'short', 'password_confirmation' => 'short'])
        ->assertSessionHasErrors('password');
});

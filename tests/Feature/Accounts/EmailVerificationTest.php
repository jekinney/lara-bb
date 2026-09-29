<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

function verificationLink(User $user, ?string $hash = null, int $minutes = 60): string
{
    return URL::temporarySignedRoute('verification.verify', now()->addMinutes($minutes), [
        'id' => $user->id,
        'hash' => $hash ?? sha1($user->email),
    ]);
}

it('tells a new member to check their email', function () {
    $this->withSession(['email' => 'mira@example.com'])->get('/email/verify')
        ->assertOk()->assertSee('Check your email')->assertSee('mira@example.com');
});

it('confirms the email, activates the account and sends them to log in', function () {
    $user = User::factory()->awaitingEmail()->create();

    $this->get(verificationLink($user))->assertRedirect(route('login'))->assertSessionHas('status');

    $user->refresh();
    expect($user->hasVerifiedEmail())->toBeTrue()->and($user->status)->toBe('active');
});

it('is harmless to open the link again', function () {
    $user = User::factory()->create();
    $verifiedAt = $user->email_verified_at;

    $this->get(verificationLink($user))->assertRedirect(route('login'));

    expect($user->fresh()->email_verified_at->equalTo($verifiedAt))->toBeTrue()->and($user->fresh()->status)->toBe('active');
});

it('does not reactivate an account that was made inactive', function () {
    $user = User::factory()->create(['status' => 'inactive']);

    $this->get(verificationLink($user))->assertRedirect(route('login'));

    expect($user->fresh()->status)->toBe('inactive');
});

it('refuses a link for a different email address', function () {
    $user = User::factory()->awaitingEmail()->create();

    $this->get(verificationLink($user, sha1('someone-else@example.com')))->assertForbidden();

    expect($user->fresh()->status)->toBe('awaiting_email');
});

it('refuses a link that was changed or has expired', function () {
    $user = User::factory()->awaitingEmail()->create();

    $this->get(verificationLink($user).'x')->assertForbidden();
    $this->get(verificationLink($user, minutes: -5))->assertForbidden();
    $this->get("/email/verify/{$user->id}/".sha1($user->email))->assertForbidden();
});

it('answers 404 for a link to an account that does not exist', function () {
    $this->get(URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => 999, 'hash' => sha1('x')]))->assertNotFound();
});

it('sends the link again to a member who is waiting', function () {
    Notification::fake();
    $user = User::factory()->awaitingEmail()->create(['email' => 'mira@example.com']);

    $this->post('/email/resend', ['email' => 'Mira@Example.com'])
        ->assertSessionHas('status')->assertSessionHas('email', 'mira@example.com');

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('answers the same for addresses that are not waiting, and sends nothing', function () {
    Notification::fake();
    User::factory()->create(['email' => 'active@example.com']);

    $known = $this->post('/email/resend', ['email' => 'active@example.com']);
    $unknown = $this->post('/email/resend', ['email' => 'nobody@example.com']);

    expect(session('status'))->toBe('If that address is waiting for confirmation, we sent the link again.');
    $known->assertSessionHas('status');
    $unknown->assertSessionHas('status');
    Notification::assertNothingSent();
});

it('needs a valid email to resend', function () {
    $this->post('/email/resend', ['email' => 'nope'])->assertSessionHasErrors('email');
});

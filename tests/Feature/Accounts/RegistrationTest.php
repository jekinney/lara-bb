<?php

use App\Models\Group;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

/** @param  array<string, mixed>  $overrides */
function registration(array $overrides = []): array
{
    return $overrides + [
        'name' => 'Mira_K',
        'email' => 'Mira@Example.com',
        'password' => 'a-long-passphrase-42',
        'password_confirmation' => 'a-long-passphrase-42',
        'timezone' => 'Europe/Berlin',
        'website_url' => '',
    ];
}

it('shows the registration form', function () {
    $this->get('/register')->assertOk()->assertSee('Create an account')->assertDontSee('confirm your address');
});

it('says confirmation is needed when the board asks for it', function () {
    Setting::put('registration.mode', 'email');

    $this->get('/register')->assertOk()->assertSee('confirm your address');
});

it('shows a closed board and refuses registrations', function () {
    Setting::put('registration.mode', 'closed');

    $this->get('/register')->assertOk()->assertSee('Registration is closed')->assertDontSee('Create an account');
    $this->post('/register', registration())->assertForbidden();
    expect(User::count())->toBe(0);
});

it('registers a member, puts them in Registered users and logs them in', function () {
    $this->post('/register', registration())->assertRedirect('/')->assertSessionHas('status');

    $user = User::firstOrFail();
    expect($user->name)->toBe('Mira_K')
        ->and($user->username_clean)->toBe('mira_k')
        ->and($user->email)->toBe('mira@example.com')
        ->and($user->status)->toBe('active')
        ->and($user->is_founder)->toBeFalse()
        ->and($user->timezone)->toBe('Europe/Berlin')
        ->and(Hash::check('a-long-passphrase-42', $user->password))->toBeTrue()
        ->and($user->primaryGroup->slug)->toBe('registered')
        ->and($user->groups->pluck('slug')->all())->toBe(['registered']);
    $this->assertAuthenticatedAs($user);
});

it('holds the member until they confirm their email when the board asks for it', function () {
    Setting::put('registration.mode', 'email');
    Notification::fake();

    $this->post('/register', registration())->assertRedirect(route('verification.notice'))->assertSessionHas('email', 'mira@example.com');

    $user = User::firstOrFail();
    expect($user->status)->toBe('awaiting_email')->and($user->email_verified_at)->toBeNull();
    $this->assertGuest();
    Notification::assertSentTo($user, VerifyEmail::class);
});

it('rejects a duplicate username in any capitalisation', function () {
    User::factory()->create(['name' => 'Mira_K']);

    $this->post('/register', registration(['name' => 'mira_k', 'email' => 'other@example.com']))
        ->assertSessionHasErrors(['name' => 'That username is already taken.']);
});

it('rejects a duplicate email in any capitalisation', function () {
    User::factory()->create(['email' => 'mira@example.com']);

    $this->post('/register', registration(['name' => 'Someone']))->assertSessionHasErrors('email');
});

it('rejects reserved and malformed usernames', function (string $name, string $message) {
    $this->post('/register', registration(['name' => $name]))->assertSessionHasErrors(['name' => $message]);
})->with([
    'reserved' => ['Admin', 'That username is reserved.'],
    'reserved product name' => ['laraBB', 'That username is reserved.'],
    'too short' => ['ab', 'The username must be 3 to 30 letters, numbers, dots, dashes or underscores.'],
    'spaces' => ['two words', 'The username must be 3 to 30 letters, numbers, dots, dashes or underscores.'],
    'markup' => ['<script>', 'The username must be 3 to 30 letters, numbers, dots, dashes or underscores.'],
]);

it('validates the other fields', function () {
    $this->post('/register', registration(['email' => 'nope', 'password' => 'short', 'password_confirmation' => 'other', 'timezone' => 'Mars/Base']))
        ->assertSessionHasErrors(['email', 'password', 'timezone']);
});

it('turns away bots that fill in the hidden field', function () {
    $this->post('/register', registration(['website_url' => 'http://spam.example']))->assertSessionHasErrors('website_url');

    expect(User::count())->toBe(0);
});

it('sends people who are already logged in home', function () {
    $this->actingAs(User::factory()->create())->get('/register')->assertRedirect('/');
});

it('has the four system groups from the start', function () {
    expect(Group::orderBy('slug')->pluck('slug')->all())->toBe(['administrators', 'global-moderators', 'guests', 'registered']);
});

<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

function member(array $attributes = []): User
{
    return User::factory()->create($attributes);
}

function profile(array $overrides = []): array
{
    return $overrides + [
        'timezone' => 'Asia/Tokyo', 'preferred_mode' => 'dark', 'preferred_theme' => 'default', 'preferred_editor' => 'bbcode',
        'signature' => 'Keep it simple', 'about' => "Line one\nLine two", 'location' => 'Osaka', 'website' => 'https://example.com',
    ];
}

it('sends guests to the login page', function () {
    $this->get('/account')->assertRedirect(route('login'));
    $this->put('/account', profile())->assertRedirect(route('login'));
    $this->put('/account/password', [])->assertRedirect(route('login'));
});

it('shows the member their profile settings', function () {
    $this->actingAs(member(['name' => 'mira', 'location' => 'Berlin']))->get('/account')
        ->assertOk()->assertSee('Your profile')->assertSee('Berlin')->assertSee('Change your password');
});

it('saves the profile', function () {
    $user = member();

    $this->actingAs($user)->put('/account', profile())->assertRedirect()->assertSessionHas('status', 'Your profile is saved.');

    expect($user->fresh())
        ->timezone->toBe('Asia/Tokyo')->preferred_mode->toBe('dark')->preferred_theme->toBe('default')
        ->preferred_editor->toBe('bbcode')->signature->toBe('Keep it simple')->location->toBe('Osaka')->website->toBe('https://example.com');
});

it('lets optional fields be cleared', function () {
    $user = member(['location' => 'Berlin', 'website' => 'https://old.example', 'preferred_editor' => 'markdown']);

    $this->actingAs($user)->put('/account', profile(['location' => null, 'website' => null, 'preferred_editor' => null, 'preferred_theme' => null, 'signature' => null, 'about' => null]))
        ->assertSessionDoesntHaveErrors();

    expect($user->fresh())->location->toBeNull()->website->toBeNull()->preferred_editor->toBeNull();
});

it('validates the profile', function () {
    $this->actingAs(member())->put('/account', [
        'timezone' => 'Mars/Base', 'preferred_mode' => 'neon', 'preferred_theme' => 'evil', 'preferred_editor' => 'html',
        'signature' => str_repeat('x', 256), 'about' => str_repeat('x', 2001), 'location' => str_repeat('x', 101), 'website' => 'javascript:alert(1)',
    ])->assertSessionHasErrors(['timezone', 'preferred_mode', 'preferred_theme', 'preferred_editor', 'signature', 'about', 'location', 'website']);
});

it('ignores attempts to set protected fields', function () {
    $user = member();

    $this->actingAs($user)->put('/account', profile([
        'is_founder' => 1, 'status' => 'inactive', 'primary_group_id' => 4, 'name' => 'renamed', 'email' => 'new@example.com', 'username_clean' => 'hacked',
    ]))->assertSessionDoesntHaveErrors();

    $fresh = $user->fresh();
    expect($fresh->is_founder)->toBeFalse()->and($fresh->status)->toBe('active')->and($fresh->primary_group_id)->toBeNull()
        ->and($fresh->name)->toBe($user->name)->and($fresh->email)->toBe($user->email)->and($fresh->username_clean)->toBe(strtolower($user->name));
});

it('changes the password', function () {
    $user = member(['remember_token' => 'old']);

    $this->actingAs($user)->put('/account/password', [
        'current_password' => 'password', 'password' => 'a-brand-new-passphrase', 'password_confirmation' => 'a-brand-new-passphrase',
    ])->assertSessionHas('status', 'Your password is changed.');

    expect(Hash::check('a-brand-new-passphrase', $user->fresh()->password))->toBeTrue()->and($user->fresh()->remember_token)->not->toBe('old');
});

it('needs the current password to change it', function () {
    $user = member();

    $this->actingAs($user)->put('/account/password', [
        'current_password' => 'wrong', 'password' => 'a-brand-new-passphrase', 'password_confirmation' => 'a-brand-new-passphrase',
    ])->assertSessionHasErrors('current_password');

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

it('rejects a weak, unconfirmed or unchanged new password', function () {
    $this->actingAs(member())->put('/account/password', ['current_password' => 'password', 'password' => 'short', 'password_confirmation' => 'short'])
        ->assertSessionHasErrors('password');
    $this->put('/account/password', ['current_password' => 'password', 'password' => 'a-brand-new-passphrase', 'password_confirmation' => 'other'])
        ->assertSessionHasErrors('password');
    $this->put('/account/password', ['current_password' => 'password', 'password' => 'password', 'password_confirmation' => 'password'])
        ->assertSessionHasErrors('password');
});

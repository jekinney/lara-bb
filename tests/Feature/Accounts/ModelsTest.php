<?php

use App\Auth\RegistrationMode;
use App\Models\Group;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

it('keeps a lower-case copy of the username in step with the name', function () {
    $user = User::factory()->create(['name' => 'MiRa']);
    expect($user->username_clean)->toBe('mira');

    $user->update(['name' => 'Renamed']);
    expect($user->fresh()->username_clean)->toBe('renamed');
});

it('gives every member initials and a stable avatar colour', function () {
    $user = User::factory()->create(['name' => 'mira']);

    expect($user->initials())->toBe('MI')
        ->and($user->avatarTone())->toBeBetween(1, 6)
        ->and($user->avatarTone())->toBe($user->avatarTone());
    expect((new User(['name' => 'zed']))->avatarTone())->toBeBetween(1, 6);
});

it('knows whether an account is active', function () {
    expect(User::factory()->create()->isActive())->toBeTrue()
        ->and(User::factory()->awaitingEmail()->create()->isActive())->toBeFalse();
});

it('only fills the profile fields from user input', function () {
    $user = new User(['name' => 'x', 'status' => 'inactive', 'is_founder' => true, 'primary_group_id' => 4, 'username_clean' => 'y', 'location' => 'Berlin']);

    expect($user->location)->toBe('Berlin')
        ->and($user->status)->toBe('active')
        ->and($user->is_founder)->toBeFalse()
        ->and($user->primary_group_id)->toBeNull();
});

it('relates members to groups with leader and pending flags', function () {
    $user = User::factory()->create();
    $group = Group::create(['name' => 'Photographers', 'slug' => 'photographers']);
    $group->users()->attach($user->id, ['is_leader' => true]);

    expect($user->groups->first()->pivot->is_leader)->toBe(1)->and($user->groups->first()->pivot->is_pending)->toBe(0)
        ->and($group->users->pluck('id')->all())->toBe([$user->id]);
});

it('tells system groups from custom ones', function () {
    expect(Group::bySlug('administrators')->isSystem())->toBeTrue()
        ->and(Group::create(['name' => 'Custom', 'slug' => 'custom'])->fresh()->isSystem())->toBeFalse();
});

it('finds a group by slug or fails', function () {
    expect(Group::bySlug('registered')->name)->toBe('Registered users');

    Group::bySlug('nope');
})->throws(ModelNotFoundException::class);

it('reads the registration mode from settings', function (?string $stored, RegistrationMode $expected) {
    if ($stored !== null) {
        Setting::put('registration.mode', $stored);
    }

    expect(RegistrationMode::current())->toBe($expected);
})->with([
    'not set' => [null, RegistrationMode::Open],
    'open' => ['open', RegistrationMode::Open],
    'email' => ['email', RegistrationMode::Email],
    'closed' => ['closed', RegistrationMode::Closed],
    'nonsense falls back to open' => ['banana', RegistrationMode::Open],
]);

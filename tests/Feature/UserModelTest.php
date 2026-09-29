<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('hashes the password and hides secrets when serialised', function () {
    $user = User::factory()->create(['password' => 'a-long-passphrase']);

    expect(Hash::check('a-long-passphrase', $user->password))->toBeTrue()
        ->and($user->toArray())->not->toHaveKeys(['password', 'remember_token']);
});

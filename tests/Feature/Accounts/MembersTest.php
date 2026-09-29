<?php

use App\Models\Group;
use App\Models\User;

it('lists active members only, in name order', function () {
    User::factory()->create(['name' => 'zed']);
    User::factory()->create(['name' => 'amy']);
    User::factory()->awaitingEmail()->create(['name' => 'ghost']);
    User::factory()->create(['name' => 'gone', 'status' => 'inactive']);

    $this->get('/members')->assertOk()->assertSeeInOrder(['amy', 'zed'])->assertDontSee('ghost')->assertDontSee('gone');
});

it('pages the member list', function () {
    User::factory()->count(26)->create();

    $this->get('/members')->assertOk()->assertSee('1 of 2')->assertSee('Next')->assertSee('rel="next"', false);
    $this->get('/members?page=2')->assertOk()->assertSee('2 of 2')->assertSee('rel="prev"', false);
});

it('shows no pager for a short list', function () {
    User::factory()->count(2)->create();

    $this->get('/members')->assertOk()->assertDontSee('Pages');
});

it('shows a public profile, whatever the capitalisation in the address', function () {
    $user = User::factory()->create([
        'name' => 'Mira_K', 'location' => 'Berlin', 'website' => 'https://mira.example', 'about' => 'I like forums.', 'signature' => 'Be kind',
        'last_visit_at' => now()->subHours(3),
    ]);
    $user->groups()->attach(Group::bySlug('registered')->id);

    $this->get('/members/mIRA_k')->assertOk()
        ->assertSee('Mira_K')->assertSee('Berlin')->assertSee('I like forums.')->assertSee('Be kind')->assertSee('Registered users')
        ->assertSee('3 hours ago')
        ->assertSee('rel="nofollow ugc noopener"', false);
});

it('shows a profile with no optional details', function () {
    User::factory()->create(['name' => 'quiet']);

    $this->get('/members/quiet')->assertOk()->assertSee('Never')->assertDontSee('Location')->assertDontSee('Website')->assertDontSee('About');
});

it('hides hidden and pending groups', function () {
    $user = User::factory()->create(['name' => 'mira']);
    $secret = Group::create(['name' => 'Secret club', 'slug' => 'secret', 'is_hidden' => true]);
    $waiting = Group::create(['name' => 'Waiting list', 'slug' => 'waiting']);
    $open = Group::create(['name' => 'Photographers', 'slug' => 'photographers', 'color' => '#4ade80']);
    $user->groups()->attach($secret->id);
    $user->groups()->attach($waiting->id, ['is_pending' => true]);
    $user->groups()->attach($open->id);

    $this->get('/members/mira')->assertSee('Photographers')->assertSee('--c:#4ade80', false)->assertDontSee('Secret club')->assertDontSee('Waiting list');
});

it('answers 404 for members that do not exist or are not active', function () {
    User::factory()->awaitingEmail()->create(['name' => 'ghost']);

    $this->get('/members/nobody')->assertNotFound();
    $this->get('/members/ghost')->assertNotFound();
});

it('offers the edit button only on your own profile', function () {
    $mira = User::factory()->create(['name' => 'mira']);
    $other = User::factory()->create(['name' => 'other']);

    $this->get('/members/mira')->assertDontSee('Edit your profile');
    $this->actingAs($other)->get('/members/mira')->assertDontSee('Edit your profile');
    $this->actingAs($mira)->get('/members/mira')->assertSee('Edit your profile');
});

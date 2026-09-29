<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

it('shows the login form', function () {
    $this->get('/login')->assertOk()->assertSee('Log in')->assertSee('Forgot your password?');
});

it('logs in with a username in any capitalisation', function () {
    $user = User::factory()->create(['name' => 'Mira_K']);

    $this->post('/login', ['login' => 'MIRA_k', 'password' => 'password'])->assertRedirect('/');

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->last_visit_at)->not->toBeNull();
});

it('logs in with an email address', function () {
    $user = User::factory()->create(['email' => 'mira@example.com']);

    $this->post('/login', ['login' => 'Mira@Example.com', 'password' => 'password'])->assertRedirect('/');

    $this->assertAuthenticatedAs($user);
});

it('goes back to the page the visitor wanted', function () {
    User::factory()->create(['name' => 'mira']);

    $this->withSession(['url.intended' => url('/account')])
        ->post('/login', ['login' => 'mira', 'password' => 'password'])
        ->assertRedirect(url('/account'));
});

it('remembers the login when asked to', function () {
    User::factory()->create(['name' => 'mira']);

    $cookies = collect($this->post('/login', ['login' => 'mira', 'password' => 'password', 'remember' => '1'])->headers->getCookies())
        ->map->getName();

    expect($cookies->contains(fn (string $name) => str_starts_with($name, 'remember_web_')))->toBeTrue();
});

it('does not remember the login otherwise', function () {
    User::factory()->create(['name' => 'mira']);

    $cookies = collect($this->post('/login', ['login' => 'mira', 'password' => 'password'])->headers->getCookies())->map->getName();

    expect($cookies->contains(fn (string $name) => str_starts_with($name, 'remember_web_')))->toBeFalse();
});

it('gives the same error for a wrong password and an unknown login', function () {
    User::factory()->create(['name' => 'mira']);

    $wrong = $this->from('/login')->post('/login', ['login' => 'mira', 'password' => 'wrong']);
    $unknown = $this->from('/login')->post('/login', ['login' => 'nobody', 'password' => 'wrong']);

    $wrong->assertRedirect('/login')->assertSessionHasErrors(['login' => 'That username or password is not right.']);
    $unknown->assertRedirect('/login')->assertSessionHasErrors(['login' => 'That username or password is not right.']);
    $this->assertGuest();
});

it('locks a login out after five wrong passwords, even for the right one', function () {
    User::factory()->create(['name' => 'mira']);

    for ($i = 0; $i < 5; $i++) {
        $this->post('/login', ['login' => 'mira', 'password' => 'wrong']);
    }

    $this->post('/login', ['login' => 'mira', 'password' => 'password'])
        ->assertSessionHasErrors('login');
    expect(session('errors')->first('login'))->toStartWith('Too many attempts. Try again in');
    $this->assertGuest();
});

it('forgets the failed attempts after a good login', function () {
    User::factory()->create(['name' => 'mira']);
    $this->post('/login', ['login' => 'mira', 'password' => 'wrong']);

    $this->post('/login', ['login' => 'mira', 'password' => 'password']);

    expect(RateLimiter::attempts('mira|127.0.0.1'))->toBe(0);
});

it('will not log in a member who has not confirmed their email', function () {
    User::factory()->awaitingEmail()->create(['name' => 'mira']);

    $this->post('/login', ['login' => 'mira', 'password' => 'password'])
        ->assertSessionHasErrors(['login' => 'Confirm your email address first. Use the link we emailed you.']);
    $this->assertGuest();
});

it('will not log in an inactive account', function () {
    User::factory()->create(['name' => 'mira', 'status' => 'inactive']);

    $this->post('/login', ['login' => 'mira', 'password' => 'password'])
        ->assertSessionHasErrors(['login' => 'This account is not active.']);
    $this->assertGuest();
});

it('requires both fields', function () {
    $this->post('/login', [])->assertSessionHasErrors(['login', 'password']);
});

it('logs out', function () {
    $this->actingAs(User::factory()->create())->post('/logout')->assertRedirect('/');

    $this->assertGuest();
});

it('sends guests who log out to the login page', function () {
    $this->post('/logout')->assertRedirect(route('login'));
});

it('sends people who are already logged in home', function () {
    $this->actingAs(User::factory()->create())->get('/login')->assertRedirect('/');
});

it('shows the right navigation for guests and members', function () {
    $this->get('/')->assertSee('Log in')->assertSee('Register')->assertDontSee('Log out');

    $user = User::factory()->create(['name' => 'mira']);
    $this->actingAs($user)->get('/')->assertSee('Log out')->assertSee('Welcome back, mira')->assertSee('User control panel');
});

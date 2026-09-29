<?php

use App\Install\ProbeResult;
use App\Install\Requirements;
use App\Install\ServiceProbe;

beforeEach(fn () => uninstall());

/** A ServiceProbe that answers from a script and remembers how it was called. */
function fakeServices(bool $ok = true): object
{
    $fake = new class extends ServiceProbe
    {
        public bool $ok = true;

        /** @var list<array{string, array<mixed>}> */
        public array $calls = [];

        public function redis(array $input): ProbeResult
        {
            $this->calls[] = ['redis', $input];

            return new ProbeResult($this->ok, 'redis says '.($this->ok ? 'yes' : 'no'));
        }

        public function storage(array $config): ProbeResult
        {
            $this->calls[] = ['storage', $config];

            return new ProbeResult($this->ok, 'storage says '.($this->ok ? 'yes' : 'no'));
        }

        public function mail(array $transport, string $from, string $to): ProbeResult
        {
            $this->calls[] = ['mail', [$transport, $from, $to]];

            return new ProbeResult($this->ok, 'mail says '.($this->ok ? 'yes' : 'no'));
        }
    };
    $fake->ok = $ok;
    app()->instance(ServiceProbe::class, $fake);

    return $fake;
}

// ---- entry and guards ----

it('starts at the token step', function () {
    $this->get('/install')->assertRedirect(route('install.token'));
});

it('resumes at the first unfinished step', function () {
    $this->withSession(wizardSession('token', 'requirements'))
        ->get('/install')->assertRedirect(route('install.database'));
});

it('will not open a step before the earlier ones are done', function () {
    foreach (['requirements', 'database', 'services', 'site', 'review'] as $step) {
        $this->get("/install/{$step}")->assertRedirect(route('install.token'));
    }

    $this->post('/install/run')->assertRedirect(route('install.token'));
});

// ---- 1. token ----

it('issues a token and shows where to find it', function () {
    $this->get('/install/token')
        ->assertOk()
        ->assertSee('Confirm you own this server')
        ->assertSee(sandboxPath('install-token'));

    expect(sandboxPath('install-token'))->toBeFile();
});

it('accepts the right token and moves on', function () {
    $this->get('/install/token');
    $token = json_decode(file_get_contents(sandboxPath('install-token')), true)['token'];

    $this->post('/install/token', ['token' => strtolower($token)])
        ->assertRedirect(route('install.requirements'))
        ->assertSessionHas('install.token');
});

it('rejects a wrong token', function () {
    $this->get('/install/token');

    $this->from('/install/token')->post('/install/token', ['token' => 'AAAA-AAAA-AAAA'])
        ->assertRedirect('/install/token')
        ->assertSessionHasErrors('token')
        ->assertSessionMissing('install.token');
});

it('requires a token to be entered', function () {
    $this->post('/install/token', [])->assertSessionHasErrors('token');
});

it('rate limits token guessing', function () {
    $this->get('/install/token');

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post('/install/token', ['token' => 'AAAA-AAAA-AAAA'])->assertStatus(302);
    }

    $this->post('/install/token', ['token' => 'AAAA-AAAA-AAAA'])->assertStatus(429);
});

it('shows the token page again after it has been passed', function () {
    $this->withSession(wizardSession('token'))->get('/install/token')->assertOk();
});

// ---- 2. requirements ----

it('shows the server check', function () {
    $this->withSession(wizardSession('token'))->get('/install/requirements')
        ->assertOk()->assertSee('Server check')->assertSee('PHP 8.3.0 or newer');
});

it('continues when every check passes', function () {
    $this->withSession(wizardSession('token'))->post('/install/requirements')
        ->assertRedirect(route('install.database'))
        ->assertSessionHas('install.requirements');
});

it('stays put when a check fails', function () {
    app()->instance(Requirements::class, new Requirements(minPhp: '99.0.0'));

    $this->withSession(wizardSession('token'))->from('/install/requirements')->post('/install/requirements')
        ->assertRedirect('/install/requirements')
        ->assertSessionHasErrors('requirements')
        ->assertSessionMissing('install.requirements');
});

// ---- 3. database ----

it('shows the database form with sensible defaults', function () {
    $this->withSession(wizardSession('token', 'requirements'))->get('/install/database')
        ->assertOk()->assertSee('127.0.0.1')->assertSee('3306');
});

it('tests the connection without saving anything', function () {
    $this->withSession(wizardSession('token', 'requirements'))
        ->post('/install/database', ['driver' => 'sqlite', 'database' => sandboxPath('t.sqlite'), 'action' => 'test'])
        ->assertRedirect()
        ->assertSessionHas('probe.ok', true)
        ->assertSessionMissing('install.database');
});

it('saves a working database and continues', function () {
    $this->withSession(wizardSession('token', 'requirements'))
        ->post('/install/database', ['driver' => 'sqlite', 'database' => sandboxPath('t.sqlite'), 'action' => 'continue'])
        ->assertRedirect(route('install.services'))
        ->assertSessionHas('install.database.driver', 'sqlite')
        ->assertSessionMissing('install.database.action');
});

it('does not continue with a database that fails', function () {
    $this->withSession(wizardSession('token', 'requirements'))
        ->post('/install/database', [
            'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 1, 'database' => 'x', 'username' => 'u', 'action' => 'continue',
        ])
        ->assertSessionHas('probe.ok', false)
        ->assertSessionMissing('install.database');
});

it('shows the result of a database test on the form', function () {
    $this->withSession(wizardSession('token', 'requirements'))
        ->withSession(['probe' => ['ok' => true, 'message' => 'Connected. Fine.', 'section' => 'database']])
        ->get('/install/database')->assertSee('Connected. Fine.');
});

it('validates the database form', function () {
    $this->withSession(wizardSession('token', 'requirements'))
        ->post('/install/database', ['driver' => 'mysql', 'tls' => '1', 'action' => 'continue'])
        ->assertSessionHasErrors(['host', 'port', 'database', 'username', 'ca_cert']);

    $this->withSession(wizardSession('token', 'requirements'))
        ->post('/install/database', ['driver' => 'oracle', 'action' => 'drop tables'])
        ->assertSessionHasErrors(['driver', 'action']);
});

it('does not ask SQLite for a host or credentials', function () {
    $this->withSession(wizardSession('token', 'requirements'))
        ->post('/install/database', ['driver' => 'sqlite', 'database' => sandboxPath('s.sqlite'), 'tls' => '1', 'action' => 'test'])
        ->assertSessionDoesntHaveErrors();
});

// ---- 4. services ----

it('shows the services form', function () {
    $this->withSession(wizardSession('token', 'requirements', 'database'))->get('/install/services')
        ->assertOk()->assertSee('Cache, sessions and queue')->assertSee('nyc3');
});

it('continues with the built-in services', function () {
    $this->withSession(wizardSession('token', 'requirements', 'database'))
        ->post('/install/services', ['cache_driver' => 'database', 'mail_mailer' => 'log', 'mail_from' => 'noreply@example.com', 'storage' => 'local', 'action' => 'continue'])
        ->assertRedirect(route('install.site'))
        ->assertSessionHas('install.services.storage', 'local');
});

it('checks Redis and S3 before continuing with them', function () {
    $fake = fakeServices();
    $payload = [
        'cache_driver' => 'redis', 'redis_host' => 'redis', 'redis_port' => 6379,
        'mail_mailer' => 'smtp', 'mail_from' => 'noreply@example.com', 'mail_host' => 'smtp.example.com', 'mail_port' => 587, 'mail_encryption' => 'ssl',
        'storage' => 's3', 's3_key' => 'k', 's3_secret' => 's', 's3_region' => 'nyc3', 's3_bucket' => 'forum', 's3_endpoint' => 'https://nyc3.digitaloceanspaces.com',
        'action' => 'continue',
    ];

    $this->withSession(wizardSession('token', 'requirements', 'database'))->post('/install/services', $payload)
        ->assertRedirect(route('install.site'));

    expect(array_column($fake->calls, 0))->toBe(['redis', 'storage'])
        ->and($fake->calls[1][1])->toMatchArray(['driver' => 's3', 'bucket' => 'forum', 'endpoint' => 'https://nyc3.digitaloceanspaces.com']);
});

it('will not continue when Redis or storage fails', function () {
    fakeServices(ok: false);

    $this->withSession(wizardSession('token', 'requirements', 'database'))
        ->post('/install/services', ['cache_driver' => 'redis', 'redis_host' => 'redis', 'redis_port' => 6379, 'mail_mailer' => 'log', 'mail_from' => 'a@b.co', 'storage' => 'local', 'action' => 'continue'])
        ->assertSessionHas('probe.message', 'redis says no')
        ->assertSessionMissing('install.services');

    $this->withSession(wizardSession('token', 'requirements', 'database'))
        ->post('/install/services', ['cache_driver' => 'database', 'mail_mailer' => 'log', 'mail_from' => 'a@b.co', 'storage' => 's3', 's3_key' => 'k', 's3_secret' => 's', 's3_region' => 'r', 's3_bucket' => 'b', 'action' => 'continue'])
        ->assertSessionHas('probe.message', 'storage says no')
        ->assertSessionMissing('install.services');
});

it('runs each service test on its own', function () {
    $fake = fakeServices();
    $base = ['cache_driver' => 'redis', 'redis_host' => 'redis', 'redis_port' => 6379, 'mail_mailer' => 'smtp', 'mail_from' => 'noreply@example.com', 'mail_host' => 'smtp', 'mail_port' => 25, 'mail_username' => 'u', 'mail_password' => 'p', 'storage' => 'local'];

    $this->withSession(wizardSession('token', 'requirements', 'database'))->post('/install/services', $base + ['action' => 'test_redis'])->assertSessionHas('probe.message', 'redis says yes');
    $this->withSession(wizardSession('token', 'requirements', 'database'))->post('/install/services', $base + ['action' => 'test_storage'])->assertSessionHas('probe.message', 'storage says yes');
    $this->withSession(wizardSession('token', 'requirements', 'database'))->post('/install/services', $base + ['action' => 'test_mail', 'test_email' => 'mira@example.com'])->assertSessionHas('probe.message', 'mail says yes');

    expect($fake->calls[1][1])->toBe(['driver' => 'local', 'root' => storage_path('app/private')])
        ->and($fake->calls[2][1][0])->toMatchArray(['transport' => 'smtp', 'host' => 'smtp', 'port' => 25, 'scheme' => 'smtp'])
        ->and($fake->calls[2][1][2])->toBe('mira@example.com');
});

it('tests the log mailer without SMTP settings', function () {
    $fake = fakeServices();

    $this->withSession(wizardSession('token', 'requirements', 'database'))
        ->post('/install/services', ['cache_driver' => 'database', 'mail_mailer' => 'log', 'mail_from' => 'noreply@example.com', 'storage' => 'local', 'action' => 'test_mail', 'test_email' => 'mira@example.com']);

    expect($fake->calls[0][1][0])->toBe(['transport' => 'log']);
});

it('validates the services form', function () {
    $this->withSession(wizardSession('token', 'requirements', 'database'))
        ->post('/install/services', ['cache_driver' => 'redis', 'mail_mailer' => 'smtp', 'storage' => 's3', 'action' => 'test_mail'])
        ->assertSessionHasErrors(['redis_host', 'redis_port', 'mail_from', 'mail_host', 'mail_port', 's3_key', 's3_secret', 's3_region', 's3_bucket', 'test_email']);
});

// ---- 5. site ----

it('shows the board and founder form with defaults', function () {
    $this->withSession(wizardSession('token', 'requirements', 'database', 'services'))->get('/install/site')
        ->assertOk()->assertSee('Founder account')->assertSee('laraBB')->assertSee('Europe/Berlin');
});

it('never puts the founder password back into the form', function () {
    $this->withSession(wizardSession('token', 'requirements', 'database', 'services', 'site'))->get('/install/site')
        ->assertOk()->assertDontSee('a-long-passphrase-42');
});

it('saves the board and founder and goes to the review', function () {
    $this->withSession(wizardSession('token', 'requirements', 'database', 'services'))
        ->post('/install/site', wizardData()['site'] + ['founder_password_confirmation' => 'a-long-passphrase-42'])
        ->assertRedirect(route('install.review'))
        ->assertSessionHas('install.site.founder_username', 'mira');
});

it('validates the board and founder form', function () {
    $this->withSession(wizardSession('token', 'requirements', 'database', 'services'))
        ->post('/install/site', [
            'board_name' => '', 'board_url' => 'nope', 'timezone' => 'Mars/Base', 'theme' => 'other', 'editor' => 'html',
            'founder_username' => 'a b', 'founder_email' => 'x', 'founder_password' => 'short', 'founder_password_confirmation' => 'different',
        ])
        ->assertSessionHasErrors(['board_name', 'board_url', 'timezone', 'theme', 'editor', 'founder_username', 'founder_email', 'founder_password']);
});

// ---- 6. review ----

it('summarises the choices without secrets', function () {
    $this->withSession(wizardSession('token', 'requirements', 'database', 'services', 'site'))->get('/install/review')
        ->assertOk()->assertSee('Ready to install')->assertSee('Test Board')->assertSee('mira@example.com')
        ->assertDontSee('a-long-passphrase-42');
});

it('summarises Redis, SMTP and S3 choices', function () {
    $session = wizardSession('token', 'requirements', 'database', 'services', 'site');
    $session['install']['database'] = ['driver' => 'mysql', 'host' => 'db.example.com', 'port' => 25060, 'database' => 'larabb'];
    $session['install']['services'] = [
        'cache_driver' => 'redis', 'redis_host' => 'redis.example.com', 'mail_mailer' => 'smtp', 'mail_host' => 'smtp.example.com',
        'storage' => 's3', 's3_bucket' => 'forum-files', 'mail_from' => 'noreply@example.com',
    ];

    $this->withSession($session)->get('/install/review')
        ->assertSee('mysql at db.example.com:25060')->assertSee('Redis at redis.example.com')
        ->assertSee('SMTP at smtp.example.com')->assertSee('S3 bucket forum-files');
});

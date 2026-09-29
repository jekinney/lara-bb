<?php

use App\Install\EnvironmentFile;
use App\Install\EnvWriter;
use App\Install\InstallState;
use App\Install\ProbeResult;
use App\Install\Requirements;
use App\Install\SetupToken;
use App\Install\Wizard;
use App\Models\Setting;
use Dotenv\Dotenv;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

// ---- InstallState ----

it('knows whether laraBB is installed from the lock file', function () {
    $state = new InstallState;
    expect($state->isInstalled())->toBeTrue();

    uninstall();
    expect($state->isInstalled())->toBeFalse();
});

it('writes the lock file, creating its directory', function () {
    $nested = sandboxPath('deep/er/installed');
    config(['larabb.install.lock_file' => $nested]);

    (new InstallState)->markInstalled();

    expect($nested)->toBeFile()
        ->and((new InstallState)->isInstalled())->toBeTrue();
});

// ---- SetupToken ----

it('issues a formatted token, stores it and logs it', function () {
    Log::shouldReceive('warning')->once()->withArgs(fn (string $m) => str_contains($m, 'laraBB setup token: '));

    $token = (new SetupToken)->ensure();

    expect($token)->toMatch('/^[2-9A-HJKMNP-Z]{4}-[2-9A-HJKMNP-Z]{4}-[2-9A-HJKMNP-Z]{4}$/')
        ->and(json_decode(file_get_contents(sandboxPath('install-token')), true)['token'])->toBe($token);
});

it('keeps the same token until it expires, then issues a new one', function () {
    $tokens = new SetupToken;
    $first = $tokens->ensure();

    expect($tokens->ensure())->toBe($first);

    Carbon::setTestNow(now()->addMinutes(31));
    expect($tokens->ensure())->not->toBe($first);
    Carbon::setTestNow();
});

it('verifies a token ignoring case, dashes and spaces', function () {
    $tokens = new SetupToken;
    $token = $tokens->ensure();

    expect($tokens->verify(strtolower(str_replace('-', ' ', $token))))->toBeTrue()
        ->and($tokens->verify('AAAA-AAAA-AAAA'))->toBeFalse();
});

it('refuses every token when none has been issued or it has expired', function () {
    $tokens = new SetupToken;
    expect($tokens->verify('anything'))->toBeFalse();

    $token = $tokens->ensure();
    Carbon::setTestNow(now()->addMinutes(31));
    expect($tokens->verify($token))->toBeFalse();
    Carbon::setTestNow();
});

it('reissues when the token file is corrupt', function () {
    File::put(sandboxPath('install-token'), 'not json');
    expect((new SetupToken)->verify('x'))->toBeFalse();

    File::put(sandboxPath('install-token'), json_encode(['token' => 5]));
    expect((new SetupToken)->ensure())->toBeString();
});

it('forgets the token', function () {
    $tokens = new SetupToken;
    $tokens->ensure();
    $tokens->forget();

    expect(sandboxPath('install-token'))->not->toBeFile();
});

// ---- Requirements ----

function secureRequest(): Request
{
    return Request::create('https://forum.example.com/install');
}

it('passes on a healthy server', function () {
    $checks = (new Requirements)->evaluate(secureRequest());

    expect($checks)->toHaveCount(6)
        ->and((new Requirements)->passes($checks))->toBeTrue();
});

it('fails when PHP is too old, an extension is missing or a path is not writable', function () {
    $requirements = new Requirements(minPhp: '99.0.0', extensions: ['not_a_real_extension'], writable: [sandboxPath('missing')]);
    $checks = $requirements->evaluate(secureRequest());
    $byStatus = array_count_values(array_column($checks, 'status'));

    expect($byStatus['fail'])->toBe(3)
        ->and($requirements->passes($checks))->toBeFalse();
});

it('refuses plain HTTP in production unless a proxy is trusted', function () {
    $this->app['env'] = 'production';
    $http = Request::create('http://forum.example.com/install');

    $blocked = (new Requirements)->evaluate($http)[4];
    config(['larabb.install.allow_http' => true]);
    $allowed = (new Requirements)->evaluate($http)[4];

    expect($blocked['status'])->toBe('fail')
        ->and($allowed['status'])->toBe('warn');
});

it('only warns about plain HTTP outside production', function () {
    expect((new Requirements)->evaluate(Request::create('http://localhost/install'))[4]['status'])->toBe('warn');
});

it('checks the env file itself when it already exists', function () {
    File::put(sandboxPath('.env'), 'APP_NAME=x');

    expect((new Requirements)->evaluate(secureRequest())[3]['status'])->toBe('ok');
});

// ---- EnvWriter ----

it('creates the file from a template and appends new keys', function () {
    (new EnvWriter)->write(sandboxPath('new.env'), ['APP_NAME' => 'laraBB', 'DEBUG' => false, 'PORT' => 3306, 'EMPTY' => null], "KEEP=1\n");

    expect(file_get_contents(sandboxPath('new.env')))->toBe("KEEP=1\nAPP_NAME=laraBB\nDEBUG=false\nPORT=3306\nEMPTY=\n");
});

it('replaces existing keys and leaves other lines alone', function () {
    File::put(sandboxPath('.env'), "# comment\r\nAPP_NAME=Old\r\nexport DB_HOST=old\r\nOTHER=stay\r\n");

    (new EnvWriter)->write(sandboxPath('.env'), ['APP_NAME' => 'New', 'DB_HOST' => 'db']);

    expect(file_get_contents(sandboxPath('.env')))->toBe("# comment\nAPP_NAME=New\nDB_HOST=db\nOTHER=stay\n");
});

it('starts empty when there is no file and no template', function () {
    (new EnvWriter)->write(sandboxPath('bare.env'), ['A' => 'b']);

    expect(file_get_contents(sandboxPath('bare.env')))->toBe("A=b\n");
});

it('quotes awkward values so they read back exactly', function () {
    $values = ['PASSWORD' => 'p@ss w"or$d#1\\x\'y', 'NAME' => 'My Board', 'PLAIN' => 'a-b_c.d:e/f'];
    (new EnvWriter)->write(sandboxPath('q.env'), $values);

    expect(Dotenv::parse(file_get_contents(sandboxPath('q.env'))))->toBe($values);
});

it('refuses values with line breaks', function () {
    (new EnvWriter)->write(sandboxPath('x.env'), ['A' => "one\nAPP_KEY=stolen"]);
})->throws(InvalidArgumentException::class, 'line break');

it('keeps the file private', function () {
    (new EnvWriter)->write(sandboxPath('p.env'), ['A' => 'b']);

    expect(decoct(fileperms(sandboxPath('p.env')) & 0777))->toBe('600');
})->skip(PHP_OS_FAMILY === 'Windows', 'Windows has no Unix file modes');

// ---- EnvironmentFile ----

/** Building an Application replaces the global container, so put the running one back afterwards. */
function withScratchApplication(Closure $test): void
{
    $running = Container::getInstance();

    try {
        $test(new Application(base_path()));
    } finally {
        Container::setInstance($running);
    }
}

it('leaves the application alone without a path', function () {
    withScratchApplication(function (Application $app) {
        $before = $app->environmentFilePath();

        expect(EnvironmentFile::apply($app, null)->environmentFilePath())->toBe($before)
            ->and(EnvironmentFile::apply($app, '')->environmentFilePath())->toBe($before);
    });
});

it('points the application at an env file elsewhere', function () {
    withScratchApplication(function (Application $app) {
        $app = EnvironmentFile::apply($app, '/data/laravel/.env');

        expect($app->environmentPath())->toBe('/data/laravel')
            ->and($app->environmentFile())->toBe('.env');
    });
});

// ---- ProbeResult ----

it('builds ok and failed probe results', function () {
    expect(ProbeResult::ok('yes')->ok)->toBeTrue()
        ->and(ProbeResult::fail('no')->message)->toBe('no');
});

// ---- Wizard ----

function wizard(): Wizard
{
    return new Wizard(new Store('test', new ArraySessionHandler(10)));
}

it('starts at the token step and moves forward as steps complete', function () {
    $wizard = wizard();
    expect($wizard->current())->toBe('token')->and($wizard->isDone('token'))->toBeFalse();

    $wizard->complete('token');
    expect($wizard->current())->toBe('requirements')->and($wizard->isDone('token'))->toBeTrue();
});

it('only allows a step once the earlier ones are done', function () {
    $wizard = wizard();

    expect($wizard->allows('token'))->toBeTrue()
        ->and($wizard->allows('database'))->toBeFalse()
        ->and($wizard->allows('nonsense'))->toBeFalse();

    $wizard->complete('token');
    $wizard->complete('requirements');
    expect($wizard->allows('database'))->toBeTrue();
});

it('never allows a step that does not exist', function () {
    $wizard = wizard();
    foreach (Wizard::STEPS as $step) {
        $wizard->complete($step);
    }

    expect($wizard->allows('nonsense'))->toBeFalse();
});

it('stores and returns what was entered', function () {
    $wizard = wizard();
    $wizard->complete('database', ['driver' => 'sqlite']);

    expect($wizard->data('database'))->toBe(['driver' => 'sqlite'])
        ->and($wizard->data('services'))->toBe([])
        ->and($wizard->collected())->toBe(['database' => ['driver' => 'sqlite'], 'services' => [], 'site' => []]);
});

it('stays on review once everything is done, and can be reset', function () {
    $wizard = wizard();
    foreach (Wizard::STEPS as $step) {
        $wizard->complete($step);
    }
    expect($wizard->current())->toBe('review');

    $wizard->reset();
    expect($wizard->current())->toBe('token');
});

// ---- Setting ----

it('reads and writes settings', function () {
    expect(Setting::read('board.name', 'fallback'))->toBe('fallback');

    Setting::put('board.name', 'laraBB');
    Setting::put('board.name', 'Renamed');
    Setting::put('board.flags', ['a' => 1]);

    expect(Setting::read('board.name'))->toBe('Renamed')
        ->and(Setting::read('board.flags'))->toBe(['a' => 1]);
});

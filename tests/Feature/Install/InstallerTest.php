<?php

use App\Install\DatabaseProbe;
use App\Install\Installer;
use App\Install\InstallFailed;
use App\Install\ProbeResult;
use App\Install\SetupToken;
use Dotenv\Dotenv;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    uninstall();
    Log::spy();
});

function boardDb(): PDO
{
    return new PDO('sqlite:'.sandboxPath('board.sqlite'));
}

function tableCount(): int
{
    return (int) boardDb()->query("select count(*) from sqlite_master where type = 'table'")->fetchColumn();
}

/** The values Laravel would read back from the env file the installer wrote. */
function envValues(): array
{
    return Dotenv::parse((string) file_get_contents(sandboxPath('.env')));
}

it('installs the board end to end', function () {
    (new SetupToken)->ensure();

    $this->withSession(['install' => wizardData()])->post('/install/run')
        ->assertOk()
        ->assertSee('laraBB is ready')
        ->assertSee('Created the founder account')
        ->assertSee('Locked the installer');

    $founder = boardDb()->query('select * from users')->fetch(PDO::FETCH_ASSOC);
    $settings = boardDb()->query('select key, value from settings order by key')->fetchAll(PDO::FETCH_KEY_PAIR);

    expect($founder)->toMatchArray(['name' => 'mira', 'email' => 'mira@example.com', 'is_founder' => 1])
        ->and(Hash::check('a-long-passphrase-42', $founder['password']))->toBeTrue()
        ->and($founder['email_verified_at'])->not->toBeNull()
        ->and($settings)->toBe([
            'board.default_editor' => '"bbcode"',
            'board.default_theme' => '"default"',
            'board.name' => '"Test Board"',
            'board.timezone' => '"Europe\/Berlin"',
        ])
        ->and(sandboxPath('installed'))->toBeFile()
        ->and(sandboxPath('install-token'))->not->toBeFile();

    expect(envValues())->toMatchArray([
        'APP_NAME' => 'Test Board',
        'APP_URL' => 'https://forum.example.com',
        'DB_CONNECTION' => 'sqlite',
        'DB_DATABASE' => sandboxPath('board.sqlite'),
        'CACHE_STORE' => 'database',
        'MAIL_MAILER' => 'log',
        'FILESYSTEM_DISK' => 'local',
    ])
        ->and(envValues()['APP_KEY'])->toStartWith('base64:')
        ->and(file_get_contents(sandboxPath('.env')))->not->toContain('a-long-passphrase-42');
});

it('serves 404 from the installer once the install has finished', function () {
    $this->withSession(['install' => wizardData()])->post('/install/run')->assertOk();

    $this->get('/install')->assertNotFound();
    $this->post('/install/run')->assertNotFound();
});

it('writes Redis, SMTP, S3 and TLS settings to the env file', function () {
    $this->withSession(['install' => wizardData([
        'database' => ['tls' => true, 'ca_cert' => "-----BEGIN CERTIFICATE-----\nabc\n-----END CERTIFICATE-----"],
        'services' => [
            'cache_driver' => 'redis', 'redis_host' => 'redis.example.com', 'redis_port' => 6380, 'redis_password' => 'r$dis "pw"',
            'mail_mailer' => 'smtp', 'mail_host' => 'smtp.example.com', 'mail_port' => 465, 'mail_username' => 'mailer', 'mail_password' => 'm@il', 'mail_encryption' => 'ssl',
            'storage' => 's3', 's3_key' => 'KEY', 's3_secret' => 'SECRET', 's3_region' => 'nyc3', 's3_bucket' => 'forum', 's3_endpoint' => 'https://nyc3.digitaloceanspaces.com',
        ],
    ])])->post('/install/run')->assertOk();

    expect(envValues())->toMatchArray([
        'CACHE_STORE' => 'redis', 'SESSION_DRIVER' => 'redis', 'QUEUE_CONNECTION' => 'redis',
        'REDIS_HOST' => 'redis.example.com', 'REDIS_PORT' => '6380', 'REDIS_PASSWORD' => 'r$dis "pw"',
        'MAIL_MAILER' => 'smtp', 'MAIL_HOST' => 'smtp.example.com', 'MAIL_SCHEME' => 'smtps', 'MAIL_USERNAME' => 'mailer', 'MAIL_PASSWORD' => 'm@il',
        'FILESYSTEM_DISK' => 's3', 'AWS_ACCESS_KEY_ID' => 'KEY', 'AWS_BUCKET' => 'forum', 'AWS_ENDPOINT' => 'https://nyc3.digitaloceanspaces.com',
        'MYSQL_ATTR_SSL_CA' => sandboxPath().DIRECTORY_SEPARATOR.'db-ca.pem',
    ]);

    expect(sandboxPath('db-ca.pem'))->toBeFile();
});

it('writes MySQL connection details for a real server', function () {
    $probe = new class extends DatabaseProbe
    {
        public function test(array $input): ProbeResult
        {
            return ProbeResult::ok('pretend');
        }

        public function configFor(array $input, ?string $caPath = null): array
        {
            return ['driver' => 'sqlite', 'database' => sandboxPath('board.sqlite'), 'prefix' => ''];
        }
    };
    $this->app->instance(DatabaseProbe::class, $probe);

    $this->withSession(['install' => wizardData([
        'database' => ['driver' => 'mysql', 'host' => 'db.example.com', 'port' => 25060, 'database' => 'larabb', 'username' => 'app', 'password' => 'p@ss'],
    ])])->post('/install/run')->assertOk();

    expect(envValues())->toMatchArray([
        'DB_CONNECTION' => 'mysql', 'DB_HOST' => 'db.example.com', 'DB_PORT' => '25060', 'DB_USERNAME' => 'app', 'DB_PASSWORD' => 'p@ss',
    ]);
});

it('removes the installer files when asked to', function () {
    File::ensureDirectoryExists(sandboxPath('views/install'));
    File::put(sandboxPath('views/install/layout.blade.php'), 'x');
    File::put(sandboxPath('stray.txt'), 'x');
    config([
        'larabb.install.remove_files' => true,
        'larabb.install.removable' => [sandboxPath('views/install'), sandboxPath('stray.txt')],
    ]);

    $this->withSession(['install' => wizardData()])->post('/install/run')->assertSee('Removed the installer files');

    expect(sandboxPath('views/install'))->not->toBeDirectory()
        ->and(sandboxPath('stray.txt'))->not->toBeFile();
});

it('leaves the installer files in place otherwise', function () {
    File::ensureDirectoryExists(sandboxPath('views/install'));
    config(['larabb.install.removable' => [sandboxPath('views/install')]]);

    $this->withSession(['install' => wizardData()])->post('/install/run')->assertDontSee('Removed the installer files');

    expect(sandboxPath('views/install'))->toBeDirectory();
});

// ---- refusals ----

it('refuses a database that already has tables', function () {
    $pdo = boardDb();
    $pdo->exec('create table users (id integer)');
    $pdo = null;

    $this->withSession(['install' => wizardData()])->from('/install/review')->post('/install/run')
        ->assertRedirect('/install/review')
        ->assertSessionHasErrors(['install' => 'This database already contains laraBB tables (users). Use an empty database.']);

    expect(sandboxPath('installed'))->not->toBeFile()->and(sandboxPath('.env'))->not->toBeFile();
});

it('refuses to run while another install holds the lock', function () {
    $holder = fopen(sandboxPath('installing'), 'c');
    flock($holder, LOCK_EX);

    $this->withSession(['install' => wizardData()])->from('/install/review')->post('/install/run')
        ->assertSessionHasErrors(['install' => 'Another installation is already running. Wait for it to finish.']);

    flock($holder, LOCK_UN);
    fclose($holder);
    expect(sandboxPath('installed'))->not->toBeFile();
});

it('refuses to run when already installed', function () {
    File::put(sandboxPath('installed'), 'x');

    app(Installer::class)->run(wizardData());
})->throws(InstallFailed::class, 'already installed');

// ---- rollback ----

it('rolls back completely when creating the founder fails', function () {
    $this->withSession(['install' => wizardData(['site' => ['founder_email' => null]])])->from('/install/review')->post('/install/run')
        ->assertSessionHasErrors('install');

    expect(tableCount())->toBe(0)
        ->and(sandboxPath('installed'))->not->toBeFile()
        ->and(sandboxPath('.env'))->not->toBeFile();
});

it('rolls back and removes the CA file when the configuration cannot be written', function () {
    config(['larabb.install.env_file' => sandboxPath('missing-dir/.env')]);

    $this->withSession(['install' => wizardData(['database' => ['tls' => true, 'ca_cert' => 'PEM']])])
        ->from('/install/review')->post('/install/run')
        ->assertSessionHasErrors('install');

    expect(tableCount())->toBe(0)
        ->and(sandboxPath('db-ca.pem'))->not->toBeFile()
        ->and(sandboxPath('installed'))->not->toBeFile();
});

it('still reports failure when the rollback itself cannot clear the database', function () {
    $this->app->instance(DatabaseProbe::class, new class extends DatabaseProbe
    {
        public function test(array $input): ProbeResult
        {
            return ProbeResult::ok('pretend');
        }

        public function configFor(array $input, ?string $caPath = null): array
        {
            return ['driver' => 'sqlite', 'database' => sandboxPath('no/such/dir/board.sqlite'), 'prefix' => ''];
        }
    });

    $this->withSession(['install' => wizardData()])->from('/install/review')->post('/install/run')
        ->assertSessionHasErrors('install');

    expect(sandboxPath('installed'))->not->toBeFile();
});

it('reports a migration that exits with an error', function () {
    Artisan::shouldReceive('call')->once()->andReturn(1);
    Artisan::shouldReceive('output')->once()->andReturn('Migration failed.');

    $this->withSession(['install' => wizardData()])->from('/install/review')->post('/install/run')
        ->assertSessionHasErrors('install');

    expect(tableCount())->toBe(0)->and(sandboxPath('installed'))->not->toBeFile();
});

<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->app['env'] = 'local';

    // A separate SQLite board with tables in it, so the test never drops the test database's own tables.
    config(['database.connections.larabb_uninstall' => ['driver' => 'sqlite', 'database' => sandboxPath('board.sqlite'), 'prefix' => '']]);
    touch(sandboxPath('board.sqlite'));
    $pdo = new PDO('sqlite:'.sandboxPath('board.sqlite'));
    $pdo->exec('create table users (id integer)');
    $pdo->exec('create table settings (id integer)');

    foreach (['install-token', 'install-key', 'installing', 'db-ca.pem', '.env'] as $file) {
        File::put(sandboxPath($file), 'x');
    }
});

function tables(): array
{
    return (new PDO('sqlite:'.sandboxPath('board.sqlite')))->query("select name from sqlite_master where type = 'table'")->fetchAll(PDO::FETCH_COLUMN);
}

it('empties the database and removes the installer lock and files', function () {
    $this->artisan('larabb:uninstall', ['--force' => true, '--database' => 'larabb_uninstall'])
        ->expectsOutputToContain('Dropped every table.')
        ->expectsOutputToContain('laraBB is uninstalled')
        ->assertSuccessful();

    expect(tables())->toBe([]);
    foreach (['installed', 'install-token', 'install-key', 'installing', 'db-ca.pem'] as $file) {
        expect(sandboxPath($file))->not->toBeFile();
    }
});

it('keeps the env file unless asked to delete it', function () {
    $this->artisan('larabb:uninstall', ['--force' => true, '--database' => 'larabb_uninstall'])->assertSuccessful();

    expect(sandboxPath('.env'))->toBeFile();
});

it('deletes the env file when asked to', function () {
    $this->artisan('larabb:uninstall', ['--force' => true, '--database' => 'larabb_uninstall', '--delete-env' => true])
        ->expectsOutputToContain('Deleted the env file.')
        ->assertSuccessful();

    expect(sandboxPath('.env'))->not->toBeFile();
});

it('falls back to the app env file location when none is configured', function () {
    config(['larabb.install.env_file' => null]);
    $this->app->useEnvironmentPath(sandboxPath());

    $this->artisan('larabb:uninstall', ['--force' => true, '--skip-database' => true, '--delete-env' => true])->assertSuccessful();

    expect(sandboxPath('.env'))->not->toBeFile();
});

it('leaves the database alone with --skip-database', function () {
    $this->artisan('larabb:uninstall', ['--force' => true, '--skip-database' => true])
        ->doesntExpectOutputToContain('Dropped every table.')
        ->assertSuccessful();

    expect(tables())->toBe(['users', 'settings'])->and(sandboxPath('installed'))->not->toBeFile();
});

it('asks before doing anything and stops when the answer is no', function () {
    $this->artisan('larabb:uninstall', ['--database' => 'larabb_uninstall'])
        ->expectsConfirmation('This deletes every table in the database and unlocks the installer. Continue?', 'no')
        ->expectsOutputToContain('Nothing was changed.')
        ->assertSuccessful();

    expect(tables())->toBe(['users', 'settings'])->and(sandboxPath('installed'))->toBeFile();
});

it('goes ahead when the answer is yes', function () {
    $this->artisan('larabb:uninstall', ['--database' => 'larabb_uninstall'])
        ->expectsConfirmation('This deletes every table in the database and unlocks the installer. Continue?', 'yes')
        ->assertSuccessful();

    expect(tables())->toBe([])->and(sandboxPath('installed'))->not->toBeFile();
});

it('refuses outside local development', function () {
    $this->app['env'] = 'production';

    $this->artisan('larabb:uninstall', ['--force' => true, '--database' => 'larabb_uninstall'])
        ->expectsOutputToContain('local development only')
        ->assertFailed();

    expect(tables())->toBe(['users', 'settings'])->and(sandboxPath('installed'))->toBeFile();
});

it('can be allowed on a development container with LARABB_ALLOW_UNINSTALL', function () {
    $this->app['env'] = 'production';
    config(['larabb.install.allow_uninstall' => true]);

    $this->artisan('larabb:uninstall', ['--force' => true, '--database' => 'larabb_uninstall'])->assertSuccessful();

    expect(sandboxPath('installed'))->not->toBeFile();
});

it('changes nothing when the database cannot be emptied', function () {
    config(['database.connections.larabb_uninstall' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 1, 'database' => 'x', 'username' => 'u', 'password' => 'p']]);

    $this->artisan('larabb:uninstall', ['--force' => true, '--database' => 'larabb_uninstall'])
        ->expectsOutputToContain('Could not empty the database')
        ->assertFailed();

    expect(sandboxPath('installed'))->toBeFile()->and(sandboxPath('install-token'))->toBeFile();
});

it('sends visitors back to the installer afterwards', function () {
    $this->artisan('larabb:uninstall', ['--force' => true, '--skip-database' => true])->assertSuccessful();

    $this->get('/')->assertRedirect('/install');
});

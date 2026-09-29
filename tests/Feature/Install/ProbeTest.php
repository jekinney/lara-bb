<?php

use App\Install\DatabaseProbe;
use App\Install\ServiceProbe;
use Illuminate\Redis\RedisManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

// ---- DatabaseProbe ----

it('builds a MySQL config with optional verified TLS', function () {
    $input = ['driver' => 'mysql', 'host' => 'db.example.com', 'port' => '25060', 'database' => 'larabb', 'username' => 'app', 'password' => 's3cret'];
    $probe = new DatabaseProbe;

    $plain = $probe->configFor($input);
    $tls = $probe->configFor($input, '/tmp/ca.pem');

    expect($plain)->toMatchArray(['driver' => 'mysql', 'host' => 'db.example.com', 'charset' => 'utf8mb4', 'strict' => true, 'options' => []])
        ->and($tls['options'][PDO::MYSQL_ATTR_SSL_CA])->toBe('/tmp/ca.pem')
        ->and($tls['options'][PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT])->toBeTrue()
        ->and($probe->configFor(['driver' => 'mariadb'] + $input)['password'])->toBe('s3cret')
        ->and($probe->configFor(['password' => null] + $input)['password'])->toBe('');
});

it('builds a SQLite config', function () {
    expect((new DatabaseProbe)->configFor(['driver' => 'sqlite', 'database' => '/data/board.sqlite']))
        ->toMatchArray(['driver' => 'sqlite', 'database' => '/data/board.sqlite', 'foreign_key_constraints' => true]);
});

it('accepts an empty database it can create tables in', function () {
    $result = (new DatabaseProbe)->test(['driver' => 'sqlite', 'database' => sandboxPath('new/dir/board.sqlite')]);

    expect($result->ok)->toBeTrue()
        ->and($result->message)->toContain('Connected. sqlite')->toContain('Nothing has been saved')
        ->and(sandboxPath('new/dir/board.sqlite'))->toBeFile()
        ->and(array_keys(DB::getConnections()))->not->toContain('larabb_probe');
});

it('cleans up the temporary CA file it writes for TLS', function () {
    $before = count(glob(sys_get_temp_dir().DIRECTORY_SEPARATOR.'larabb-ca-*'));

    (new DatabaseProbe)->test(['driver' => 'sqlite', 'database' => sandboxPath('a.sqlite'), 'tls' => true, 'ca_cert' => 'PEM']);

    expect(count(glob(sys_get_temp_dir().DIRECTORY_SEPARATOR.'larabb-ca-*')))->toBe($before);
});

it('refuses a database that already holds a board', function () {
    $file = sandboxPath('used.sqlite');
    touch($file);
    $pdo = new PDO('sqlite:'.$file);
    $pdo->exec('create table users (id integer)');
    $pdo->exec('create table migrations (id integer)');
    $pdo = null;

    $result = (new DatabaseProbe)->test(['driver' => 'sqlite', 'database' => $file]);

    expect($result->ok)->toBeFalse()
        ->and($result->message)->toContain('already contains laraBB tables')->toContain('migrations')->toContain('users');
});

it('reports a connection failure without throwing', function () {
    $result = (new DatabaseProbe)->test([
        'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 1, 'database' => 'x', 'username' => 'u', 'password' => 'p',
    ]);

    expect($result->ok)->toBeFalse()->and($result->message)->toStartWith('Could not use this database');
});

// ---- ServiceProbe ----

it('reports Redis as unreachable when nothing answers', function () {
    $result = (new ServiceProbe)->redis(['redis_host' => '127.0.0.1', 'redis_port' => 1, 'redis_password' => 'pw']);

    expect($result->ok)->toBeFalse()->and($result->message)->toStartWith('Could not reach Redis');
});

it('reports Redis as reachable when it answers', function () {
    $probe = new class extends ServiceProbe
    {
        protected function redisManager(array $input): RedisManager
        {
            $connection = Mockery::mock();
            $connection->shouldReceive('ping')->once()->andReturn(true);
            $manager = Mockery::mock(RedisManager::class);
            $manager->shouldReceive('connection')->with('default')->andReturn($connection);

            return $manager;
        }
    };

    expect($probe->redis(['redis_host' => 'redis', 'redis_port' => 6379])->ok)->toBeTrue();
});

it('writes, reads and deletes a file on a working disk', function () {
    $result = (new ServiceProbe)->storage(['driver' => 'local', 'root' => sandboxPath('disk')]);

    expect($result->ok)->toBeTrue()
        ->and(File::files(sandboxPath('disk')))->toBeEmpty();
});

it('reports a disk it cannot write to', function () {
    File::put(sandboxPath('a-file'), 'x');

    $result = (new ServiceProbe)->storage(['driver' => 'local', 'root' => sandboxPath('a-file/inside')]);

    expect($result->ok)->toBeFalse()->and($result->message)->toStartWith('Could not use this storage');
});

it('reports a disk that returns something else than was written', function () {
    $disk = Mockery::mock();
    $disk->shouldReceive('put')->once();
    $disk->shouldReceive('get')->once()->andReturn('garbled');
    $disk->shouldReceive('delete')->once();
    Storage::shouldReceive('build')->once()->andReturn($disk);

    $result = (new ServiceProbe)->storage(['driver' => 'local', 'root' => '/x']);

    expect($result->ok)->toBeFalse()->and($result->message)->toContain('did not return');
});

it('reports an unreachable S3 endpoint', function () {
    $result = (new ServiceProbe)->storage([
        'driver' => 's3', 'key' => 'k', 'secret' => 's', 'region' => 'nyc3', 'bucket' => 'b',
        'endpoint' => 'http://127.0.0.1:1', 'use_path_style_endpoint' => true,
    ]);

    expect($result->ok)->toBeFalse();
});

it('sends a test email through a working mailer', function () {
    $result = (new ServiceProbe)->mail(['transport' => 'log'], 'noreply@example.com', 'mira@example.com');

    expect($result->ok)->toBeTrue()->and($result->message)->toContain('mira@example.com');
});

it('reports a mail server it cannot reach', function () {
    $result = (new ServiceProbe)->mail(
        ['transport' => 'smtp', 'host' => '127.0.0.1', 'port' => 1, 'username' => null, 'password' => null, 'scheme' => 'smtp'],
        'noreply@example.com',
        'mira@example.com',
    );

    expect($result->ok)->toBeFalse()->and($result->message)->toStartWith('Could not send email');
});

<?php

namespace App\Install;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PDO;
use Throwable;

/** Checks that a database can host laraBB without saving anything. */
class DatabaseProbe
{
    /** Tables whose presence means this database already holds a board. */
    private const EXISTING = ['migrations', 'users', 'settings'];

    /**
     * @param  array<string, mixed>  $input  driver, host, port, database, username, password, tls, ca_cert
     * @param  string|null  $caPath  Path of the CA certificate file when TLS is on.
     * @return array<string, mixed>
     */
    public function configFor(array $input, ?string $caPath = null): array
    {
        if ($input['driver'] === 'sqlite') {
            return [
                'driver' => 'sqlite',
                'database' => $input['database'],
                'prefix' => '',
                'foreign_key_constraints' => true,
            ];
        }

        $options = [];
        if ($caPath !== null) {
            $options[PDO::MYSQL_ATTR_SSL_CA] = $caPath;
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
        }

        return [
            'driver' => $input['driver'],
            'host' => $input['host'],
            'port' => $input['port'],
            'database' => $input['database'],
            'username' => $input['username'],
            'password' => $input['password'] ?? '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'options' => $options,
        ];
    }

    /** @param  array<string, mixed>  $input */
    public function test(array $input): ProbeResult
    {
        $caPath = null;

        try {
            if ($input['driver'] === 'sqlite') {
                File::ensureDirectoryExists(dirname($input['database']));
                touch($input['database']);
            }

            if (! empty($input['tls']) && ! empty($input['ca_cert'])) {
                $caPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'larabb-ca-'.bin2hex(random_bytes(8));
                file_put_contents($caPath, $input['ca_cert']);
            }

            config(['database.connections.larabb_probe' => $this->configFor($input, $caPath)]);
            $connection = DB::connection('larabb_probe');
            $connection->select('select 1');

            $schema = $connection->getSchemaBuilder();
            $existing = array_intersect(self::EXISTING, array_column($schema->getTables(), 'name'));
            if ($existing !== []) {
                return ProbeResult::fail('This database already contains laraBB tables ('.implode(', ', $existing).'). Use an empty database.');
            }

            $schema->create('larabb_install_probe', fn ($table) => $table->id());
            $schema->drop('larabb_install_probe');

            $version = $connection->selectOne($input['driver'] === 'sqlite' ? 'select sqlite_version() as v' : 'select version() as v')->v;

            return ProbeResult::ok("Connected. {$input['driver']} {$version}. Tables can be created and dropped. Nothing has been saved yet.");
        } catch (Throwable $e) {
            return ProbeResult::fail('Could not use this database: '.mb_substr($e->getMessage(), 0, 300));
        } finally {
            DB::purge('larabb_probe');
            if ($caPath !== null) {
                File::delete($caPath);
            }
        }
    }
}

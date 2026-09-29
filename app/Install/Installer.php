<?php

namespace App\Install;

use App\Auth\RegistrationMode;
use App\Models\Group;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class Installer
{
    private const CONNECTION = 'larabb_install';

    public function __construct(
        private readonly DatabaseProbe $database,
        private readonly EnvWriter $env,
        private readonly InstallState $state,
        private readonly SetupToken $token,
    ) {}

    /**
     * Runs the whole install. It either finishes completely or leaves the database empty again.
     *
     * @param  array{database: array<string, mixed>, services: array<string, mixed>, site: array<string, mixed>}  $wizard
     * @return list<string> What was done, in order.
     */
    public function run(array $wizard): array
    {
        $lockFile = config('larabb.install.run_lock_file');
        File::ensureDirectoryExists(dirname($lockFile));
        $handle = fopen($lockFile, 'c') ?: throw new InstallFailed('Could not create the install lock file.');

        if (! flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            throw new InstallFailed('Another installation is already running. Wait for it to finish.');
        }

        try {
            return $this->perform($wizard);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * @param  array{database: array<string, mixed>, services: array<string, mixed>, site: array<string, mixed>}  $wizard
     * @return list<string>
     */
    private function perform(array $wizard): array
    {
        if ($this->state->isInstalled()) {
            throw new InstallFailed('laraBB is already installed.');
        }

        $db = $wizard['database'];

        // Checked again here, so an install can never run against a database that has filled up since.
        $probe = $this->database->test($db);
        if (! $probe->ok) {
            throw new InstallFailed($probe->message);
        }

        $caPath = $this->keepCaCertificate($db);
        config(['database.connections.'.self::CONNECTION => $this->database->configFor($db, $caPath)]);
        DB::purge(self::CONNECTION);

        try {
            $tasks = $this->populate($wizard);
            $this->env->write(
                config('larabb.install.env_file') ?: app()->environmentFilePath(),
                $this->environment($wizard, $caPath),
                is_file(base_path('.env.example')) ? (string) file_get_contents(base_path('.env.example')) : '',
            );
            $tasks[] = 'Wrote the configuration file';
        } catch (Throwable $e) {
            $this->rollBack($caPath);
            Log::error('laraBB install failed and was rolled back', ['exception' => $e]);

            throw new InstallFailed('The install failed and was rolled back, so nothing was changed. See the application log for the reason.', previous: $e);
        }

        $this->state->markInstalled();
        $this->token->forget();
        $tasks[] = 'Locked the installer';

        if (config('larabb.install.remove_files')) {
            foreach (config('larabb.install.removable') as $path) {
                is_dir($path) ? File::deleteDirectory($path) : File::delete($path);
            }
            $tasks[] = 'Removed the installer files';
        }

        return $tasks;
    }

    /**
     * @param  array{database: array<string, mixed>, services: array<string, mixed>, site: array<string, mixed>}  $wizard
     * @return list<string>
     */
    private function populate(array $wizard): array
    {
        ['site' => $site, 'services' => $services] = $wizard;

        if (Artisan::call('migrate', ['--database' => self::CONNECTION, '--force' => true]) !== 0) {
            throw new RuntimeException(trim(Artisan::output()));
        }
        $tasks = ['Created the database tables'];

        // The founder is an administrator and a registered user, like any admin created later.
        $groups = Group::on(self::CONNECTION)->whereIn('slug', ['administrators', 'registered'])->pluck('id', 'slug');

        $founder = new User;
        $founder->setConnection(self::CONNECTION);
        $founder->forceFill([
            'name' => $site['founder_username'],
            'email' => $site['founder_email'],
            'password' => $site['founder_password'],
            'email_verified_at' => now(),
            'is_founder' => true,
            'status' => User::STATUS_ACTIVE,
            'timezone' => $site['timezone'],
            'primary_group_id' => $groups['administrators'],
        ])->save();
        $founder->groups()->attach($groups->values()->all());
        $tasks[] = 'Created the founder account';

        foreach ([
            'board.name' => $site['board_name'],
            'board.timezone' => $site['timezone'],
            'board.default_theme' => $site['theme'],
            'board.default_editor' => $site['editor'],
            // New members must confirm their email when the board can actually send email.
            'registration.mode' => $services['mail_mailer'] === 'smtp' ? RegistrationMode::Email->value : RegistrationMode::Open->value,
        ] as $key => $value) {
            Setting::on(self::CONNECTION)->updateOrCreate(['key' => $key], ['value' => $value]);
        }
        $tasks[] = 'Saved the board settings';

        return $tasks;
    }

    private function rollBack(?string $caPath): void
    {
        try {
            DB::connection(self::CONNECTION)->getSchemaBuilder()->dropAllTables();
        } catch (Throwable $e) {
            Log::error('laraBB install rollback could not clear the database', ['exception' => $e]);
        }

        if ($caPath !== null) {
            File::delete($caPath);
        }
    }

    /** @param  array<string, mixed>  $db */
    private function keepCaCertificate(array $db): ?string
    {
        if (empty($db['tls']) || empty($db['ca_cert'])) {
            return null;
        }

        $path = rtrim(config('larabb.install.data_dir'), '/\\').DIRECTORY_SEPARATOR.'db-ca.pem';
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $db['ca_cert']);
        chmod($path, 0600);

        return $path;
    }

    /**
     * @param  array{database: array<string, mixed>, services: array<string, mixed>, site: array<string, mixed>}  $wizard
     * @return array<string, string|int|bool|null>
     */
    private function environment(array $wizard, ?string $caPath): array
    {
        ['database' => $db, 'services' => $services, 'site' => $site] = $wizard;
        $redis = $services['cache_driver'] === 'redis';
        $smtp = $services['mail_mailer'] === 'smtp';
        $s3 = $services['storage'] === 's3';
        $sqlite = $db['driver'] === 'sqlite';

        return [
            'APP_NAME' => $site['board_name'],
            'APP_URL' => rtrim($site['board_url'], '/'),
            'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),

            'DB_CONNECTION' => $db['driver'],
            'DB_HOST' => $sqlite ? '' : $db['host'],
            'DB_PORT' => $sqlite ? '' : $db['port'],
            'DB_DATABASE' => $db['database'],
            'DB_USERNAME' => $sqlite ? '' : $db['username'],
            'DB_PASSWORD' => $sqlite ? '' : ($db['password'] ?? ''),
            'MYSQL_ATTR_SSL_CA' => $caPath ?? '',

            'CACHE_STORE' => $redis ? 'redis' : 'database',
            'SESSION_DRIVER' => $redis ? 'redis' : 'database',
            'QUEUE_CONNECTION' => $redis ? 'redis' : 'database',
            'REDIS_HOST' => $redis ? $services['redis_host'] : '127.0.0.1',
            'REDIS_PORT' => $redis ? $services['redis_port'] : 6379,
            'REDIS_PASSWORD' => $redis ? ($services['redis_password'] ?? '') : '',

            'MAIL_MAILER' => $services['mail_mailer'],
            'MAIL_HOST' => $smtp ? $services['mail_host'] : '',
            'MAIL_PORT' => $smtp ? $services['mail_port'] : '',
            'MAIL_USERNAME' => $smtp ? ($services['mail_username'] ?? '') : '',
            'MAIL_PASSWORD' => $smtp ? ($services['mail_password'] ?? '') : '',
            'MAIL_SCHEME' => $smtp && ($services['mail_encryption'] ?? 'none') === 'ssl' ? 'smtps' : 'smtp',
            'MAIL_FROM_ADDRESS' => $services['mail_from'],

            'FILESYSTEM_DISK' => $s3 ? 's3' : 'local',
            'AWS_ACCESS_KEY_ID' => $s3 ? $services['s3_key'] : '',
            'AWS_SECRET_ACCESS_KEY' => $s3 ? $services['s3_secret'] : '',
            'AWS_DEFAULT_REGION' => $s3 ? $services['s3_region'] : '',
            'AWS_BUCKET' => $s3 ? $services['s3_bucket'] : '',
            'AWS_ENDPOINT' => $s3 ? ($services['s3_endpoint'] ?? '') : '',
        ];
    }
}

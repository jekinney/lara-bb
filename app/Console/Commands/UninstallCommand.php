<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

/** Puts a local board back to "not installed" so the installer can be tried again. */
final class UninstallCommand extends Command
{
    protected $signature = 'larabb:uninstall
        {--force : Do not ask for confirmation}
        {--database= : Connection to empty. Defaults to the configured database}
        {--skip-database : Leave the database alone and only remove the installer lock and files}
        {--delete-env : Also delete the env file the installer wrote}';

    protected $description = 'LOCAL DEVELOPMENT ONLY: delete every table and the installer lock so the installer runs again';

    public function handle(): int
    {
        if (! app()->environment('local') && ! config('larabb.install.allow_uninstall')) {
            $this->components->error('Uninstalling is for local development only. To allow it on this machine, run with APP_ENV=local or LARABB_ALLOW_UNINSTALL=true.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->components->confirm('This deletes every table in the database and unlocks the installer. Continue?')) {
            $this->components->info('Nothing was changed.');

            return self::SUCCESS;
        }

        if (! $this->option('skip-database')) {
            try {
                DB::connection($this->option('database'))->getSchemaBuilder()->dropAllTables();
            } catch (Throwable $e) {
                $this->components->error('Could not empty the database: '.$e->getMessage());
                $this->components->info('Nothing else was removed. Fix the connection, or use --skip-database.');

                return self::FAILURE;
            }
            $this->components->info('Dropped every table.');
        }

        $install = config('larabb.install');
        File::delete([
            $install['lock_file'],
            $install['token_file'],
            $install['key_file'],
            $install['run_lock_file'],
            rtrim($install['data_dir'], '/\\').DIRECTORY_SEPARATOR.'db-ca.pem',
        ]);

        if ($this->option('delete-env')) {
            File::delete($install['env_file'] ?: app()->environmentFilePath());
            $this->components->info('Deleted the env file.');
        }

        $this->components->info('laraBB is uninstalled. Open the site to run the installer again.');

        return self::SUCCESS;
    }
}

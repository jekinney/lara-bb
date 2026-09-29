<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/*
 | Every feature test runs against an installed board with all installer files inside a throwaway
 | directory, so tests never touch the real .env, storage or lock. Installer tests call uninstall().
 */
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'larabb-'.bin2hex(random_bytes(6));
        File::ensureDirectoryExists($dir);
        $this->sandbox = $dir;

        config(['larabb.install' => array_merge(config('larabb.install'), [
            'lock_file' => $dir.'/installed',
            'token_file' => $dir.'/install-token',
            'key_file' => $dir.'/install-key',
            'run_lock_file' => $dir.'/installing',
            'env_file' => $dir.'/.env',
            'data_dir' => $dir,
            'session_driver' => 'array',
            'cache_store' => 'array',
            'remove_files' => false,
            'removable' => [],
        ])]);

        File::put($dir.'/installed', 'test');
    })
    // Only ever delete the directory this test created. A skipped test never ran beforeEach, and
    // deleting "the configured directory" then would wipe the real storage/app.
    ->afterEach(function () {
        if (isset($this->sandbox)) {
            File::deleteDirectory($this->sandbox);
        }
    })
    ->in('Feature');

function sandboxPath(string $file = ''): string
{
    return dirname(config('larabb.install.lock_file')).($file === '' ? '' : '/'.$file);
}

/** Puts the app back in the "not installed yet" state. */
function uninstall(): void
{
    File::delete(config('larabb.install.lock_file'));
}

/**
 * A complete, valid set of wizard answers using a throwaway SQLite database.
 *
 * @param  array<string, mixed>  $override
 * @return array<string, array<string, mixed>>
 */
function wizardData(array $override = []): array
{
    return array_replace_recursive([
        'token' => [],
        'requirements' => [],
        'review' => [],
        'database' => ['driver' => 'sqlite', 'database' => sandboxPath('board.sqlite'), 'tls' => false],
        'services' => ['cache_driver' => 'database', 'mail_mailer' => 'log', 'mail_from' => 'noreply@example.com', 'storage' => 'local'],
        'site' => [
            'board_name' => 'Test Board', 'board_url' => 'https://forum.example.com/', 'timezone' => 'Europe/Berlin',
            'theme' => 'default', 'editor' => 'bbcode',
            'founder_username' => 'mira', 'founder_email' => 'mira@example.com', 'founder_password' => 'a-long-passphrase-42',
        ],
    ], $override);
}

/** Session payload for a visitor who has finished the given wizard steps. */
function wizardSession(string ...$steps): array
{
    return ['install' => array_intersect_key(wizardData(), array_flip($steps))];
}

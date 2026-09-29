<?php

namespace App\Install;

use Illuminate\Support\Facades\File;

final class InstallState
{
    public function isInstalled(): bool
    {
        return is_file($this->lockFile());
    }

    public function markInstalled(): void
    {
        $file = $this->lockFile();

        File::ensureDirectoryExists(dirname($file));
        File::put($file, now()->toIso8601String().PHP_EOL);
        chmod($file, 0600);
    }

    private function lockFile(): string
    {
        return config('larabb.install.lock_file');
    }
}

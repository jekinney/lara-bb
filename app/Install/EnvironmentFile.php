<?php

namespace App\Install;

use Illuminate\Foundation\Application;

final class EnvironmentFile
{
    /**
     * Point the app at an env file outside the code directory. In Docker this lets the file live
     * on the storage volume, so the installer's configuration survives a container being replaced.
     */
    public static function apply(Application $app, ?string $path): Application
    {
        if ($path === null || $path === '') {
            return $app;
        }

        return $app->useEnvironmentPath(dirname($path))->loadEnvironmentFrom(basename($path));
    }
}

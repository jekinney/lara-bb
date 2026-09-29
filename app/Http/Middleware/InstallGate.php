<?php

namespace App\Http\Middleware;

use App\Install\InstallState;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs before anything that touches the database or session.
 *
 * Once installed, /install answers 404 as if it never existed. Before that, every page except the
 * health checks goes to the installer, and sessions and cache use files, because the database is
 * not configured yet.
 */
final class InstallGate
{
    public function __construct(private readonly InstallState $state) {}

    public function handle(Request $request, Closure $next): Response
    {
        $installing = $request->is('install', 'install/*');

        if ($this->state->isInstalled()) {
            abort_if($installing, 404);

            return $next($request);
        }

        $this->prepareForInstaller();

        if (! $installing && ! $request->is('healthz', 'readyz')) {
            return redirect('/install');
        }

        return $next($request);
    }

    private function prepareForInstaller(): void
    {
        config([
            'session.driver' => config('larabb.install.session_driver'),
            'session.encrypt' => true,
            'cache.default' => config('larabb.install.cache_store'),
        ]);

        if (blank(config('app.key'))) {
            config(['app.key' => $this->temporaryKey()]);
        }
    }

    /** A throwaway key so the wizard session can be encrypted before the real APP_KEY exists. */
    private function temporaryKey(): string
    {
        $file = config('larabb.install.key_file');

        if (! is_file($file)) {
            File::ensureDirectoryExists(dirname($file));
            File::put($file, 'base64:'.base64_encode(random_bytes(32)));
            chmod($file, 0600);
        }

        return trim((string) file_get_contents($file));
    }
}

<?php

namespace App\Install;

use Illuminate\Http\Request;

final class Requirements
{
    /**
     * @param  list<string>|null  $extensions  Defaults to what laraBB needs.
     * @param  list<string>|null  $writable  Defaults to the paths the app writes to.
     */
    public function __construct(
        private readonly string $minPhp = '8.3.0',
        private readonly ?array $extensions = null,
        private readonly ?array $writable = null,
    ) {}

    /** @return list<array{label: string, status: 'ok'|'warn'|'fail', detail: string}> */
    public function evaluate(Request $request): array
    {
        return [
            $this->php(),
            $this->extensionCheck(),
            $this->database(),
            $this->writableCheck(),
            $this->https($request),
            $this->opcache(),
        ];
    }

    /** @param  list<array{label: string, status: string, detail: string}>  $checks */
    public function passes(array $checks): bool
    {
        foreach ($checks as $check) {
            if ($check['status'] === 'fail') {
                return false;
            }
        }

        return true;
    }

    /** @return array{label: string, status: 'ok'|'fail', detail: string} */
    private function php(): array
    {
        $ok = version_compare(PHP_VERSION, $this->minPhp, '>=');

        return [
            'label' => "PHP {$this->minPhp} or newer",
            'status' => $ok ? 'ok' : 'fail',
            'detail' => PHP_VERSION,
        ];
    }

    /** @return array{label: string, status: 'ok'|'fail', detail: string} */
    private function extensionCheck(): array
    {
        $required = $this->extensions ?? ['mbstring', 'intl', 'gd', 'openssl', 'fileinfo', 'ctype', 'tokenizer', 'xml', 'curl'];
        $missing = array_values(array_filter($required, fn (string $name) => ! extension_loaded($name)));

        return [
            'label' => 'Extensions: '.implode(', ', $required),
            'status' => $missing === [] ? 'ok' : 'fail',
            'detail' => $missing === [] ? 'all loaded' : 'missing '.implode(', ', $missing),
        ];
    }

    /** @return array{label: string, status: 'ok'|'fail', detail: string} */
    private function database(): array
    {
        $drivers = array_values(array_intersect(['pdo_mysql', 'pdo_sqlite'], get_loaded_extensions()));

        return [
            'label' => 'A database driver (pdo_mysql or pdo_sqlite)',
            'status' => $drivers === [] ? 'fail' : 'ok',
            'detail' => $drivers === [] ? 'none loaded' : implode(', ', $drivers),
        ];
    }

    /** @return array{label: string, status: 'ok'|'fail', detail: string} */
    private function writableCheck(): array
    {
        $paths = $this->writable ?? [storage_path(), base_path('bootstrap/cache'), $this->envDirectory()];
        $blocked = array_values(array_filter($paths, fn (string $path) => ! is_writable($path)));

        return [
            'label' => 'Storage, cache and configuration paths are writable',
            'status' => $blocked === [] ? 'ok' : 'fail',
            'detail' => $blocked === [] ? 'ok' : 'not writable: '.implode(', ', $blocked),
        ];
    }

    /** @return array{label: string, status: 'ok'|'warn'|'fail', detail: string} */
    private function https(Request $request): array
    {
        if ($request->isSecure()) {
            return ['label' => 'Secure connection (HTTPS)', 'status' => 'ok', 'detail' => 'HTTPS'];
        }

        if (app()->isProduction() && ! config('larabb.install.allow_http')) {
            return [
                'label' => 'Secure connection (HTTPS)',
                'status' => 'fail',
                'detail' => 'open this page over HTTPS, or set LARABB_INSTALLER_ALLOW_HTTP=true behind a TLS load balancer',
            ];
        }

        return ['label' => 'Secure connection (HTTPS)', 'status' => 'warn', 'detail' => 'plain HTTP, fine for local use only'];
    }

    /** @return array{label: string, status: 'ok'|'warn', detail: string} */
    private function opcache(): array
    {
        $enabled = extension_loaded('Zend OPcache') && filter_var(ini_get('opcache.enable'), FILTER_VALIDATE_BOOLEAN);

        return [
            'label' => 'OPcache enabled',
            'status' => $enabled ? 'ok' : 'warn',
            'detail' => $enabled ? 'on' : 'recommended for speed',
        ];
    }

    private function envDirectory(): string
    {
        $file = config('larabb.install.env_file') ?: app()->environmentFilePath();

        return is_file($file) ? $file : dirname($file);
    }
}

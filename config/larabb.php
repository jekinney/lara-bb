<?php

return [

    'install' => [
        // Existence of this file means laraBB is installed. It is authoritative.
        'lock_file' => env('LARABB_INSTALL_LOCK', storage_path('app/installed')),

        // One-time setup token, printed to the log and this file until the install finishes.
        'token_file' => storage_path('app/install-token'),
        'token_ttl_minutes' => 30,

        // Throwaway key so the wizard can encrypt its session before a real APP_KEY exists.
        'key_file' => storage_path('app/install-key'),
        'session_driver' => 'file',
        'cache_store' => 'file',

        // Held while an install runs so two visitors cannot install at the same time.
        'run_lock_file' => storage_path('app/installing'),

        // Where the finished configuration is written. Null means the app's own .env.
        'env_file' => null,

        // Directory for files the installer keeps (for example the database CA certificate).
        'data_dir' => storage_path('app'),

        // Plain HTTP is refused in production unless a load balancer terminates TLS and this is set.
        'allow_http' => (bool) env('LARABB_INSTALLER_ALLOW_HTTP', false),

        // Delete the installer views once the install has finished. Off outside production so a
        // development checkout keeps its tracked files.
        'remove_files' => (bool) env('LARABB_INSTALLER_REMOVE', env('APP_ENV') === 'production'),
        'removable' => [
            resource_path('views/install'),
        ],
    ],

];

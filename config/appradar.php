<?php

return [
    /*
    |--------------------------------------------------------------------------
    | App metadata (plain PHP)
    |--------------------------------------------------------------------------
    |
    | Laravel ignores these and uses config('app.*') instead. For plain PHP,
    | fill these in so /status can report the app name and environment.
    |
    */
    'app' => [
        'name' => null,
        'environment' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Project root (plain PHP)
    |--------------------------------------------------------------------------
    |
    | Optional. When null, the agent walks up from this config file until it
    | finds composer.lock / composer.json (so config/appradar.php still works).
    |
    */
    'base_path' => null,

    'route' => [
        'path' => 'status',
        'middleware' => ['web'],
        'name' => 'appradar.status',
        'tests_name' => 'appradar.status.tests.run',
    ],

    'only_local' => false,

    /*
    |--------------------------------------------------------------------------
    | Agent secret
    |--------------------------------------------------------------------------
    |
    | Shared secret from AppRadar app settings. When empty, /status stays public.
    | When set, agent HTTP routes require Authorization: Bearer <secret>
    | (or X-AppRadar-Token). Same secret authenticates error ingest.
    |
    | APPRADAR_STATUS_TOKEN is still accepted as a legacy env alias.
    |
    */
    'secret' => env('APPRADAR_SECRET', env('APPRADAR_STATUS_TOKEN', '')),

    'storage_path' => 'app/status',

    'scheduler' => [
        'heartbeat_name' => 'appradar-heartbeat',
    ],

    'queue' => [
        'activity_window_seconds' => 900,
        'problem_window_seconds' => 21600,
        'problem_threshold' => 3,
        'timeout_threshold' => 2,
        'max_problem_jobs' => 5,
        'incident_retention_hours' => 24,
    ],

    /*
    |--------------------------------------------------------------------------
    | Database (plain PHP only)
    |--------------------------------------------------------------------------
    */
    'database' => [
        'driver' => null, // mysql, pgsql, sqlite
        'host' => null,
        'port' => null,
        'database' => null,
        'username' => null,
        'password' => null,
        'dsn' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis (plain PHP only)
    |--------------------------------------------------------------------------
    */
    'redis' => [
        'host' => null,
        'port' => 6379,
        'password' => null,
        'database' => 0,
        'timeout' => 1.0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    */
    'security' => [
        'public_url' => null,
        'public_path' => null,
        'ssl_check' => true,
        'ssl_expiry_warn_days' => 14,
        'ssl_timeout_seconds' => 3.0,
    ],

    'maintenance' => [
        'composer_audit' => env('APPRADAR_MAINTENANCE_COMPOSER_AUDIT', true),
        'composer_audit_cache_seconds' => 86400,
        'abandoned_check' => env('APPRADAR_MAINTENANCE_ABANDONED', true),
        'php_unsupported_below' => '8.2.0',
        'php_eol_below' => '8.1.0',
        'laravel_security_supported_majors' => [11, 12],
    ],

    /*
    |--------------------------------------------------------------------------
    | Error reporting (Laravel)
    |--------------------------------------------------------------------------
    |
    | Auto-on when app_uuid + secret are set. Posts to the fixed webhook:
    |   {APPRADAR_URL|https://appradar.nl}/api/agent/apps/{app_uuid}/errors
    | Local AppRadar: set APPRADAR_URL=http://127.0.0.1:8000
    | Uses the same agent secret. Does not replace your exception handler.
    |
    */
    'errors' => [
        'app_uuid' => env('APPRADAR_APP_UUID'),
        // Optional: only for local/self-hosted AppRadar. SaaS users leave unset.
        'base_url' => env('APPRADAR_URL'),
        'sample_rate' => 1.0,
        'send_timeout_seconds' => 2.0,
        'environment' => null,
        'release' => env('APPRADAR_RELEASE'),
        'ignore' => [],
    ],
];

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

    'route' => [
        'path' => 'status',
        'middleware' => ['web'],
        'name' => 'appradar.status',
        'tests_name' => 'appradar.status.tests.run',
    ],

    'only_local' => false,

    /*
    |--------------------------------------------------------------------------
    | Status endpoint token
    |--------------------------------------------------------------------------
    |
    | When empty, /status (and related agent routes) stay public.
    | When set, every agent HTTP route requires:
    |   Authorization: Bearer <token>
    | or header X-AppRadar-Token: <token>
    |
    */
    'status_token' => env('APPRADAR_STATUS_TOKEN', ''),

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
    |
    | Laravel ignores this block and uses config/database.php + .env.
    | For plain PHP, fill either `dsn` or discrete driver/host/database fields.
    | Leave empty to skip live DB checks (status will warn "not configured").
    |
    */
    'database' => [
        'driver' => null, // mysql, pgsql, sqlite
        'host' => null,
        'port' => null,
        'database' => null,
        'username' => null,
        'password' => null,
        'dsn' => null, // optional full PDO DSN instead of discrete fields
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis (plain PHP only)
    |--------------------------------------------------------------------------
    |
    | Laravel ignores this block and uses config/database.php redis section.
    | For plain PHP, set host (and optional auth). Needs ext-redis or predis/predis.
    | Leave host empty to skip live Redis checks.
    |
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
    |
    | Feeds the status payload `security` section (issues + 0-100 score meter).
    | SSL checks use an outbound TLS probe against public_url (Laravel defaults
    | to config('app.url') when public_url is null).
    |
    */
    'security' => [
        'composer_audit' => false,
        'php_unsupported_below' => '8.2.0',
        'php_eol_below' => '8.1.0',
        'public_url' => null,
        'public_path' => null,
        'ssl_check' => true,
        'ssl_expiry_warn_days' => 14,
        'ssl_timeout_seconds' => 3.0,
    ],
];

# AppRadar Agent

Framework-aware application agent for AppRadar.

- **Laravel** — auto-discovers DB, Redis, scheduler, queue, tests, and security posture (incl. SSL)
- **Plain PHP** — checks DB/Redis/security when you fill in `config/appradar.php`

## What This Package Does

### Laravel

It registers:

- `GET /status`
- `POST /status/tests/run`

It also wires:

- queue activity listeners
- scheduler activity listeners
- scheduler heartbeat registration

### Plain PHP

You mount a small endpoint yourself and point it at a config file. If database/redis credentials are empty, those sections return a warning (`not configured`). Queue, scheduler, and tests are not available without a framework.

Example front controller (`public/appradar-status.php`):

```php
<?php

require __DIR__.'/../vendor/autoload.php';

use AppRadar\Agent\Php\Http\StatusEndpoint;

StatusEndpoint::fromConfigFile(__DIR__.'/../config/appradar.php')->respond();
```

Then copy the package config and fill in credentials:

```bash
cp vendor/appradar/agent/config/appradar.php config/appradar.php
```

Plain PHP config fields (ignored by Laravel):

```php
'app' => [
    'name' => 'My App',
    'environment' => 'production',
],

'database' => [
    'driver' => 'mysql', // or pgsql / sqlite, or set dsn instead
    'host' => '127.0.0.1',
    'port' => 3306,
    'database' => 'my_app',
    'username' => 'root',
    'password' => 'secret',
    'dsn' => null,
],

'redis' => [
    'host' => '127.0.0.1',
    'port' => 6379,
    'password' => null,
    'database' => 0,
    'timeout' => 1.0,
],

'security' => [
    'public_url' => 'https://example.com',
    'public_path' => __DIR__.'/../public',
    'ssl_check' => true,
    'ssl_expiry_warn_days' => 14,
],
```

Redis needs `ext-redis` or `predis/predis`.

## Security section

`/status` includes a `security` object:

- `status` — `0` ok / `1` warn / `2` error (worst finding)
- `score` — **0–100 meter** (`100` = all checks clean). Formula: `max(0, 100 - errors*20 - warns*5)`
- `issues` — findings with `id`, `severity`, `title`, `message`, `remediation`

SSL is part of `security` (not a separate top-level section). The agent opens TLS to the public host (`APP_URL` on Laravel, or `security.public_url` on plain PHP). It does **not** trust “was this HTTP request HTTPS?” behind proxies.

Optional: set `security.composer_audit` to `true` (Laravel) to run `composer audit` (slower; off by default).

Live intrusion / “hackers probing now” detection is not included yet.

## Shared Contract

This package is both:

- the agent that exposes the status endpoint inside supported apps
- the shared contract/SDK that can parse those endpoint responses elsewhere

Example:

```php
use AppRadar\Agent\Data\StatusReport;

$report = StatusReport::fromArray($payload);

$databaseStatus = $report->database;
$allSections = $report->sections();
```

## Install

For a tagged release:

```bash
composer require appradar/agent:^1.0
```

For local development through a path repository:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../../Projects/appradar-agent",
      "options": {
        "symlink": true
      }
    }
  ]
}
```

Then require the package:

```bash
composer require appradar/agent:@dev
```

For private GitHub installation:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "git@github.com:logiclyhub/appradar.git"
    }
  ]
}
```

Then require the main branch:

```bash
composer require appradar/agent:dev-main
```

## Config

### Laravel

Publish the config if you want to override defaults:

```bash
php artisan vendor:publish --tag=appradar-config
```

Laravel still reads DB/Redis from the app's normal config/env. The `database` / `redis` blocks in `appradar.php` are only for plain PHP.

Default config:

- route path: `status`
- only local environment: `false`
- status storage path: `app/status`
- scheduler heartbeat: every minute

### Protecting `/status`

By default the endpoint is public so AppRadar can connect quickly.

To lock every agent route (`/status`, `/status/tests/run`, …), set a shared token:

```env
APPRADAR_STATUS_TOKEN=apr_your_token_from_appradar
```

Or in `config/appradar.php`:

```php
'status_token' => env('APPRADAR_STATUS_TOKEN', ''),
```

When set, requests must include:

```http
Authorization: Bearer apr_your_token_from_appradar
```

(or `X-AppRadar-Token: apr_…`). Missing/wrong token returns `401`.

Generate and copy the token from the app settings in AppRadar (“Protect status endpoint”).

### Plain PHP

Copy `config/appradar.php` into your app and fill `app`, `database`, and/or `redis`. Nothing is auto-discovered.

## Current Scope

| Capability | Laravel | Plain PHP |
|------------|---------|-----------|
| Database | auto | explicit config |
| Redis | auto | explicit config |
| Scheduler | yes | not available |
| Queue | yes | not available |
| Tests | yes | not available |
| Security (+ SSL meter) | yes | yes (smaller probe set) |

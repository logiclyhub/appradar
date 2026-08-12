# AppRadar Agent

In-app agent for AppRadar. Exposes a `/status` endpoint with health and security checks.

**Laravel** — auto-wires routes and reads DB/Redis from your app.  
**Plain PHP** — you mount the endpoint and fill in config yourself.

---

## Install

This package is private. Add the GitHub repo to your app’s `composer.json`:

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

Then require it (use a tag when you can):

```bash
composer require appradar/agent:^1.2
```

Or track main:

```bash
composer require appradar/agent:dev-main
```

You need GitHub SSH access (or a token) so Composer can clone the repo.

### Local path (development)

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../appradar-agent",
      "options": { "symlink": true }
    }
  ]
}
```

```bash
composer require appradar/agent:@dev
```

---

## Setup

### Laravel

Publish the config:

```bash
php artisan vendor:publish --tag=appradar-config
```

That creates `config/appradar.php`.  
Routes `GET /status` and `POST /status/tests/run` are registered automatically.

### Plain PHP

1. Copy the example config:

```bash
cp vendor/appradar/agent/config/appradar.php config/appradar.php
```

2. Fill in `app`, `database`, `redis`, and `security` (see below).

3. Add a front controller, e.g. `public/appradar-status.php`:

```php
<?php

require __DIR__.'/../vendor/autoload.php';

use AppRadar\Agent\Php\Http\StatusEndpoint;

StatusEndpoint::fromConfigFile(__DIR__.'/../config/appradar.php')->respond();
```

Point AppRadar at that URL. Queue, scheduler, and tests are Laravel-only.

---

## Config

All keys live in `config/appradar.php` (published or copied).

### `secret`

Protects every agent route **and** authenticates error ingest. Empty = `/status` public.

```env
APPRADAR_SECRET=apr_your_secret_from_appradar
```

(`APPRADAR_STATUS_TOKEN` still works as a legacy alias.)

Requests need:

```http
Authorization: Bearer apr_your_secret_from_appradar
```

or `X-AppRadar-Token: apr_…`. Wrong/missing → `401`.

### `route` (Laravel)

| Key | Default | Meaning |
|-----|---------|---------|
| `path` | `status` | URL path for the status endpoint |
| `middleware` | `['web']` | Middleware stack |
| `name` | `appradar.status` | Route name |
| `tests_name` | `appradar.status.tests.run` | Tests-run route name |

### `only_local`

If `true`, status only works in `local` / `testing`. Default `false` so AppRadar can reach production.

### `storage_path` (Laravel)

Where the agent stores status data (relative to `storage/`). Default `app/status`.

### `scheduler` (Laravel)

| Key | Default | Meaning |
|-----|---------|---------|
| `heartbeat_name` | `appradar-heartbeat` | Scheduled heartbeat job name |

### `queue` (Laravel)

Windows and thresholds for queue health (activity, problems, timeouts, retention). Defaults are fine for most apps.

### `app` (plain PHP only)

Laravel uses `config('app.*')` instead.

```php
'app' => [
    'name' => 'My App',
    'environment' => 'production',
],
```

### `database` (plain PHP only)

Laravel uses `config/database.php`. Leave empty on plain PHP to skip DB checks (status warns “not configured”).

```php
'database' => [
    'driver' => 'mysql', // mysql, pgsql, sqlite
    'host' => '127.0.0.1',
    'port' => 3306,
    'database' => 'my_app',
    'username' => 'root',
    'password' => 'secret',
    'dsn' => null, // optional full PDO DSN instead of fields above
],
```

### `redis` (plain PHP only)

Laravel uses its own Redis config. Needs `ext-redis` or `predis/predis`. Empty `host` = skip Redis checks.

```php
'redis' => [
    'host' => '127.0.0.1',
    'port' => 6379,
    'password' => null,
    'database' => 0,
    'timeout' => 1.0,
],
```

### `security`

Feeds the `security` section on `/status` (issues + 0–100 score).

| Key | Default | Meaning |
|-----|---------|---------|
| `composer_audit` | `false` | Laravel: run `composer audit` (slow; opt-in) |
| `php_unsupported_below` | `8.2.0` | PHP below this → issue |
| `php_eol_below` | `8.1.0` | PHP below this → stronger issue |
| `public_url` | `null` | Host for SSL check (Laravel falls back to `APP_URL`) |
| `public_path` | `null` | Plain PHP: path to public dir (for HTTP checks) |
| `ssl_check` | `true` | Outbound TLS check against public URL |
| `ssl_expiry_warn_days` | `14` | Warn when cert expires within N days |
| `ssl_timeout_seconds` | `3.0` | SSL probe timeout |

### `errors` (Laravel)

Exception reporting to AppRadar. **Auto-on** when `app_uuid` + `secret` are set. Does not replace Laravel’s handler / Sentry.

Fixed webhook (SaaS default):

`https://appradar.nl/api/agent/apps/{uuid}/errors`

```env
APPRADAR_APP_UUID=550e8400-e29b-41d4-a716-446655440000
APPRADAR_SECRET=apr_…
# only while testing against local AppRadar:
# APPRADAR_URL=http://127.0.0.1:8000
```

| Key | Default | Meaning |
|-----|---------|---------|
| `app_uuid` | `APPRADAR_APP_UUID` | App UUID from AppRadar |
| `base_url` | `https://appradar.nl` | Local/self-host: set `APPRADAR_URL=http://127.0.0.1:8000` |
| `sample_rate` | `1.0` | Fraction of errors to send |
| `send_timeout_seconds` | `2.0` | HTTP timeout; failures swallowed |
| `release` | `APPRADAR_RELEASE` | Optional release label |
| `ignore` | `[]` | Extra exception classes to skip |

Secret is the top-level `secret` / `APPRADAR_SECRET` — not a separate errors token.

---

## What you get

| | Laravel | Plain PHP |
|--|---------|-----------|
| Database | auto | config |
| Redis | auto | config |
| Scheduler | yes | — |
| Queue | yes | — |
| Tests | yes | — |
| Security (+ SSL) | yes | yes (smaller set) |
| Error reporting | yes (opt-in) | — (later) |

`/status` → JSON. Security includes `status` (0/1/2), `score` (0–100), and `issues[]`.

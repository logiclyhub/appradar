# Design: Port Doctor-style posture probes into AppRadar security

**Date:** 2026-08-11  
**Status:** Approved (user: all in `security`, no `laravel/doctor` dependency)

## Goal

Steal useful Laravel Doctor *ideas* we do not already cover, implemented as our own security probes. Do **not** require or call `laravel/doctor`.

## Placement

All findings go into the existing `security` section (issues + 0–100 meter).

## New Laravel probes (v1)

| Issue id | Severity | Detection |
|----------|----------|-----------|
| `php_extension_missing` | error (one issue per missing ext, or one issue listing all) | Missing required PHP extensions |
| `storage_not_writable` | error | Required storage dirs not writable |
| `storage_link_missing` | warn | `public/storage` missing when public disk expected |
| `queue_sync_in_production` | error | Default queue connection driver is `sync` in non-local |
| `bootstrap_cache_missing` | warn | Non-local and `bootstrap/cache/config.php` missing |
| `session_driver_array_in_production` | warn | `session.driver` is `array` in non-local |

## Extension list

Always check: `openssl`, `pdo`, `mbstring`, `tokenizer`, `xml`, `ctype`, `json`, `fileinfo`, `bcmath`.  
Also: `redis` if default cache/queue/session driver is redis; `pdo_mysql` / `pdo_pgsql` / `pdo_sqlite` based on default DB driver.

Emit a **single** issue `php_extension_missing` listing all missing names (avoids score death from many errors).

## Out of scope

- Depending on / shelling out to `laravel/doctor`
- Auto-fix / `--fix`
- Pending migration apply
- Plain PHP ports of these (Laravel-only this batch)
- Composer install/lock repair

## Implementation notes

- Extend `LaravelSecurityContext` with fields needed by probes (or read filesystem/config inside probes via context paths).
- Register probes in `Laravel\Checks\SecurityCheck`.
- Unit-test probes with injected context where practical.
- Bump package minor/patch and document in README security list.

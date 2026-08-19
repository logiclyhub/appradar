# Maintenance Section Implementation Plan

> **For agentic workers:** Implement in `appradar-agent` first (this repo). AppRadar platform UI can consume the new `/status` section later. Follow existing security-section patterns. Use TDD. Do not push unless the user asks. Conventional commits.

**Goal:** Add a top-level `maintenance` section on `/status` that reports **real stack health risks** (EOL runtimes/frameworks, Composer security advisories, abandoned packages) — **not** “every package that has a newer version”.

**Product stance (agreed with product owner):**
- `maintenance` is a **sibling** of `security`, not inside it.
- Security = posture / leak / misconfig risk (debug, secrets, SSL, cookies, …).
- Maintenance = “is the stack still supported / patched?”.
- Random “1.2.3 → 1.2.4 available” updates must **not** create issues or tank the score.

**Architecture:** Mirror `SecurityStatus`: `MaintenanceIssue` + `MaintenanceIssueCollection` + `MaintenanceStatus` (with `status` 0/1/2 + `score` 0–100). A `MaintenanceCheck` aggregator runs probes. Laravel is richer; plain PHP uses `composer.lock` when present + PHP version. Reuse/adapt `ComposerAuditRunner` (move findings from security into maintenance). Cache expensive Composer network calls.

**Tech Stack:** PHP 8.2+, existing AppRadar agent patterns, Symfony Process for `composer audit`, optional Packagist abandoned checks, Illuminate Application version for Laravel.

## Global Constraints

- Public methods pass **value objects**, not arrays (except private helpers / `toArray()` JSON boundary).
- Reuse `StatusCodes::OK|WARN|ERROR` — no fourth status code.
- `score` meter only on `maintenance` (and existing `security`); other sections stay without score.
- Formula (same as security unless product says otherwise): `max(0, 100 - errors*20 - warns*5)`.
- Do **not** flag pure version bumps without advisory/EOL/abandoned.
- Do **not** run uncached `composer audit` on every `/status` hit in production defaults — cache ≥ 6–24h (file under agent storage path).
- Move **Composer audit** and **PHP EOL** out of `security` into `maintenance` when this ships (security stays posture-only). Update README accordingly.
- Laravel first; plain PHP second with smaller probe set.
- Prefer small probe classes under `Checks/Maintenance/`.
- Do not push; only commit when the user asks.

---

## What counts as a maintenance finding

| Signal | Severity | Include? | Source |
|--------|----------|----------|--------|
| Composer security advisory / CVE | **error** | yes | `composer audit --format=json` |
| PHP below EOL floor | **error** | yes | configured / built-in floor (existing thresholds) |
| PHP below “unsupported” floor but above EOL | **warn** | yes | existing thresholds |
| Laravel major outside security support | **error** | yes | built-in support table in agent |
| Laravel major approaching EOL (optional) | **warn** | yes if easy | same table |
| Packagist `abandoned` on locked package | **warn** | yes | Packagist metadata or `composer outdated` abandoned field if available without listing all outdated |
| Newer patch/minor with **no** advisory | — | **no** | ignore |
| “Major available” while current major still supported | — | **no** (or later warn) | ignore in v1 |

---

## Payload shape (target)

```json
{
  "maintenance": {
    "status": 2,
    "score": 60,
    "issue_count": 2,
    "error_count": 1,
    "warn_count": 1,
    "issues": [
      {
        "id": "composer_advisory:vendor/package",
        "severity": 2,
        "title": "Security advisory",
        "message": "vendor/package 1.2.3 has advisory CVE-…",
        "remediation": "Upgrade vendor/package to 1.2.5 or later."
      },
      {
        "id": "laravel_version_eol",
        "severity": 2,
        "title": "Laravel outside security support",
        "message": "Running Laravel 9.x; security support has ended.",
        "remediation": "Upgrade to a Laravel version that still receives security fixes."
      }
    ],
    "runtime": {
      "php": "8.3.12",
      "laravel": "12.0.0"
    }
  }
}
```

Notes:
- `runtime` is informational (always present when known); it does not alone change score.
- Issue shape matches security issues (`id`, `severity`, `title`, `message`, `remediation`) so the platform can reuse list UI patterns.
- Overall `/status` `status` must include maintenance in worst-of aggregation (like security).

---

## File structure (agent)

| Path | Responsibility |
|------|----------------|
| `src/Data/MaintenanceIssue.php` | Single finding VO (same fields as SecurityIssue; can extract shared base later — YAGNI: duplicate thin VO first) |
| `src/Data/MaintenanceIssueCollection.php` | Merge / worst severity / counts |
| `src/Data/MaintenanceStatus.php` | Section DTO: status, score, counts, issues, optional runtime |
| `src/Data/MaintenanceScoreCalculator.php` | Pure score helper (same formula as security) |
| `src/Data/StatusReport.php` | Add `maintenance` property + serialize/parse/sections |
| `src/Core/Contracts/MaintenanceProbeInterface.php` | `probe(): MaintenanceIssueCollection` |
| `src/Laravel/Checks/MaintenanceCheck.php` | Runs Laravel maintenance probes → `MaintenanceStatus` |
| `src/Laravel/Checks/Maintenance/*.php` | One probe per concern |
| `src/Php/Checks/MaintenanceCheck.php` | Smaller plain-PHP set |
| `src/Php/Checks/Maintenance/*.php` | PHP EOL + composer audit if lock present |
| `src/Laravel/LaravelAdapter.php` / `src/Php/PhpAdapter.php` | Include maintenance in report + overall status |
| `src/Laravel/Support/ComposerAuditRunner.php` | Keep; call from maintenance (not security) |
| `src/Core/Maintenance/ComposerAuditCache.php` (or under Support) | Cache audit JSON result by path + mtime of lock |
| `config/appradar.php` | `maintenance` toggles |
| `README.md` | Document section + config |
| `tests/...` | PHPUnit for DTOs + probes + StatusReport parsing |

### Laravel probes (v1)

| Probe | Issue id(s) |
|-------|-------------|
| `PhpVersionProbe` | `php_version_eol` (error/warn) — **moved from security** |
| `LaravelVersionProbe` | `laravel_version_eol` (error), optional `laravel_version_ending_soon` (warn) |
| `ComposerAdvisoryProbe` | `composer_advisory:{package}` / `composer_audit_unavailable` — **moved from security**, **on by default** with cache |
| `AbandonedPackageProbe` | `composer_package_abandoned` (one issue listing abandoned packages, or one per package capped at N) |

### Plain PHP probes (v1)

| Probe | Notes |
|-------|-------|
| `PhpVersionProbe` | Same as Laravel |
| `ComposerAdvisoryProbe` | Only if `composer.lock` (+ composer binary) next to config / base path |
| Skip Laravel version | N/A |
| Abandoned | Optional if easy; else defer |

---

## Config (`config/appradar.php`)

```php
'maintenance' => [
    'composer_audit' => env('APPRADAR_MAINTENANCE_COMPOSER_AUDIT', true),
    'composer_audit_cache_seconds' => 86400, // 24h
    'abandoned_check' => env('APPRADAR_MAINTENANCE_ABANDONED', true),
    'php_unsupported_below' => '8.2.0', // can mirror / replace security.* keys
    'php_eol_below' => '8.1.0',
    // Laravel majors still receiving security fixes (update when Laravel policy changes)
    'laravel_security_supported_majors' => [11, 12],
],
```

Remove or deprecate `security.composer_audit` and security PHP version floors once migrated — leave a short README note: “PHP EOL + composer audit now live under `maintenance`”.

---

## Laravel version table (v1)

Hardcode in a small VO/service, e.g. `LaravelSupportPolicy`:

- Input: `Illuminate\Foundation\Application::VERSION` (or composer lock `laravel/framework`)
- If major **not** in `laravel_security_supported_majors` → error `laravel_version_eol`
- Do not require live HTTP to laravel.com in v1 (table may lag; document that majors list is config-overridable)

---

## Caching Composer audit

1. Cache key: hash of absolute path to `composer.lock` + filemtime + filesize.
2. Store under agent storage (Laravel: `storage_path(config('appradar.storage_path').'/maintenance')`; plain PHP: beside config or sys temp with clear prefix).
3. On cache hit: map cached advisories → issues (no process).
4. On miss: run `composer audit --format=json`, parse, write cache, return issues.
5. Fail open: if composer missing / timeout → single **warn** `composer_audit_unavailable`, do not error the whole status endpoint.

---

## Security section cleanup (same PR or immediate follow-up commit)

- Remove `ComposerAuditProbe` from `SecurityCheck`.
- Remove `PhpVersionEolProbe` from `SecurityCheck` (both Laravel + Php).
- Keep SSL, debug, secrets, etc. in security.
- Update security tests / README so PHP EOL is no longer listed under security.

---

## AppRadar platform (out of scope for agent PR, note for later)

- Parse `maintenance` on status snapshots like `security`.
- Optional UI meter on app overview — **not required** to ship agent section.
- Do not block agent release on platform UI.

---

## Implementation order (checkboxes)

### Phase 1 — DTOs + StatusReport wiring

- [ ] `MaintenanceIssue`, `MaintenanceIssueCollection`, `MaintenanceStatus`, `MaintenanceScoreCalculator`
- [ ] Tests: score formula, fromArray/toArray round-trip, empty = score 100
- [ ] Add `maintenance` to `StatusReport` (+ adapters stubbing `MaintenanceStatus::clean()` until probes exist)
- [ ] Overall status aggregation includes maintenance

### Phase 2 — PHP + Laravel version probes

- [ ] Shared or duplicated `PhpVersionProbe` under Maintenance (copy logic from security probe)
- [ ] `LaravelVersionProbe` + config majors list + tests
- [ ] Wire into Laravel `MaintenanceCheck`
- [ ] Remove PHP probe from security; update security tests

### Phase 3 — Composer advisories (default on, cached)

- [ ] Cache wrapper around `ComposerAuditRunner`
- [ ] `ComposerAdvisoryProbe` → `MaintenanceIssue`s (map package advisories)
- [ ] Default enabled; tests with fake runner + fake cache
- [ ] Remove audit from security config/probes

### Phase 4 — Abandoned packages

- [ ] Probe that detects abandoned locked packages without emitting “outdated” noise
- [ ] Prefer: parse `composer outdated --direct --format=json` **only for abandoned flag** if that field exists; else Packagist `GET /packages/{name}.json` abandoned field for **direct** require packages only (cap N requests or skip if too heavy)
- [ ] If too expensive/noisy: ship warn listing from `composer show -N` abandoned if available; otherwise document as Phase 4 optional and ship without it in v1.0 of maintenance

**Recommendation if time-boxed:** ship Phases 1–3 first; abandoned as follow-up.

### Phase 5 — Plain PHP + docs

- [ ] Php `MaintenanceCheck` with PHP + optional composer audit
- [ ] README: new section, config keys, what is / isn’t flagged
- [ ] Design note under `docs/superpowers/specs/` if useful
- [ ] Bump package version only when user asks to tag

---

## Success criteria

- `/status` includes `maintenance` with `status`, `score`, `issues`.
- App with clean lock + supported PHP/Laravel → score **100**, no issues.
- Known advisory in lock → error issue + score drop.
- Outdated non-advisory package → **no** issue.
- Security section no longer runs composer audit / PHP EOL.
- `/status` remains fast when audit cache is warm (&lt; composer cold start).

## Non-goals

- Full dependency upgrade UI / PRs
- npm/yarn
- Live “latest version” matrix for all packages
- Treating major upgrades as mandatory errors while still in security support
- Platform Vue work in the same agent PR

---

## Prompt blurb (paste for another AI)

```text
Implement the Maintenance section for appradar/agent per
docs/superpowers/plans/2026-08-19-maintenance-section.md

Workspace: /Users/ismael/projects/appradar-agent

Rules:
- Separate top-level `maintenance` on StatusReport (sibling of security).
- Only real risks: composer advisories, PHP/Laravel EOL, optional abandoned.
- Ignore random outdated versions.
- VOs not arrays across public APIs; TDD; mirror SecurityStatus patterns.
- Move composer audit + PHP EOL from security into maintenance.
- Cache composer audit; default audit on.
- Do not push; commit only if I ask.
- Start Phase 1, then 2, then 3; Phase 4 abandoned only if time.
```

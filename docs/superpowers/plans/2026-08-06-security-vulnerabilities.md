# Security Vulnerabilities Section Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a `security` section to the AppRadar status payload that reports configuration and dependency vulnerabilities (posture), with rich Laravel coverage and a smaller plain-PHP subset — no live intrusion detection.

**Architecture:** Introduce `SecurityIssue` + `SecurityIssueCollection` + `SecurityStatus` DTOs. The `security` section uses the usual `status` 0/1/2 **plus** a gamified **`score` meter (0–100)** — 100 means every security check is clean (including SSL). SSL is **not** a separate top-level section; outbound TLS findings are normal `security.issues` and pull the meter down like any other finding. A `SecurityCheck` aggregator runs framework-specific probes; each probe returns a `SecurityIssueCollection` value object (never raw arrays across public APIs). Laravel probes read `config()`, env, filesystem, and optional `composer audit`. Plain PHP probes read `PhpAgentConfig`, `ini_get`, filesystem next to the config, and PHP version. SSL is checked via an **outbound TLS probe** against the app's public host (`APP_URL` / configured URL) — not via “was this `/status` request HTTPS?” (unreliable behind proxies). Live attack/middleware detection is explicitly out of scope.

**Tech Stack:** PHP 8.2+, existing AppRadar agent package patterns, PDO/ini/filesystem for plain PHP, Illuminate config/app for Laravel, Symfony Process for optional `composer audit`, PHP stream sockets (`ssl://`) + OpenSSL for certificate inspection.

## Global Constraints

- Do not add a fourth status code; reuse `StatusCodes::OK|WARN|ERROR`.
- `score` / security meter is **only** on the `security` section (other sections stay without a score).
- SSL belongs inside `security` (issues + meter), never a sibling section like `database` / `redis`.
- Do not implement live hacker/probe detection in this plan (defer to a later plan).
- Public methods pass value objects, not arrays (except existing private parsers / `toArray()` boundaries).
- Prefer small focused probe classes under `Checks/Security/`.
- Do not push; only commit when the user asks.
- Laravel remains richer; plain PHP may return fewer issues and WARN when a probe cannot run.

---

## File structure

| Path | Responsibility |
|------|----------------|
| `src/Data/SecurityIssue.php` | Single finding VO (`id`, `severity`, `title`, `message`, `remediation`) |
| `src/Data/SecurityIssueCollection.php` | Value object wrapping findings; merge/worst-severity helpers |
| `src/Data/SecurityStatus.php` | Status section DTO (`status`, `score` 0–100 meter, counts, issues) |
| `src/Data/SecurityScoreCalculator.php` | Pure VO helper: issues → meter score (100 when clean) |
| `src/Data/StatusReport.php` | Add `security` property + serialize/parse/sections |
| `src/Core/Contracts/SecurityProbeInterface.php` | `probe(): SecurityIssueCollection` |
| `src/Laravel/Checks/SecurityCheck.php` | Runs Laravel probes → `SecurityStatus` |
| `src/Laravel/Checks/Security/*.php` | One probe per concern |
| `src/Core/Security/SslCertificateResult.php` | VO: host, valid, expired, daysRemaining, errorMessage, etc. |
| `src/Core/Security/SslCertificateInspector.php` | Shared outbound TLS inspect (`ssl://host:443`) used by Laravel + PHP |
| `src/Laravel/Checks/Security/SslCertificateProbe.php` | Maps inspector result + `APP_URL` scheme → issues |
| `src/Php/Checks/SecurityCheck.php` | Runs plain-PHP probes → `SecurityStatus` |
| `src/Php/Checks/Security/*.php` | Smaller probe set (includes SSL) |
| `src/Php/Checks/Security/SslCertificateProbe.php` | Same SSL mapping using configured public URL |
| `src/Laravel/LaravelAdapter.php` | Include security in report + overall status |
| `src/Php/PhpAdapter.php` | Same |
| `config/appradar.php` | Optional `security` toggles (composer audit, SSL host/warn days) |
| `README.md` | Document security section |
| `tests/...` | PHPUnit coverage for DTOs + key probes (add phpunit if missing) |

---

## Payload shape (target)

```json
{
  "security": {
    "status": 2,
    "score": 65,
    "issue_count": 3,
    "error_count": 1,
    "warn_count": 2,
    "issues": [
      {
        "id": "app_debug_enabled",
        "severity": 2,
        "title": "Debug mode enabled",
        "message": "APP_DEBUG is true while the app environment is production.",
        "remediation": "Set APP_DEBUG=false in production."
      },
      {
        "id": "ssl_certificate_expiring_soon",
        "severity": 1,
        "title": "TLS certificate expiring soon",
        "message": "Certificate for api.example.com expires in 9 days.",
        "remediation": "Renew the certificate before expiry."
      }
    ]
  }
}
```

`severity` uses the same ints as section status (`0` ok is unused on issues; issues are `1` warn or `2` error). Section `status` = worst issue severity, or `0` if none.

### Security meter (`score`)

- Integer **0–100**, only on `security`.
- **100** = zero issues (all checks good, including SSL) — the “chase 100” target for the frontend meter.
- Deduction model (simple, predictable):

```text
score = max(0, 100 - (error_count * 20) - (warn_count * 5))
```

| Example | Score |
|---------|-------|
| No issues | **100** |
| 1 warn (e.g. SSL expiring soon) | 95 |
| 1 error (e.g. debug on) | 80 |
| 1 error + 2 warns | 70 |
| 5+ errors | floors at 0 |

- Duplicate issue ids from the same probe collapse to one finding before scoring (collection dedupes by `id`).
- Frontend can render `score` as a gauge; agent only computes the number.
- Optional composer-audit package findings each count as their own issue (can drop the meter hard if many CVEs — acceptable).

---

## v1 vulnerability catalog

### Laravel (implement all)

| Probe id | Severity | What it detects |
|----------|----------|-----------------|
| `app_debug_enabled` | error | `config('app.debug')` true when env is not `local` |
| `app_key_missing` | error | Empty / missing `config('app.key')` |
| `app_key_insecure_length` | warn | Decoded key unexpectedly short / placeholder-looking |
| `session_secure_cookie_disabled` | warn/error | `session.secure` false when env is `production` → error; other non-local → warn |
| `session_http_only_disabled` | error | `session.http_only` false |
| `session_same_site_none_insecure` | warn | SameSite `none` without secure cookie |
| `env_file_in_public` | error | `public/.env` exists |
| `git_dir_in_public` | error | `public/.git` exists |
| `vendor_web_accessible_hint` | warn | `public/vendor` exists as a directory (heuristic; may be legitimate assets — warn only) |
| `status_endpoint_public` | warn | `appradar.only_local` is false |
| `php_display_errors_on` | error | `ini_get('display_errors')` on when not local |
| `php_version_eol` | warn/error | PHP version below supported floor (config-driven list) |
| `database_empty_password` | warn | Default DB connection password empty in non-local |
| `redis_empty_password` | warn | Redis password empty/null in non-local (skip if redis unused) |
| `telescope_exposed` | warn | Laravel Telescope package present and `telescope.enabled` true without gate signal we can detect simply — if too fuzzy, only flag when `TELESCOPE_ENABLED` true in non-local |
| `composer_audit_advisories` | error/warn | Optional: run `composer audit --format=json` when `security.composer_audit` enabled; map advisories to issues (`composer_audit:{package}`) |
| `app_url_not_https` | error | Public URL scheme is `http` (not `https`) when environment is non-local |
| `ssl_host_missing` | warn | No usable host to probe (`APP_URL` empty / invalid) — skip cert check |
| `ssl_unreachable` | warn | Cannot open TLS connection to public host:443 (timeout/refused/DNS) |
| `ssl_certificate_invalid` | error | TLS handshake/cert verify failed (self-signed, untrusted chain, etc.) |
| `ssl_hostname_mismatch` | error | Certificate CN/SAN does not match probed host |
| `ssl_certificate_expired` | error | Certificate `validTo` is in the past |
| `ssl_certificate_expiring_soon` | warn | Certificate expires within `security.ssl_expiry_warn_days` (default 14) |

### Plain PHP (subset)

| Probe id | Severity | What it detects |
|----------|----------|-----------------|
| `php_display_errors_on` | error | `display_errors` on when `app.environment` is not `local` |
| `php_version_eol` | warn/error | Same as Laravel |
| `database_empty_password` | warn | Configured DB with empty password in non-local |
| `redis_empty_password` | warn | Configured Redis with empty password in non-local |
| `env_file_beside_webroot` | error | If endpoint can resolve a public dir via config `security.public_path`, check `.env` / `.git` there |
| `status_endpoint_public` | warn | `only_local` false |
| `credentials_not_configured` | warn | Neither DB nor Redis configured (posture incomplete — optional; skip if too noisy) |
| `app_url_not_https` | error | Configured `security.public_url` / `app.url` is `http` in non-local |
| `ssl_host_missing` | warn | No public URL/host configured for TLS probe |
| `ssl_unreachable` | warn | Same as Laravel |
| `ssl_certificate_invalid` | error | Same as Laravel |
| `ssl_hostname_mismatch` | error | Same as Laravel |
| `ssl_certificate_expired` | error | Same as Laravel |
| `ssl_certificate_expiring_soon` | warn | Same as Laravel |

### Explicitly later (not this plan)

- Live request probing / brute-force / scanner middleware
- Full malware / webshell scanning
- Open port scanning
- Deep Telescope/Horizon auth introspection beyond simple flags

---

### Task 1: Security DTOs + wire into StatusReport

**Files:**
- Create: `src/Data/SecurityIssue.php`
- Create: `src/Data/SecurityIssueCollection.php`
- Create: `src/Data/SecurityScoreCalculator.php`
- Create: `src/Data/SecurityStatus.php`
- Modify: `src/Data/StatusReport.php`
- Create: `tests/Data/SecurityStatusTest.php` (and add minimal PHPUnit if none exists)

**Interfaces:**
- Consumes: `StatusCodes`, `InteractsWithPayload`, `StatusSectionInterface`
- Produces: `SecurityIssue`, `SecurityIssueCollection`, `SecurityScoreCalculator`, `SecurityStatus` (with `score`); `StatusReport` includes `security`

- [ ] **Step 1: Add PHPUnit scaffolding if missing**

Create `composer.json` require-dev + scripts if needed:

```json
"require-dev": {
    "phpunit/phpunit": "^11.0"
},
"scripts": {
    "test": "phpunit"
}
```

Add `phpunit.xml` with `tests/` bootstrap via vendor autoload.

- [ ] **Step 2: Write failing tests for SecurityIssue / SecurityStatus / StatusReport**

```php
public function test_security_status_worst_severity_wins(): void
{
    $issues = new SecurityIssueCollection([
        new SecurityIssue('a', StatusCodes::WARN, 'A', 'warn msg'),
        new SecurityIssue('b', StatusCodes::ERROR, 'B', 'error msg', 'fix it'),
    ]);

    $status = SecurityStatus::fromIssues($issues);

    $this->assertSame(StatusCodes::ERROR, $status->status);
    $this->assertSame(75, $status->score); // 100 - (1*20) - (1*5)
    $this->assertSame(2, $status->issueCount);
    $this->assertSame(1, $status->errorCount);
    $this->assertSame(1, $status->warnCount);
}

public function test_security_meter_is_100_when_clean(): void
{
    $status = SecurityStatus::fromIssues(SecurityIssueCollection::empty());

    $this->assertSame(StatusCodes::OK, $status->status);
    $this->assertSame(100, $status->score);
}

public function test_status_report_round_trips_security(): void
{
    $report = StatusReport::fromArray([
        'name' => 'App',
        'environment' => 'production',
        'status' => 2,
        'checked_at' => '2026-08-06T12:00:00+00:00',
        'database' => ['status' => 0, 'connected' => true],
        'redis' => ['status' => 0, 'connected' => true],
        'scheduler' => ['status' => 0, 'running' => true, 'expected_interval_seconds' => 60],
        'queue' => ['status' => 0, 'connected' => true, 'problem_jobs' => []],
        'tests' => ['status' => 0, 'has_run' => false, 'coverage_available' => false],
        'security' => [
            'status' => 2,
            'score' => 80,
            'issue_count' => 1,
            'error_count' => 1,
            'warn_count' => 0,
            'issues' => [
                [
                    'id' => 'app_debug_enabled',
                    'severity' => 2,
                    'title' => 'Debug mode enabled',
                    'message' => 'Debug is on in production.',
                    'remediation' => 'Set APP_DEBUG=false.',
                ],
            ],
        ],
    ]);

    $this->assertSame('app_debug_enabled', $report->security->issues->first()->id);
    $this->assertSame(80, $report->security->score);
    $this->assertArrayHasKey('security', $report->toArray());
    $this->assertSame(80, $report->toArray()['security']['score']);
}
```

Adjust `fromArray` defaults for sibling sections to match existing DTO required fields (mirror current `*Status::fromArray` defaults).

- [ ] **Step 3: Run tests — expect fail**

Run: `composer test -- --filter SecurityStatus`
Expected: FAIL (classes missing)

- [ ] **Step 4: Implement DTOs**

`SecurityIssue`:

```php
final class SecurityIssue
{
    public function __construct(
        public readonly string $id,
        public readonly int $severity,
        public readonly string $title,
        public readonly string $message,
        public readonly ?string $remediation = null,
    ) {}

    public static function fromArray(array $payload): self { /* ... */ }
    public function toArray(): array { /* snake_case keys */ }
}
```

`SecurityIssueCollection`:

```php
final class SecurityIssueCollection
{
    /** @param array<int, SecurityIssue> $issues */
    public function __construct(private readonly array $issues = []) {}

    public static function empty(): self
    public function merge(self $other): self
    public function first(): ?SecurityIssue
    /** @return array<int, SecurityIssue> */
    public function all(): array
    public function count(): int
    public function errorCount(): int
    public function warnCount(): int
    public function worstSeverity(): int // OK if empty
}
```

`SecurityScoreCalculator::fromIssues(SecurityIssueCollection $issues): int` implements `max(0, 100 - errors*20 - warns*5)`.

`SecurityStatus::fromIssues(SecurityIssueCollection $issues): self` sets counts, worst `status`, and `score` via the calculator. Empty issues → `status=OK`, `score=100`.

Wire `StatusReport` constructor, `fromArray`, `sections()`, `toArray()` to include `security` (with `score`). When `security` key missing in `fromArray`, use empty OK `SecurityStatus` with `score=100` for backward compatibility.

- [ ] **Step 5: Run tests — expect pass**

Run: `composer test -- --filter Security`
Expected: PASS

- [ ] **Step 6: Commit only if user asked** (skip by default)

---

### Task 2: Security probe contract + Laravel SecurityCheck shell

**Files:**
- Create: `src/Core/Contracts/SecurityProbeInterface.php`
- Create: `src/Laravel/Checks/SecurityCheck.php`
- Modify: `src/Laravel/LaravelAdapter.php`
- Test: `tests/Laravel/SecurityCheckEmptyProbesTest.php`

**Interfaces:**
- Consumes: `SecurityIssueCollection`, `SecurityStatus`, `StatusCheckInterface`
- Produces: `SecurityProbeInterface::probe(): SecurityIssueCollection`; `SecurityCheck::run(): SecurityStatus`

- [ ] **Step 1: Write failing test — empty probe list yields OK security**

```php
$check = new SecurityCheck(/* empty probes VO or injectable list VO */);
$status = $check->run();
$this->assertSame(StatusCodes::OK, $status->status);
$this->assertSame(0, $status->issueCount);
```

Prefer injecting a `SecurityProbeSet` value object that holds probes, not a raw array parameter on the public constructor if that violates project rules — private construction from adapter may assemble probes.

- [ ] **Step 2: Implement interface + SecurityCheck aggregator**

```php
interface SecurityProbeInterface
{
    public function probe(): SecurityIssueCollection;
}

class SecurityCheck implements StatusCheckInterface
{
    public function run(): SecurityStatus
    {
        $issues = SecurityIssueCollection::empty();
        foreach ($this->probes() as $probe) {
            $issues = $issues->merge($probe->probe());
        }
        return SecurityStatus::fromIssues($issues);
    }
}
```

- [ ] **Step 3: Wire LaravelAdapter**

Include `$security = app(SecurityCheck::class)->run()` in `StatusReport` and pass into `StatusRunner::overallStatus([...])`.

- [ ] **Step 4: Run tests — pass**

---

### Task 3: Laravel environment & session probes

**Files:**
- Create: `src/Laravel/Checks/Security/AppDebugProbe.php`
- Create: `src/Laravel/Checks/Security/AppKeyProbe.php`
- Create: `src/Laravel/Checks/Security/SessionCookieProbe.php`
- Create: `src/Laravel/Checks/Security/PhpDisplayErrorsProbe.php`
- Modify: `src/Laravel/Checks/SecurityCheck.php` (register probes)
- Test: `tests/Laravel/Security/AppDebugProbeTest.php` (and siblings as needed)

**Interfaces:**
- Consumes: Laravel `config()`, `app()->environment()`, `ini_get`
- Produces: issue ids `app_debug_enabled`, `app_key_missing`, `app_key_insecure_length`, `session_secure_cookie_disabled`, `session_http_only_disabled`, `session_same_site_none_insecure`, `php_display_errors_on`

- [ ] **Step 1: Write failing unit tests with config fakes**

Use Orchestra Testbench **or** thin probes that accept a small `LaravelSecurityContext` VO (recommended to avoid full Testbench):

```php
final class LaravelSecurityContext
{
    public function __construct(
        public readonly string $environment,
        public readonly bool $debug,
        public readonly ?string $appKey,
        public readonly bool $sessionSecure,
        public readonly bool $sessionHttpOnly,
        public readonly ?string $sessionSameSite,
        public readonly bool $displayErrors,
        public readonly string $phpVersion,
        public readonly string $publicPath,
        public readonly bool $onlyLocalStatus,
        public readonly bool $databasePasswordEmpty,
        public readonly bool $redisConfigured,
        public readonly bool $redisPasswordEmpty,
        public readonly bool $telescopeEnabled,
        public readonly bool $composerAuditEnabled,
        public readonly ?string $publicUrl, // config('app.url') or appradar.security.public_url override
        public readonly bool $sslCheckEnabled,
        public readonly int $sslExpiryWarnDays,
        public readonly float $sslTimeoutSeconds,
    ) {}
}
```

Probes take `LaravelSecurityContext` in constructor; `SecurityCheck` builds context once from Laravel.

- [ ] **Step 2: Implement probes**

Each returns empty collection or one/many `SecurityIssue`s.

- [ ] **Step 3: Register in SecurityCheck**

- [ ] **Step 4: Tests pass**

---

### Task 4: Laravel filesystem & exposure probes

**Files:**
- Create: `src/Laravel/Checks/Security/PublicSensitiveFilesProbe.php`
- Create: `src/Laravel/Checks/Security/StatusEndpointExposureProbe.php`
- Create: `src/Laravel/Checks/Security/TelescopeEnabledProbe.php`
- Create: `src/Laravel/Checks/Security/DatabasePasswordProbe.php`
- Create: `src/Laravel/Checks/Security/RedisPasswordProbe.php`
- Create: `src/Laravel/Checks/Security/PhpVersionEolProbe.php`
- Modify: `config/appradar.php` — add:

```php
'security' => [
    'composer_audit' => false,
    'php_unsupported_below' => '8.2.0',
    'php_eol_below' => '8.1.0',
    'public_url' => null, // optional override; Laravel defaults to config('app.url')
    'public_path' => null, // plain PHP webroot for .env/.git checks
    'ssl_check' => true,
    'ssl_expiry_warn_days' => 14,
    'ssl_timeout_seconds' => 3.0,
],
```

**Interfaces:**
- Produces: `env_file_in_public`, `git_dir_in_public`, `vendor_web_accessible_hint`, `status_endpoint_public`, `telescope_exposed`, `database_empty_password`, `redis_empty_password`, `php_version_eol`

- [ ] **Step 1: Tests with temp directories for public path checks**

```php
$public = sys_get_temp_dir().'/appradar-public-'.uniqid();
mkdir($public);
file_put_contents($public.'/.env', 'APP_KEY=test');
// context.publicPath = $public
// assert issue id env_file_in_public
```

- [ ] **Step 2: Implement probes + register**

- [ ] **Step 3: Tests pass**

---

### Task 5: Shared SSL certificate inspector + probes (Laravel + PHP)

**Files:**
- Create: `src/Core/Security/SslCertificateResult.php`
- Create: `src/Core/Security/SslCertificateInspector.php`
- Create: `src/Laravel/Checks/Security/SslCertificateProbe.php`
- Create: `src/Php/Checks/Security/SslCertificateProbe.php`
- Modify: `config/appradar.php` — `security.ssl_*` + `security.public_url` (see Task 4)
- Modify: `src/Php/Config/PhpAgentConfig.php` — load `security.public_url` / ssl settings into config or `PhpSecurityContext`
- Test: `tests/Core/Security/SslCertificateInspectorTest.php`
- Test: `tests/Laravel/Security/SslCertificateProbeTest.php`

**Interfaces:**
- Consumes: public URL/host from Laravel `app.url` or `appradar.security.public_url`; plain PHP same config keys
- Produces: `app_url_not_https`, `ssl_host_missing`, `ssl_unreachable`, `ssl_certificate_invalid`, `ssl_hostname_mismatch`, `ssl_certificate_expired`, `ssl_certificate_expiring_soon`

**Rules:**
- SSL lives **only** under `security` (issues feed the same meter as debug/key/session checks).
- Do **not** use `request()->secure()` / `$_SERVER['HTTPS']` as proof of SSL (proxy false negatives).
- Skip entirely when `security.ssl_check` is `false` (skipped check does not invent issues; meter unaffected by SSL).
- Skip cert probe (emit `ssl_host_missing` warn only) when host cannot be parsed — that warn **does** lower the meter.
- Keep timeout short (`ssl_timeout_seconds`, default `3.0`) so `/status` stays fast.
- Never throw out of the probe — always return a `SecurityIssueCollection`.

- [ ] **Step 1: Write failing tests for result mapping**

```php
public function test_expired_cert_maps_to_error_issue(): void
{
    $result = new SslCertificateResult(
        host: 'example.com',
        reached: true,
        verified: false,
        hostnameMatches: true,
        expired: true,
        daysRemaining: -3,
        validFrom: null,
        validTo: null,
        message: 'certificate has expired',
    );

    $issues = (new SslCertificateProbe(/* context with https publicUrl */))->mapResult($result);

    $this->assertSame('ssl_certificate_expired', $issues->first()->id);
    $this->assertSame(StatusCodes::ERROR, $issues->first()->severity);
}

public function test_http_app_url_in_production_is_error(): void
{
    // context.environment = production, publicUrl = http://api.example.com
    // assert issue id app_url_not_https
}
```

`mapResult` may be a package-private method on the probe, or a dedicated `SslIssueMapper` VO collaborator — keep public API as `probe(): SecurityIssueCollection`.

- [ ] **Step 2: Implement `SslCertificateInspector`**

```php
final class SslCertificateInspector
{
    public function inspect(string $host, int $port = 443, float $timeoutSeconds = 3.0): SslCertificateResult
    {
        // stream_socket_client('ssl://'.$host.':'.$port, ..., stream_context with capture_peer_cert)
        // openssl_x509_parse on peer certificate
        // populate SslCertificateResult (reached/verified/hostname/expiry)
    }
}
```

Capture peer cert even when verify fails so expiry/hostname can still be reported when possible.

- [ ] **Step 3: Implement Laravel + PHP `SslCertificateProbe`** using the shared inspector; register in both `SecurityCheck`s.

- [ ] **Step 4: Run tests — pass** (inspector unit tests may stub streams via an injectable connector interface/`SslSocketClient` if raw sockets are hard to fake; prefer a tiny `SslTransportInterface` with a fake in tests).

---

### Task 6: Optional Composer audit probe (Laravel)

**Files:**
- Create: `src/Laravel/Checks/Security/ComposerAuditProbe.php`
- Create: `src/Laravel/Support/ComposerAuditRunner.php`
- Modify: `config/appradar.php` (`security.composer_audit`)
- Test: `tests/Laravel/Security/ComposerAuditProbeTest.php`

**Interfaces:**
- Consumes: `Symfony\Component\Process\Process`, base_path
- Produces: `composer_audit:{package}` issues; skip entirely when config disabled

- [ ] **Step 1: Write test with fake runner VO result**

```php
final class ComposerAuditResult
{
    public function __construct(
        public readonly bool $ran,
        public readonly SecurityIssueCollection $issues,
        public readonly ?string $message = null,
    ) {}
}
```

Probe maps result → collection; if `ran=false` and enabled, emit single warn `composer_audit_unavailable`.

- [ ] **Step 2: Implement runner** (`composer audit --format=json --no-interaction`) with timeout ~30s; never throw to status endpoint — catch and warn.

- [ ] **Step 3: Default `composer_audit` to `false` so `/status` stays fast.

- [ ] **Step 4: Tests pass**

---

### Task 7: Plain PHP security checks

**Files:**
- Create: `src/Php/Checks/SecurityCheck.php`
- Create: `src/Php/Checks/Security/*Probe.php` (subset listed above, including SSL from Task 5)
- Create: `src/Php/Config/PhpSecurityContext.php` (VO built from `PhpAgentConfig` + runtime; include `publicUrl` + ssl settings)
- Modify: `src/Php/PhpAdapter.php`
- Modify: `config/appradar.php` — `security.public_path` / `security.public_url` as above
- Test: `tests/Php/SecurityCheckTest.php`

**Interfaces:**
- Consumes: `PhpAgentConfig`
- Produces: `SecurityStatus` for plain PHP adapter

- [ ] **Step 1: Failing tests for display_errors + empty passwords + only_local + ssl_host_missing when no public_url**

- [ ] **Step 2: Implement probes + SecurityCheck** (reuse shared `SslCertificateInspector`)

- [ ] **Step 3: Wire `PhpAdapter` like Laravel (include in report + overall status)**

- [ ] **Step 4: Tests pass**

---

### Task 8: Docs + README

**Files:**
- Modify: `README.md`

- [ ] **Step 1: Document `security` section (including `score` meter: 100 = all clear), Laravel vs PHP coverage table, `security.composer_audit`, SSL outbound check (`public_url`, `ssl_check`, expiry warn days), and that live attack detection is not included yet**

- [ ] **Step 2: Note consumers of `StatusReport` must accept new `security` key with `score` (SDK `fromArray` already default-safe → 100)**

- [ ] **Step 3: Document that SSL is part of `security` (not its own section), uses the app's public URL over TLS from the server, and affects the meter like other findings; `ssl_unreachable` if the host cannot reach its own public DNS/IP**

- [ ] **Step 4: Document score formula for frontend gauge: `max(0, 100 - errors*20 - warns*5)`**

---

## Self-review

1. **Spec coverage:** Vulnerability posture yes; SSL inside `security` yes; security meter/`score` 100 when clean yes; live hackers deferred yes; Laravel-heavy / PHP-light yes; same status ints yes.
2. **Placeholders:** None intentional; composer audit gated; SSL gated via `ssl_check` (default on); Telescope kept simple.
3. **Types:** `SecurityIssueCollection` is the only cross-probe carrier; `SslCertificateResult` is the inspector VO; `SecurityScoreCalculator` produces the meter; `SecurityStatus` is the section DTO; adapters only receive `SecurityStatus` from `SecurityCheck::run()`.

---

## Out of scope reminders

- Intrusion / brute-force / scanner middleware (future plan)
- Changing `StatusCodes` with a dedicated `SECURITY` constant
- Putting SSL as a top-level status section next to `database` / `redis`
- Relying on `request()->secure()` as SSL proof
- Pushing to remote

# Error Reporting (Agent → AppRadar) Plan

> **For agentic workers:** Implement phase-by-phase. Agent work lives in this repo; platform UI/API lives in the AppRadar platform repo. Do not build Sentry feature-parity in v1.

**Goal:** Catch application errors in the AppRadar agent, ship them to the AppRadar platform, and show a **good** detail page (stack + context) — without becoming a full Sentry clone.

**Product stance (agreed):** Mix of “ship errors + list” **and** a solid detail page. Detail must feel useful day one. Heavy Sentry extras (session replay, performance tracing, releases UI, assignee workflows, source maps for every stack) are later or never.

**Architecture:** Agent registers an exception/error reporter (Laravel first). On uncaught (and optionally reported) exceptions it builds a scrubbed event payload, fingerprints it for grouping, and POSTs async/batched to the AppRadar ingest API. Platform stores events + groups, lists them per app, and renders a detail page with stack frames, request/env context, breadcrumbs (light), and occurrence counts. Status/health remains separate from error ingest.

**Tech Stack (agent):** PHP 8.2+, Laravel exception handler hook / `reportable`, Guzzle/HTTP client or native streams, config in `config/appradar.php`. Platform: existing AppRadar stack (API + UI) — out of this package except payload contract.

## Global Constraints

- Laravel first; plain PHP is a later phase (same payload contract).
- Never block the user request on ingest failure; fail open (log locally, drop or queue retry lightly).
- Scrub secrets by default (cookies, Authorization, `_token`, `password`, `*_SECRET`, `.env`-like keys).
- Public APIs pass value objects, not arrays (except `toArray()` / HTTP JSON boundary).
- Agent remains installable without the platform being up; errors simply won’t ingest.
- Do not replace `/status` with error reporting — they are sibling capabilities.
- YAGNI: no session replay, no APM/tracing, no source maps, no mobile SDKs in v1.

---

## What “good detail page” means (v1)

**Must have**

- Title + exception class + message
- Grouped issue (same fingerprint) with occurrence count + first/last seen
- Stacktrace with file, line, function (app frames highlighted vs vendor)
- Environment, release/version if known, PHP version
- HTTP context when present: method, URL (query scrubbed), status if available
- Timestamp, severity level
- Raw event JSON expandable for power users (optional but cheap)

**Nice soon (v1.1), not blockers**

- Short breadcrumb trail (log + query + job events, capped)
- User id / email if app opts in
- Link from AppRadar app dashboard → latest errors
- “Similar events” list under the group

**Explicitly out of scope for this plan**

- Session replay / screenshots
- Continuous performance / traces
- Full release health / commit blame
- Assignee / workflow / Slack war-room (can be a later notifications plan)
- Replacing Sentry for teams that need deep observability

---

## System split

| Piece | Owns | Does not own |
|-------|------|--------------|
| **Agent (this repo)** | Catch, scrub, fingerprint, send events | Storage, UI, alerting rules |
| **Platform ingest API** | Auth (app token), validate, store, dedupe/group | Running inside customer apps |
| **Platform UI** | List + detail page | Shipping SDK code |

```text
[Laravel app]
   exception → Agent reporter → scrub + fingerprint
        → POST /ingest/errors (async)
              → Platform store (event + group)
                    → UI list / detail
```

---

## Payload contract (v1 draft)

One event ≈ one occurrence. Platform groups by `fingerprint`.

```json
{
  "schema_version": 1,
  "sent_at": "2026-08-12T08:00:00+00:00",
  "app": {
    "environment": "production",
    "release": "1.4.2",
    "runtime": "php",
    "runtime_version": "8.3.12",
    "framework": "laravel",
    "framework_version": "12.x"
  },
  "event": {
    "event_id": "uuid",
    "timestamp": "2026-08-12T07:59:59+00:00",
    "level": "error",
    "fingerprint": ["App\\Services\\Foo", "TypeError", "abs_path:line_hash"],
    "exception": {
      "type": "TypeError",
      "message": "...",
      "stacktrace": {
        "frames": [
          {
            "filename": "app/Services/Foo.php",
            "abs_path": "/var/www/app/Services/Foo.php",
            "lineno": 42,
            "function": "App\\Services\\Foo::bar",
            "in_app": true
          }
        ]
      }
    },
    "request": {
      "method": "POST",
      "url": "https://example.com/checkout",
      "headers": { "user-agent": "..." },
      "context": {}
    },
    "tags": {
      "queue": false
    },
    "breadcrumbs": []
  }
}
```

Notes:

- `fingerprint` is computed client-side so offline/grouping stays stable; platform may refine later.
- Stack frames: prefer relative paths under app root; mark `vendor/` as `in_app: false`.
- Cap stack depth (e.g. 50 frames) and breadcrumb count (e.g. 20).
- Cap message length; truncate with marker.

---

## Agent design (this repo)

### Config (`config/appradar.php`)

```php
'errors' => [
    'enabled' => env('APPRADAR_ERRORS_ENABLED', false),
    'dsn' => env('APPRADAR_ERRORS_DSN'), // platform ingest URL + project key, or separate url/token
    'sample_rate' => 1.0,
    'send_timeout_seconds' => 2.0,
    'environment' => null, // default: app.env
    'release' => env('APPRADAR_RELEASE'),
],
```

Default **off** until DSN is set — avoids surprise outbound traffic.

### Components

| Unit | Responsibility |
|------|----------------|
| `ErrorEvent` (VO) | Immutable event + `toArray()` for JSON |
| `ErrorFingerprint` | Builds fingerprint from exception type + top in-app frame |
| `ErrorScrubber` | Redacts secrets from request/context/message |
| `StacktraceBuilder` | Exception → frames with `in_app` |
| `ErrorIngestClient` | HTTP POST; never throws to caller |
| `LaravelErrorReporter` | Hooks Laravel `reportable` / exception handling |
| Service provider registration | Only when `errors.enabled` + DSN present |

### Capture rules (v1)

- Uncaught exceptions that Laravel would report
- Optional: `report($e)` path (same pipeline)
- Do **not** capture validation exceptions / 404 by default (configurable ignore list)
- Queue/job failures: include if they go through `report()` (Laravel does for failed jobs when configured)

### Transport

- Prefer non-blocking: register `terminating` callback or fire-and-forget HTTP with short timeout
- On failure: swallow; optionally single local log line
- No heavy local queue dependency in v1 (can add later)

---

## Platform design (other repo — outline only)

### API

- `POST /api/ingest/errors` authenticated by project DSN/token
- Validate `schema_version`, size limit (e.g. 256 KB)
- Upsert **group** by fingerprint + app; insert **event**
- Rate limit per project

### UI

1. **Errors list** — groups: title, count, last seen, env
2. **Group detail** — must-haves listed above; latest event selected by default; occurrence spark/count
3. Deep links from app overview widget (“3 new errors”)

Keep the detail page calm: one column stack + side meta, not a dashboard of twelve panels.

---

## Phased delivery

### Phase 0 — Contract & spike (½–1 day)

- [ ] Freeze v1 JSON schema in this doc (adjust names once)
- [ ] Spike Laravel: catch one exception, dump payload locally
- [ ] Agree DSN shape with platform (`https://…/ingest/errors` + token)

### Phase 1 — Agent MVP (this repo)

- [x] VOs: `ErrorEvent`, collection/result types as needed
- [x] `StacktraceBuilder` + `ErrorFingerprint` + `ErrorScrubber` + tests
- [x] `ErrorIngestClient` (timeout, no throw) + tests with mocked HTTP
- [x] `LaravelErrorReporter` + provider wiring + config keys
- [x] README: enable errors, DSN, what is/ isn’t captured
- [ ] Tag release when platform can receive

### Phase 2 — Platform ingest + storage

- [x] Ingest endpoint + auth + size/rate limits (`POST /api/agent/apps/{appId}/errors`)
- [x] `app_error_groups` + `app_error_events` tables
- [x] Group upsert by fingerprint + environment
- [x] Auth list API `GET /api/apps/{slug}/errors` (UI detail page later)

### Phase 3 — Platform UI (the “good detail page”)

- [ ] Groups list per application
- [ ] Detail page: exception, stack (in-app highlight), request, meta, counts
- [ ] Empty/error states; scrubbed fields never show secrets

### Phase 4 — Hardening (still not Sentry)

- [ ] Ignore list / sample rate tuning
- [ ] Light breadcrumbs (log channel tap, capped)
- [ ] Plain PHP bootstrap (manual `AppRadar\Agent\Php\ErrorReporter::register()`)
- [ ] Basic notify hook (email/Slack on new group) — separate small plan if needed

---

## Success criteria

- Enabling one config flag + DSN starts sending Laravel errors without app code changes beyond install.
- Same exception repeats → one group, rising count, not a flood of unique issues.
- Detail page answers in &lt;5 seconds: *what broke, where, how often, on which URL/env?*
- No measurable user-facing latency from ingest in normal conditions.
- Security posture (`/status`) and error reporting do not depend on each other.

## Non-goals reminder

If a customer needs full Sentry, they can keep Sentry. AppRadar errors are **ops + product context next to health/security**, not an APM suite.

---

## Open questions (resolve before Phase 1 coding)

1. DSN format: single URL with token query/header vs separate `endpoint` + `token`?
2. Does platform already have project API tokens we reuse?
3. Multi-app / multi-environment: group key = fingerprint only, or fingerprint + release + env?

**Recommendation (implemented):** fixed SaaS webhook  
`POST https://appradar.nl/api/agent/apps/{appUuid}/errors` with `Authorization: Bearer {secret}`.  
Agent needs only `APPRADAR_APP_UUID` + `APPRADAR_SECRET` (`APPRADAR_URL` for local, e.g. `http://127.0.0.1:8000`). Group by fingerprint + environment.

---

## Suggested implementation order in this repo

1. Payload VOs + scrubber + fingerprint + stack builder (pure, tested)
2. Ingest client
3. Laravel reporter + config + README
4. Coordinate Phase 2/3 with platform; only then flip default docs to “recommended on”

Do not start UI work inside `appradar-agent`.

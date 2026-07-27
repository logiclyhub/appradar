# AppRadar Agent

Framework-aware application agent for AppRadar. Laravel is the first supported adapter and exposes health data for:

- database
- redis
- scheduler
- queue
- tests

## What This Package Does

It registers:

- `GET /status`
- `POST /status/tests/run`

It also wires:

- queue activity listeners
- scheduler activity listeners
- scheduler heartbeat registration

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

Publish the config if you want to override defaults:

```bash
php artisan vendor:publish --tag=appradar-config
```

Default config:

- route path: `status`
- only local environment: `false`
- status storage path: `app/status`
- scheduler heartbeat: every minute

## Current Scope

Framework detection is kept in place for future adapters, but the package currently supports Laravel only.

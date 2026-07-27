# AppRadar Agent

Framework-aware application agent for AppRadar. Laravel is the first supported adapter and exposes health data for:

- database
- redis
- scheduler
- queue
- tests

## What This Package Does

It registers:

- `GET /local/status`
- `POST /local/status/tests/run`

It also wires:

- queue activity listeners
- scheduler activity listeners
- scheduler heartbeat registration

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

- route path: `local/status`
- only local environment: `true`
- status storage path: `app/status`
- scheduler heartbeat: every minute

## Current Scope

Framework detection is kept in place for future adapters, but the package currently supports Laravel only.

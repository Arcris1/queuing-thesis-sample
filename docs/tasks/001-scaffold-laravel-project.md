---
id: 001
title: Scaffold Laravel project, DB & base config
status: Todo
owner: laravel-backend-engineer
plan_ref: "Phase 1 / §3,§4"
depends_on: []
---

## Objective

Stand up a fresh Laravel 12 application that serves as the single API-first backend for the whole
system, with database connectivity, environment config, and the directory conventions all later
backend tasks depend on.

## Context

This is the root of the project. Everything (geofencing, presence, AI prediction, both frontends)
talks to this one Laravel API. The agent conventions in CLAUDE.md mandate a layered structure
(`App\DTOs`, `App\Enums`, `App\Services`, `App\Actions`, `App\Http\Requests`, `App\Http\Resources`,
`App\Helpers`) and **no Repository pattern**. DB is PostgreSQL or MySQL (§4).

## Scope

**In scope**
- `composer create-project laravel/laravel backend` (or repo root) on Laravel 12, PHP 8.2+.
- Configure `.env` / `.env.example`: `DB_CONNECTION`, app URL, timezone `Asia/Manila`.
- Create empty namespaced folders: `app/DTOs`, `app/Enums`, `app/Services`, `app/Actions`,
  `app/Helpers` and register `app/Helpers/helpers.php` in `composer.json` autoload files.
- Enable `declare(strict_types=1)` baseline and PSR-12 (Pint config).
- Configure `routes/api.php` with a `/api` prefix and a `GET /api/health` smoke endpoint.

**Out of scope**
- Authentication (task 002), any domain tables (tasks 003–007), Reverb (task 018).

## Implementation notes

Use the API-only routing (install `php artisan install:api` if needed for `routes/api.php`). Add
Laravel Pint and a `composer.json` script. Keep `AppServiceProvider` clean. Confirm the helpers file
autoloads via `composer dump-autoload`.

## API / contract (if applicable)

- `GET /api/health` → `200 { "status": "ok" }` (used by all later verification steps).

## Acceptance criteria

- [ ] Laravel 12 app boots with `php artisan serve`
- [ ] `php artisan migrate` runs cleanly against the configured DB
- [ ] Namespaced folders exist and `App\Helpers` autoloads
- [ ] `GET /api/health` returns `{ "status": "ok" }`
- [ ] `vendor/bin/pint --test` passes
- [ ] `.env.example` documents all required keys

## Verification

```
composer install
php artisan migrate
php artisan serve &
curl -s localhost:8000/api/health   # expect {"status":"ok"}
vendor/bin/pint --test
```

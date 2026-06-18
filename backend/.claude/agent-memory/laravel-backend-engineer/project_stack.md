---
name: project-stack
description: Smart Queue backend stack facts — Laravel 13, PHP 8.4, Postgres, JWT (php-open-source-saver), model attribute conventions
metadata:
  type: project
---

Smart Queue backend (`backend/`) actual stack as built.

**Why:** CLAUDE.md and task files say "Laravel 12" but the scaffold is actually newer.
**How to apply:** Trust composer.json over the docs when versions matter.

- Laravel **13** (`laravel/framework: ^13.8`), PHP **8.4.18**, Postgres **17.7** (db `smart_queue`, user `arcrissilang`, no password, localhost).
- JWT: `php-open-source-saver/jwt-auth` **2.9.2** (namespace `PHPOpenSourceSaver\JWTAuth`). `config/jwt.php` present, secret set. `User implements JWTSubject` with `getJWTIdentifier()` / `getJWTCustomClaims()` (returns `['role' => $this->role->value]`).
- Laravel 13 default `User` model used attribute-based config (`#[Fillable]`, `#[Hidden]` from `Illuminate\Database\Eloquent\Attributes`). I rewrote it to classic `protected $fillable/$hidden/casts()` since it needed the JWTSubject contract + relations anyway. Other models follow the classic style too.
- `BROADCAST_CONNECTION=log` currently (Reverb not yet wired). `laravel/sanctum` is installed but auth is JWT per the plan.
- Presence/geofence/reconnect tunables live in `config/queue_system.php` (plan §15). `Heartbeat::presence_status` is a computed accessor reading those config thresholds — never a stored column.

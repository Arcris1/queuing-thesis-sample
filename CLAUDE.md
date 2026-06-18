# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project status: pre-implementation

This repository currently contains **no application source code** — only planning artifacts and
agent configuration. There is no build system, dependency manifest, or test suite yet. Do not invent
build/lint/test commands; the sections below describe what *will* exist once the stack is scaffolded.

Current contents:
- `docs/plans/smart-queue-system-plan.md` — the authoritative spec for the whole system.
- `docs/tasks/` — one task file per unit of work, derived from the plan (see "Task workflow").
- `.claude/agents/` — three project agents encoding the chosen stack and conventions (see below).

**The plan is the source of truth.** Read `docs/plans/smart-queue-system-plan.md` before any
substantial work. If code and plan diverge, reconcile and update the plan in the same change.

## What this project is

An **AI-Powered Smart Queue Management System** for university administrative offices (Registrar,
Accounting, Cashier). Four research contributions distinguish it from a normal queueing app:

0. **Service-level queueing with dynamic window routing** — the core data rule: **never queue by
   office or by physical window.** Students queue by *service*; services belong to a shared **queue
   group** (Office → Queue Group → Service); **windows** attach to queue groups via the
   `window_queue_groups` pivot. A server-side **routing engine** assigns the oldest *eligible*
   (Active + in-range) waiting ticket in a window's groups when that window becomes available. An idle
   window is widened by attaching another queue group — a one-row change, no code. Tickets carry a
   `priority` hook for future PWD/Senior/Faculty lanes. See plan §5–§7.
1. **Geofencing** — students are only eligible for service when physically within a configurable
   radius (default **15 m**) of the office. Distance is computed **server-side** via the Haversine
   formula from the phone's GPS against each office's stored coordinates. Never trust a
   client-computed distance — the client sends raw lat/lng, the API decides eligibility.
2. **Presence detection** — a heartbeat (every 30 s) drives an Active → Away → Offline → Removed
   state machine (thresholds: 2 / 5 / 10 min). A scheduled job reclaims abandoned slots; an
   about-to-be-called Away/Offline student gets a 2-min reconnect grace window before being skipped
   or moved to standby.
3. **AI wait-time prediction** — a small regression model (Linear Regression baseline, no LLM)
   predicts wait time + confidence from people-ahead, service type, office, time/day, and active
   staff count. Trained on `service_history`.

See the plan for the full data model, endpoint list, queue flow, and configurable parameters.

## Architecture

**API-first.** All business logic lives in the Laravel backend. The frontend(s) are pure REST/
WebSocket clients with zero business logic. Auth is JWT.

```
Vue frontend  ──REST (JWT) + WebSocket──▶  Laravel API  ──▶  PostgreSQL / MySQL
                                                │
                                                └──▶ ML prediction (regression)
```

Realtime queue updates broadcast over **Laravel Reverb / WebSockets**. Push notifications via FCM.

### Two frontends, one API

- **Flutter** — the **student mobile app** (join/monitor queue, GPS, heartbeat, QR scan, push).
- **Vue 3 + Tailwind** — the **staff/admin web dashboard** (live queue, presence states, analytics,
  queue controls).

Both are pure clients of the Laravel API. Keep the plan's frontend section in sync with this split.

## Project agents — follow their conventions

Three `memory: project` agents in `.claude/agents/` define non-negotiable architectural standards.
Delegate matching work to them, and follow these rules even when writing code directly:

### `laravel-backend-engineer` (backend)
- Latest Laravel, PHP 8.2+, `declare(strict_types=1)`, full type declarations.
- Layered structure: **DTOs** (readonly, `fromRequest()`/`fromArray()`), **native backed Enums**
  (with `label()`/`color()` helpers, used in model `$casts`), **Service** classes for all business
  logic, **Form Requests** for validation/authorization, **API Resources** for responses, optional
  invokable **Action** classes, **Helpers** for pure utilities.
- Controllers stay thin: validate → delegate to Service → return Resource.
- **Never use the Repository pattern** — interact with Eloquent directly via scopes/methods/builder.
- No business logic in controllers, models (beyond relations/scopes/accessors), or routes; no raw
  arrays where a DTO/Enum fits.
- Eager-load to avoid N+1; wrap multi-write ops in `DB::transaction()`; use Jobs/Queues and
  Events/Listeners for side-effects and decoupling.
- Reverb: events implement `ShouldBroadcast`, define `broadcastOn`/`broadcastAs`/`broadcastWith`,
  authorize channels in `routes/channels.php`, and document the client subscription contract.

### `vue-frontend-designer` (staff/admin web dashboard)
- Vue 3 Composition API with `<script setup>`; TypeScript when supported. Pinia for state, Vue
  Router for routing.
- Tailwind utility-first, mobile-first responsive, WCAG AA accessibility, all interactive elements
  have hover/focus/active/disabled states; handle loading/empty/error/success states.
- Small, focused, reusable components; extract shared logic into composables.

### `flutter-uiux-pro` (student mobile app)
- Flutter/Dart, Material Design 3, production-grade and App/Play Store ready.
- Semantic `ThemeData`/`ColorScheme` with light + dark mode; consistent 4/8/12/16/24/32 spacing
  scale; coherent Material 3 type scale; WCAG AA contrast (4.5:1 body).
- Purposeful, fast motion (150–300ms) via implicit animations and Hero transitions; accessible,
  responsive/adaptive layouts.

All three agents maintain notes under `.claude/agent-memory/<agent-name>/` — consult and update them.

## Task workflow (docs/tasks)

Every unit of work derived from the plan must be captured as its own task file in `docs/tasks/`
**before implementation starts**, so any agent can pick it up and execute it unambiguously.

Rules:
- One task = one file: `docs/tasks/<NNN>-<kebab-title>.md` (e.g. `012-queue-join-endpoint.md`).
  `NNN` is a zero-padded sequence number.
- Copy `docs/tasks/TEMPLATE.md` for every new task and fill in **all** sections — do not freeform.
- Keep `docs/tasks/README.md` (the index) updated: add a row when a task is created and update its
  status as it moves Todo → In Progress → Done.
- Trace every task back to a plan phase/section and forward to the agent that should own it
  (`laravel-backend-engineer`, `vue-frontend-designer`, or `flutter-uiux-pro`).
- A task is **Done** only when its acceptance criteria are met and verified. Update the file and the
  index in the same change.
- When the plan changes, add/adjust task files to match — plan and tasks must stay consistent.

## Expected commands (once scaffolded — not yet valid)

These do not work until the respective projects are created. Add the real, verified commands here
the moment each stack is scaffolded.

- Backend (Laravel): `composer install`, `php artisan migrate --seed`, `php artisan serve`,
  `php artisan test` (single test: `php artisan test --filter=TestName`), `php artisan reverb:start`,
  `php artisan queue:work`, `php artisan schedule:work` (the presence/skip job runs on the scheduler).
- Frontend (Vue): `npm install`, `npm run dev`, `npm run build`, `npm run test`, `npm run lint`.

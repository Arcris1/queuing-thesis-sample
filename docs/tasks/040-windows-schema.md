---
id: 040
title: Windows, window_queue_groups & window_assignments schema + seeder
status: Todo
owner: laravel-backend-engineer
plan_ref: "Phase 1 / §5,§6"
depends_on: [4]
---

## Objective

Model the physical service **windows**, their many-to-many attachment to **queue groups**, and the
**assignment** records that link a window to the ticket it is serving — then seed a realistic window
layout per plan §5.2.

## Context

Windows are mapped to **queue groups, not services** (§5.2) — this is what lets multiple windows share
one line and makes dynamic re-assignment a one-row change (§5.4). §6 defines `windows`
(id, office_id, name, status), `window_queue_groups` (pivot: window_id, queue_group_id), and
`window_assignments` (id, window_id, ticket_id, assigned_at, served_at). `window_assignments` is the
source of truth for who served what and feeds both the routing engine (task 041) and `service_history`
(task 022). Depends on queue groups (task 004). No Repository pattern; use a backed enum for status.

## Scope

**In scope**
- Migration `create_windows_table`: `office_id` FK, `name` (e.g. "Window 1"), `status` cast to a
  `WindowStatus` enum (Open/Idle/Closed), timestamps.
- Migration `create_window_queue_groups_table`: `window_id` FK, `queue_group_id` FK, timestamps;
  unique (`window_id`,`queue_group_id`).
- Migration `create_window_assignments_table`: `window_id` FK, `ticket_id` FK, `assigned_at`,
  `served_at` (nullable), timestamps; index (`window_id`,`served_at`).
- `Window` model (`belongsTo Office`, `belongsToMany QueueGroup` via the pivot, `hasMany
  WindowAssignment`), `WindowAssignment` model, `WindowStatus` enum with `label()`/`color()`.
- `WindowSeeder` per §5.2: **Accounting** — Window 1 & Window 2 → Accounting General, Window 3 →
  Refund; **Registrar** — a window for General Services and one for Transcript; **Cashier** — at least
  one window → Payments.

**Out of scope**
- The assignment/selection logic (task 041); dynamic attach/detach endpoints (task 043); the
  `service_history` write (task 022).

## Implementation notes

Attach windows to **queue groups** through the pivot, never directly to services. Keep `WindowStatus`
in `$casts`. The `window_assignments` "open" row (null `served_at`) represents the window's current
ticket — enforce at most one open assignment per window in the service layer (task 041). Seed window
names per office and wire the pivot rows in the seeder.

## API / contract (if applicable)

N/A — schema/seeder only (windows are operated via tasks 021/043).

## Acceptance criteria

- [ ] `windows`, `window_queue_groups`, `window_assignments` migrations run with FK constraints
- [ ] `window_queue_groups` enforces unique (window, queue_group)
- [ ] Windows attach to queue groups (not services); `$window->queueGroups` resolves
- [ ] `WindowStatus` enum backed + cast
- [ ] Seeder creates the §5.2 layout (Win1/Win2→Accounting General, Win3→Refund, + Registrar & Cashier)
- [ ] `php artisan migrate:fresh --seed` succeeds

## Verification

```
php artisan migrate:fresh --seed
php artisan tinker --execute="echo App\Models\Window::with('queueGroups')->find(1)->queueGroups->pluck('name')"
php artisan test --filter=WindowSchemaTest
```

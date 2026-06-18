---
id: 004
title: Offices, Queue Groups & Services schema + seeder
status: Todo
owner: laravel-backend-engineer
plan_ref: "Phase 1 / §5,§6"
depends_on: [1]
---

## Objective

Model the three administrative offices (with geo-coordinates and a configurable geofence radius),
the **queue groups** that form their shared waiting lines, and the services each provides — then seed
them with the grouping defined in plan §5.1 for development and demos.

## Context

The core model is **Office → Queue Group → Service** (§5). A student queues by *service*, but the
ticket waits in the service's **queue group** (a shared line with a ticket prefix). §6 defines:
`offices` (id, name, latitude, longitude, geofence_radius_m default 15); `queue_groups`
(id, office_id, name, prefix, status); `services` (id, office_id, **queue_group_id**, name,
avg_service_minutes). Radius is per-office configurable (§8, §15). `avg_service_minutes` seeds the AI
baseline (§10). No Repository pattern; use Enums for status.

## Scope

**In scope**
- Migration `create_offices_table`: `name`, `latitude` (decimal 10,7), `longitude` (decimal 10,7),
  `geofence_radius_m` (unsigned int, default 15), timestamps.
- Migration `create_queue_groups_table`: `office_id` (FK), `name`, `prefix` (string, e.g. A/R/RG/T/C),
  `status` (cast to a `QueueGroupStatus` enum: open/closed), timestamps. Unique (`office_id`,`prefix`).
- Migration `create_services_table`: `office_id` (FK), `queue_group_id` (FK), `name`,
  `avg_service_minutes` (unsigned int), timestamps.
- `Office`, `QueueGroup`, `Service` models with relationships
  (`Office hasMany QueueGroups/Services`, `QueueGroup hasMany Services`, `Service belongsTo Office &
  QueueGroup`).
- `QueueGroupStatus` backed enum with `label()`.
- `OfficeServiceSeeder` seeding offices, queue groups, and services per §5.1 (see below).

**Out of scope**
- Windows / window_queue_groups / window_assignments (task 040); queue tickets (task 005);
  distance computation (task 012).

## Implementation notes

Use decimal columns for coordinates (not float) to keep Haversine precise. Seed example coordinates
(e.g. Registrar 14.600100, 121.050100). Seed grouping per §5.1:

- **Accounting** → *General Transactions* (prefix `A`): Assessment, Payment Verification;
  *Refund* (prefix `R`): Refund Requests.
- **Registrar** → *General Services* (prefix `RG`): Enrollment Concerns, Document Requests,
  Grades Verification; *Transcript* (prefix `T`): Transcript Requests.
- **Cashier** → *Payments* (prefix `C`): Tuition Payment, Miscellaneous Fees, Official Receipts.

Seed `avg_service_minutes` from §10 (e.g. Transcript 8, Document 2, Enrollment 15; Assessment 4,
Refund 12; Tuition 2). Every service must reference both its office and its queue group.

## API / contract (if applicable)

N/A — schema/seeder only (offices/queue-groups/services are read via the catalog endpoints, task 042).

## Acceptance criteria

- [ ] `offices`, `queue_groups`, `services` migrations run cleanly with FK constraints
- [ ] `geofence_radius_m` defaults to 15; `(office_id, prefix)` is unique on `queue_groups`
- [ ] `services.queue_group_id` FK present and every seeded service belongs to a queue group
- [ ] Seeder creates 3 offices, 5 queue groups (A, R, RG, T, C), and all listed services with avg durations
- [ ] Relationships resolve (`$office->queueGroups`, `$queueGroup->services`, `$service->queueGroup`)
- [ ] `QueueGroupStatus` enum used in the model `$casts`
- [ ] `php artisan migrate:fresh --seed` succeeds

## Verification

```
php artisan migrate:fresh --seed
php artisan tinker --execute="echo App\Models\QueueGroup::count()"   # 5
php artisan tinker --execute="echo App\Models\Service::whereNull('queue_group_id')->count()"  # 0
php artisan test --filter=OfficeSeederTest
```

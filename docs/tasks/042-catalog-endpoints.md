---
id: 042
title: Catalog endpoints (offices + services grouped by queue group)
status: Todo
owner: laravel-backend-engineer
plan_ref: "Phase 1 / §7"
depends_on: [4]
---

## Objective

Expose the read-only catalog that the mobile join screen needs: the list of offices, and each office's
services **grouped under their queue group**.

## Context

Students queue by **service**, and services are organized **Office → Queue Group → Service** (§5). §7
lists the catalog endpoints `GET /api/offices` and `GET /api/offices/{office}/services`. The Flutter
join screen (task 028) consumes these to render services grouped by queue group. Read-only; query
Eloquent directly with eager loading. No Repository pattern; shape with API Resources.

## Scope

**In scope**
- `GET /api/offices` → offices with id, name, and coordinates omitted/limited as appropriate.
- `GET /api/offices/{office}/services` → the office's **queue groups**, each with its services
  (name, avg_service_minutes), so the client can render grouped sections.
- `OfficeResource`, `QueueGroupResource`, `ServiceResource` (nested) shaping the responses.
- Eager-load `office.queueGroups.services` to avoid N+1.

**Out of scope**
- Live load / current numbers (task 011); joining (task 008); windows (task 040).

## Implementation notes

Keep these public or behind light auth (match the join flow). Return only **open** queue groups by
default. The nested shape should make grouping trivial on the client: office → [queue_group →
[service]]. Cache-friendly (short TTL) since the catalog rarely changes.

## API / contract (if applicable)

- `GET /api/offices` → `200 { data: [ { id, name } ] }`
- `GET /api/offices/{office}/services` →
  `200 { data: { office, queue_groups: [ { id, name, prefix, services: [ { id, name, avg_service_minutes } ] } ] } }`
- Errors: `404` unknown office.

## Acceptance criteria

- [ ] `GET /api/offices` lists all offices
- [ ] `GET /api/offices/{office}/services` returns services nested under their queue group
- [ ] Only open queue groups returned by default
- [ ] No N+1 (eager-loaded); Resource-shaped responses
- [ ] Feature tests cover both endpoints incl. grouping and unknown-office 404

## Verification

```
php artisan test --filter=CatalogEndpointsTest
curl localhost:8000/api/offices
curl localhost:8000/api/offices/1/services   # expect queue_groups[] each with services[]
```

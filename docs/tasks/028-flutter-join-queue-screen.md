---
id: 028
title: Flutter join-queue — office/service selection
status: Todo
owner: flutter-uiux-pro
plan_ref: "Phase 6 / §5,§11"
depends_on: [11, 26, 42]
---

## Objective

Let a student browse offices and the services they offer, see the current queue load, and join a
queue for a chosen service.

## Context

The join endpoint is task 008; the catalog of services **grouped by queue group** is task 042; public
current/board data is task 011. Per the model (§5), students **queue by service** (services belong to
a queue group), not by physical window — surface services grouped under their queue group, never
windows. Material 3, accessible, all loading/empty/error states.

## Scope

**In scope**
- Office list/selector showing each office and its per-queue-group `current_number`/waiting count
  (from task 011).
- Service picker per office, **services grouped under their queue group** (from the catalog endpoint,
  task 042).
- Join action calling `POST /api/queue/join` with `{ service_id }`; on success route to the ticket
  screen (task 029).
- Loading/empty/error states; disable join while a request is in flight or if already queued.

**Out of scope**
- The live ticket screen (task 029); GPS/heartbeat (tasks 030/031).

## Implementation notes

Don't expose physical windows in the UI — only services. Reflect "already in a queue" by routing
straight to the ticket screen instead of allowing a duplicate join (server also rejects, task 008).
Use the design-system cards and states from task 026.

## API / contract (if applicable)

- `GET /api/offices/{office}/services` (services grouped by queue group, task 042) +
  `GET /api/queue/current` (per-group load, task 011).
- `POST /api/queue/join` `{ service_id }` → ticket (task 008 contract).

## Acceptance criteria

- [ ] Offices and their services (grouped by queue group) render with current load
- [ ] Selecting a service and joining (`{ service_id }`) creates a ticket and routes to the ticket screen
- [ ] Queues by service grouped under queue groups (no physical-window selection in the UI)
- [ ] Already-queued users are routed to their existing ticket, not a duplicate join
- [ ] Loading/empty/error states handled; AA contrast
- [ ] Widget test covers selection + join

## Verification

```
flutter test test/join_queue_test.dart
flutter run    # browse offices/services, join, land on ticket screen
```

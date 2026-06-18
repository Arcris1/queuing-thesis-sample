<!--
Copy this file for every new task: docs/tasks/<NNN>-<kebab-title>.md
Fill in EVERY section. Do not delete headings; write "N/A" if truly not applicable.
Keep the front-matter keys exactly as below so they stay machine-readable.
-->

---
id: NNN
title: Short imperative title (e.g. "Implement queue join endpoint")
status: Todo            # Todo | In Progress | Blocked | Done
owner: laravel-backend-engineer   # laravel-backend-engineer | vue-frontend-designer | flutter-uiux-pro
plan_ref: "Phase X — <name> / §<section>"   # link back to docs/plans/smart-queue-system-plan.md
depends_on: []         # list of task ids that must be Done first, e.g. [3, 7]
---

## Objective

One or two sentences: what this task delivers and why. Plain outcome, no implementation detail.

## Context

What an executing agent must know before starting: relevant plan sections, existing code/endpoints,
data model tables, constraints (e.g. "distance is decided server-side", "no Repository pattern").

## Scope

**In scope**
- Bullet the concrete deliverables.

**Out of scope**
- Bullet what this task explicitly does NOT cover (prevents scope creep / overlap with other tasks).

## Implementation notes

Stack-specific guidance the owning agent should follow (which DTO/Service/Resource/component/widget,
file locations, naming). Keep it directive but don't pre-write the whole solution.

## API / contract (if applicable)

- Endpoint(s): `METHOD /api/...`
- Request shape (fields, types, validation rules)
- Response shape (Resource / JSON envelope)
- Broadcast/WebSocket contract: channel name, event name, payload shape — if realtime.
- N/A if this task has no API surface.

## Acceptance criteria

Checklist that makes "Done" objective and testable. Every box must be checkable.
- [ ] Criterion 1
- [ ] Criterion 2
- [ ] Tests added/updated and passing
- [ ] Plan and `docs/tasks/README.md` index updated if anything changed

## Verification

Exact commands or steps to prove it works (e.g. `php artisan test --filter=QueueJoinTest`, manual
request example, UI states to check). State expected results.

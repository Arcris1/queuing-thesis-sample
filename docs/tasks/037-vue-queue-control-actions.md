---
id: 037
title: Vue queue control actions UI
status: Done
owner: vue-frontend-designer
plan_ref: "Phase 8 / §7"
depends_on: [21, 36]
---

## Objective

Add the staff controls to operate a queue from the dashboard — call next, mark served, skip — with
immediate feedback and live board refresh.

## Context

Staff actions are task 021 (`/api/staff/call-next|serve|skip`). They build on the live board (task
036). Calling next respects server-side eligibility/standby rules; the UI just triggers and reflects
results. Accessible, with confirmation where destructive.

## Scope

**In scope**
- "Call next" button (per office/window) and per-ticket "Serve"/"Skip" actions.
- Optimistic/affirmative feedback + reconciling with the board's live update (task 036).
- Disabled/loading states during requests; clear errors (`403`/`404`).
- Surface when a called ticket is in grace/standby (from server response).

**Out of scope**
- Server logic (task 021); analytics (task 038).

## Implementation notes

Prefer reconciling via the broadcast `queue.updated` rather than hand-rolling local state. Guard
actions by role (staff/admin). Use accessible buttons with focus/disabled states and a confirm step for
skip. Keep actions in a composable for reuse.

## API / contract (if applicable)

- `POST /api/staff/call-next` `{ office_id }`, `POST /api/staff/serve` `{ ticket_id }`,
  `POST /api/staff/skip` `{ ticket_id }` (task 021).

## Acceptance criteria

- [ ] Call-next/serve/skip trigger the correct endpoints and update the board
- [ ] Loading/disabled states during requests; errors shown clearly
- [ ] Skip has a confirmation; actions are role-guarded
- [ ] Grace/standby outcomes surfaced to the operator
- [ ] Accessible controls (keyboard + focus states)
- [ ] Component test covers call/serve/skip interactions (mocked API)

## Verification

```
npm run test
npm run dev    # as staff: call next, serve, skip; confirm board reflects each action
```

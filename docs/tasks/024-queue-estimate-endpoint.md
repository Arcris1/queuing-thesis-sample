---
id: 024
title: queue/estimate endpoint (GET /api/queue/estimate)
status: Done
owner: laravel-backend-engineer
plan_ref: "Phase 7 / §7,§10"
depends_on: [23]
---

## Objective

Expose an AI-predicted waiting time (with confidence) for a student's ticket, and wire it into the
queue status response and notifications.

## Context

§7 lists `GET /api/queue/estimate` ("for the caller's queue group"); §10 contrasts naive
`people_ahead × avg` with the model's **queue-group and window-aware** prediction + confidence.
Worked example: 8 ahead in Accounting General, avg 4 min, 2 windows serving the group →
`(8 × 4) ÷ 2 = 16 min` vs the naive 32 min. Consumes the trained model (task 023). The status endpoint
(task 010) left an `eta` field for this; broadcasts (task 019) and pushes (task 020) use the value.

## Scope

**In scope**
- `GET /api/queue/estimate` returning `{ minutes, confidence, method }` for the user's active ticket.
- `PredictionService` assembling live features (people_ahead **in the queue group**, service + its avg
  duration, **active windows serving the group**, office, current hour/day) and calling the model
  (microservice or in-Laravel per task 023).
- Fallback to the naive **window-aware** estimate `(people_ahead × avg) ÷ max(active_windows,1)` (and
  `method: "fallback"`) if the model/service is unavailable.
- Populate the `eta` field in `GET /api/queue/status` (task 010) from this service.

**Out of scope**
- Training/retraining (task 023); the dashboard accuracy widget (task 038).

## Implementation notes

Cache the feature lookup briefly to avoid recomputing per poll. Always degrade gracefully to the
naive formula so the app never shows a blank ETA. Return `method` so clients/dashboard can show
whether AI or fallback was used.

## API / contract (if applicable)

- `GET /api/queue/estimate` (auth) → `200 { data: { minutes, confidence, method } }`
- `GET /api/queue/status` now returns a populated `eta` object.
- Errors: `404` no active ticket.

## Acceptance criteria

- [x] Returns model-based `minutes` + `confidence` when the model is available
- [x] Falls back to the window-aware naive estimate with `method: "fallback"` when model is down
- [x] `eta` is populated in the status response
- [x] Live features assembled correctly (people_ahead-in-group, service avg, active_windows, office, hour, day)
- [x] Feature tests cover model-path and fallback-path

## Verification

```
php artisan test --filter=QueueEstimateTest
curl localhost:8000/api/queue/estimate -H 'Authorization: Bearer <t>'
```

---
id: 013
title: location/update endpoint + 15m eligibility (POST /api/location/update)
status: Done
owner: laravel-backend-engineer
plan_ref: "Phase 3 / §8"
depends_on: [8, 12]
---

## Objective

Accept a student's raw GPS position, compute server-side distance to their ticket's office, log it,
and return whether they are within the geofence (eligible for service).

## Context

§8: the client sends raw lat/lng; the server computes distance via the Haversine service (task 012)
and decides eligibility against the office radius (default 15 m). §11 triggers verification when ~5
remain; the warning/grace flow is task 017 — this task provides the eligibility signal it uses.

## Scope

**In scope**
- `UpdateLocationRequest` validating `latitude` (-90..90), `longitude` (-180..180), optional
  `ticket_id` (defaults to the user's active ticket).
- `LocationService::record(LocationDTO)` → compute `distance_m`, write a `location_logs` row,
  return `{ distance_m, within_radius, radius_m }`.
- Mark/keep ticket eligibility (e.g. transition `Waiting`→`Ready` when within radius and near turn)
  — coordinate with task 017's rules; default: just report eligibility here.

**Out of scope**
- Away/offline skip + grace (task 017); QR check-in (task 014); heartbeat (task 015).

## Implementation notes

Never accept a `distance` from the client — compute it. Use the active ticket's office for the target
coordinates. Store every sample in `location_logs` for analytics/audit. Return `radius_m` so the app
can show "you are X m away (limit 15 m)".

## API / contract (if applicable)

- `POST /api/location/update` (auth) → body `{ latitude, longitude, ticket_id? }`
  → `200 { data: { distance_m, within_radius, radius_m, recorded_at } }`
- Errors: `422` invalid coords, `404` no active ticket, `401`.

## Acceptance criteria

- [ ] Distance computed server-side via `DistanceService`; client distance ignored
- [ ] `within_radius` true at ≤15 m, false beyond, per office radius
- [ ] Each call writes a `location_logs` row with `distance_m`
- [ ] Invalid coordinates rejected by the Form Request
- [ ] Feature tests cover inside-radius, outside-radius, invalid coords

## Verification

```
php artisan test --filter=LocationUpdateTest
curl -X POST localhost:8000/api/location/update -H 'Authorization: Bearer <t>' \
  -d '{"latitude":14.60012,"longitude":121.05013}' -H 'Content-Type: application/json'
```

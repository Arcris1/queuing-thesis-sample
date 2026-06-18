---
id: 012
title: Haversine distance service
status: Todo
owner: laravel-backend-engineer
plan_ref: "Phase 3 / §8"
depends_on: [4]
---

## Objective

Provide a reusable, well-tested server-side distance calculator that returns the meters between a
student's GPS position and an office, used for all geofence eligibility checks.

## Context

§8 specifies the Haversine formula (R = 6,371,000 m) and the 15 m default radius. CLAUDE.md is
explicit: **distance and eligibility are decided server-side — never trust a client-computed
distance.** Office coordinates come from task 004; the radius is per-office configurable (§15).

## Scope

**In scope**
- `App\Services\DistanceService` (or `App\Helpers`) with
  `metersBetween(float $lat1, float $lon1, float $lat2, float $lon2): float`.
- `isWithinRadius(Office $office, float $lat, float $lon): bool` using `office.geofence_radius_m`.
- Pure, stateless implementation with strict types; precise to sub-meter for short distances.

**Out of scope**
- The `location/update` endpoint (task 013) and check-in (task 014) consume this service.

## Implementation notes

Implement exactly per §8: `a = sin²(Δlat/2) + cos(lat1)·cos(lat2)·sin²(Δlon/2)`,
`d = 2R·asin(√a)`. Convert degrees to radians. Validate the §8 worked example (office
14.600100/121.050100 vs 14.600120/121.050130 ≈ 8.4 m). Keep R in a constant.

## API / contract (if applicable)

N/A — internal service consumed by tasks 013/014.

## Acceptance criteria

- [ ] `metersBetween` reproduces the §8 example (~8.4 m, within ±0.2 m)
- [ ] `isWithinRadius` honors each office's `geofence_radius_m` (default 15)
- [ ] Pure/stateless, strict types, fully unit-tested incl. zero-distance and antipodal sanity cases
- [ ] No client-supplied distance is ever trusted (only lat/lon inputs)

## Verification

```
php artisan test --filter=DistanceServiceTest
php artisan tinker --execute="echo app(App\Services\DistanceService::class)->metersBetween(14.6001,121.0501,14.60012,121.05013)"
```

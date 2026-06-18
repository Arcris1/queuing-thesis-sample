---
id: 014
title: QR check-in endpoint (POST /api/checkin)
status: Todo
owner: laravel-backend-engineer
plan_ref: "Phase 3 / §8,§12"
depends_on: [8, 12]
---

## Objective

Validate a student's physical arrival by scanning an office QR code, cross-checking their ticket,
account, and GPS location in one call.

## Context

§12: "When arriving: Scan QR Code. Office validates: queue number, student account, location." The QR
encodes an office identifier/token; the app posts it alongside the student's coordinates. Reuses the
Haversine service (task 012) for the location check.

## Scope

**In scope**
- `CheckinRequest` validating `qr_token` (or `office_id` + signed token), `latitude`, `longitude`.
- `CheckinService::checkin(CheckinDTO)` — verify the QR maps to the ticket's office, the user owns an
  active ticket there, and they are within radius; on success mark ticket `Ready`/checked-in.
- Reject mismatched office, foreign ticket, or out-of-range location with distinct errors.

**Out of scope**
- Generating/rotating office QR tokens (config/admin concern); the skip flow (task 017).

## Implementation notes

Prefer a signed/expiring QR token over a raw office id to prevent spoofing. Confirm all three checks
(ticket validity, ownership, within-radius) before transitioning state, inside a transaction.

## API / contract (if applicable)

- `POST /api/checkin` (auth) → body `{ qr_token, latitude, longitude }`
  → `200 { data: { ticket_number, status, within_radius, distance_m } }`
- Errors: `422` invalid token/coords, `404` no matching active ticket, `409` out of range.

## Acceptance criteria

- [ ] Valid scan within radius transitions the ticket to checked-in/`Ready`
- [ ] QR for a different office than the ticket is rejected
- [ ] Out-of-range location is rejected with a clear error
- [ ] Location validated server-side via `DistanceService`
- [ ] Feature tests cover success, wrong-office, out-of-range, invalid token

## Verification

```
php artisan test --filter=CheckinTest
curl -X POST localhost:8000/api/checkin -H 'Authorization: Bearer <t>' \
  -d '{"qr_token":"<tok>","latitude":14.60012,"longitude":121.05013}' -H 'Content-Type: application/json'
```

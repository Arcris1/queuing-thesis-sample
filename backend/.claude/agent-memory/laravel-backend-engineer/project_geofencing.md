---
name: project-geofencing
description: Server-side geofence stack — GeofenceService (Haversine), location/update + QR checkin endpoints (tasks 012/013/014, Done).
metadata:
  type: project
---

Geofencing (research contribution #2, plan §8) built in tasks 012/013/014; all Done 2026-06-18. Core rule: distance/eligibility is decided SERVER-SIDE — client sends only raw lat/lng, never a distance.

**`app/Services/GeofenceService.php`** (pure/stateless): `distanceMeters(lat1,lng1,lat2,lng2): float` (Haversine, R=6_371_000 m const), `isWithinRadius(...,radius): bool`, `distanceToOffice(Office,lat,lng)`, `isWithinOffice(Office,lat,lng)`, `radiusFor(Office)` (office `geofence_radius_m` ?? `config('queue_system.geofence_radius_m')`, default 15). **Gotcha:** plan §8's worked example claims "≈8.4 m" for (14.6001,121.0501)→(14.60012,121.05013) but the mathematically correct distance is ~3.92 m — the unit test asserts 3.92; either way it's within 15 m. Don't "fix" the formula to hit 8.4.

**`app/Services/LocationService.php`** (injects GeofenceService): `record(User,LocationData): LocationResultData` (resolves user's active ticket or given ticket_id, computes distance, writes `location_logs` row, returns within_range signal) and `checkin(User,CheckinData): QueueTicket`. Checkin gotcha: the audit `location_logs` insert happens BEFORE the `DB::transaction()` + throw, so out-of-range attempts (which throw `OutOfRangeException`→409) are still logged and not rolled back; the Waiting→Ready transition is the only thing inside the transaction (re-reads + lockForUpdate). Both ticket resolvers are user-scoped so a scanned number can't check in someone else's ticket.

**Endpoints** (`routes/api.php`, `auth:api`): `POST /api/location/update` → `LocationResultResource` `{data:{distance_m,within_range,radius_m,recorded_at,office:{id,name}}}` (200). `POST /api/checkin` → `AssignedTicketResource` (200). Errors: no/foreign ticket → `TicketNotFoundException` (404); out-of-range → `OutOfRangeException` (409, body adds distance_m+radius_m); invalid coords → 422. Form requests in `app/Http/Requests/Location/` validate latitude `between:-90,90`, longitude `between:-180,180`; no `distance` field accepted.

**QR payload contract** (documented in `CheckinRequest` docblock): office QR encodes `{"t":"qms-checkin","ticket_number":"A-007"}`; app decodes, posts `{ticket_number, latitude, longitude}`. Identifier is the human-readable ticket_number for student self-checkin; a signed/expiring token can replace it without changing the endpoint shape.

**Deviation flagged:** checkin sets ticket → `Ready`, but `RoutingService` only selects `status=Waiting` (`scopeWaitingEligibleOldest`). So a Ready ticket is NOT currently picked up by the routing engine. Left as-is to avoid touching routing selection (task 017 coordinates the Ready/Standby semantics). Revisit if Ready tickets must be assignable.

See [[project_routing_engine]] for how `isEligible`/`isWithinGeofence` consume these location logs.

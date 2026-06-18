---
name: routing-eligibility-seam
description: How the window routing engine decides which ticket is callable (status, presence, geofence, grace)
metadata:
  type: project
---

The routing engine's candidate selection and eligibility live in `app/Services/RoutingService.php`.

- `QueueTicket::scopeWaitingEligibleOldest($queueGroupIds)` is the candidate query: selects
  `Ready` + `Waiting`, ordered priority desc → Ready-before-Waiting → FIFO by `joined_at`. A
  checked-in (Ready) ticket is assignable AND preferred at equal priority; priority outranks Ready.
- `RoutingService::isEligible()` is the per-ticket seam: status in {Ready,Waiting} AND
  `PresenceService::isPresent()` (Active) AND `isWithinGeofence()`. When a ticket recovers within a
  grace window it is reinstated (grace cleared) here.
- `lockNextEligibleTicket()` scans locked candidates in order; a borderline (in-range but Away/Offline)
  ticket is NOT skipped — it goes through `applyReconnectGrace()` (open grace → later Standby).

**Why:** the plan (§5.3) defines an "eligibility seam" tasks plug into without restructuring the engine.
**How to apply:** new gating rules (priority lanes, etc.) extend `isEligible()` / the scope, not the
transaction flow. Geofence math stays in `GeofenceService`; presence rule stays in `PresenceService`.

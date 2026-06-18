---
name: presence-grace-model
description: PresenceService owns the Active/Away/Offline/Removed state machine, reconnect grace, Standby vs Skipped
metadata:
  type: project
---

`app/Services/PresenceService.php` is the single source of truth for presence (plan §9/§15).

- Thresholds come ONLY from `config/queue_system.php` (`presence.*`, `reconnect_grace_seconds`).
  `statusFromLastSeen()` is the one rule; `Heartbeat::presence_status` accessor delegates to it via
  `app(PresenceService::class)`.
- `reclaimAbandoned()` is the scheduled scan (command `presence:evaluate`, registered every minute in
  `routes/console.php` via `Schedule::command(...)`). It moves Removed (>10m stale) in-line tickets to
  `Skipped`. Idempotent + transaction-safe + chunked.
- **Standby vs Skipped:** voluntary `/queue/leave` → `Skipped` (terminal). Missed call after grace →
  `Standby` (recoverable). Reinstatement to `Waiting` happens on the next heartbeat
  (`reinstateOnReturn`, called from `heartbeat()`) or QR check-in (sets `Ready`).
- Grace tracked by two nullable `queue_tickets` columns: `grace_until` (deadline) + `grace_offered_at`
  (one-time warning marker). `offerGrace()` opens a window once; `graceExpired()` + `moveToStandby()`
  close it.

**Why:** centralizing avoids the Active→Removed rule being duplicated across model accessor, scan, and
routing grace logic.
**How to apply:** push/broadcast on transitions are left as hook comments (tasks 019/020) — wire events
there, after commit. See [[routing-eligibility-seam]] for how grace plugs into assignment.

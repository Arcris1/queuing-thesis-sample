# Tasks Index

One row per task file in this folder. Derived from
[`../plans/smart-queue-system-plan.md`](../plans/smart-queue-system-plan.md). Create every task by
copying [`TEMPLATE.md`](./TEMPLATE.md), then add it here and keep its status current.

**Status values:** Todo · In Progress · Blocked · Done
**Owners:** `laravel-backend-engineer` (BE) · `vue-frontend-designer` (VUE) · `flutter-uiux-pro` (FL)

| ID  | Title | Plan ref | Owner | Status | Depends on |
|-----|-------|----------|-------|--------|------------|
| 001 | Scaffold Laravel project, DB & base config | Phase 1 / §3,§4 | BE | Done | — |
| 002 | JWT auth + register/login/logout endpoints | Phase 1 / §7 Auth | BE | Done | 001 |
| 003 | Users schema — migration, model, Role enum | Phase 1 / §6 | BE | Done | 001 |
| 004 | Offices, Queue Groups & Services schema + seeder | Phase 1 / §5,§6 | BE | Done | 001 |
| 005 | Queues & QueueTickets schema + TicketStatus enum | Phase 1 / §5,§6 | BE | Done | 003, 004 |
| 006 | LocationLogs & Heartbeats schema + PresenceStatus enum | Phase 1 / §6 | BE | Done | 003, 005 |
| 007 | Notifications & ServiceHistory schema | Phase 1 / §6 | BE | Done | 003, 004, 040 |
| 008 | Queue join endpoint (POST /api/queue/join) | Phase 1 / §5,§7,§11 | BE | Done | 002, 004, 005 |
| 009 | Queue leave endpoint (POST /api/queue/leave) | Phase 1 / §7 | BE | Done | 008 |
| 010 | Queue status endpoint (GET /api/queue/status) | Phase 1 / §7 | BE | Done | 008 |
| 011 | Queue current endpoint (GET /api/queue/current) | Phase 1 / §7 | BE | Done | 005 |
| 012 | Haversine distance service | Phase 3 / §8 | BE | Done | 004 |
| 013 | location/update endpoint + 15m eligibility | Phase 3 / §8 | BE | Done | 008, 012 |
| 014 | QR check-in endpoint (POST /api/checkin) | Phase 3 / §8,§12 | BE | Done | 008, 012 |
| 015 | heartbeat endpoint (POST /api/heartbeat) | Phase 4 / §9 | BE | Done | 006, 008 |
| 016 | Presence state machine + scheduled job | Phase 4 / §9 | BE | Done | 015 |
| 017 | Away/offline skip + standby + reconnect grace | Phase 4 / §9,§11 | BE | Done | 016 |
| 018 | Reverb / broadcasting setup | Phase 5 / §3 | BE | Done | 001 |
| 019 | Queue broadcast events (position/called) | Phase 5 / §12 | BE | Done | 010, 018 |
| 020 | FCM push + smart ETA notifications | Phase 5 / §12 | BE | Done | 007, 010 |
| 021 | Window/staff endpoints (available/serve/skip/recall) | Phase 2 / §7 | BE | Done | 005, 019, 041 |
| 022 | Service history capture on serve + seed data | Phase 7 / §10 | BE | Done | 021 |
| 023 | Wait-time regression training pipeline | Phase 7 / §10 | BE | Done | 022 |
| 024 | queue/estimate endpoint (GET /api/queue/estimate) | Phase 7 / §7,§10 | BE | Done | 023 |
| 025 | admin/analytics + admin live queue endpoints | Phase 8 / §7,§12 | BE | Done | 021 |
| 026 | Flutter scaffold + Material 3 design system | Phase 6 / §4 | FL | Done | 001 |
| 027 | Flutter login screen | Phase 6 / §7 Auth | FL | Done | 002, 026 |
| 028 | Flutter join-queue — office/service selection | Phase 6 / §5,§11 | FL | Done | 011, 026, 042 |
| 029 | Flutter ticket/status screen w/ realtime + ETA | Phase 6 / §12 | FL | Done | 010, 019, 024, 028 |
| 030 | Flutter background GPS location updates | Phase 6 / §8 | FL | Done | 013, 028 |
| 031 | Flutter background heartbeat service | Phase 6 / §9 | FL | Done | 015, 028 |
| 032 | Flutter QR scan check-in | Phase 6 / §12 | FL | In Progress | 014, 029 |
| 033 | Flutter push notification handling | Phase 6 / §12 | FL | In Progress | 020, 029 |
| 034 | Vue scaffold + Tailwind/Pinia/Router | Phase 8 / §4 | VUE | Done | 001 |
| 035 | Vue staff login/auth | Phase 8 / §7 Auth | VUE | Done | 002, 034 |
| 036 | Vue live queue board w/ presence states | Phase 8 / §12 | VUE | In Progress | 019, 025, 034 |
| 037 | Vue queue control actions UI | Phase 8 / §7 | VUE | In Progress | 021, 036 |
| 038 | Vue analytics dashboard w/ charts | Phase 8 / §12 | VUE | In Progress | 025, 034 |
| 039 | ISO 25010 evaluation instrument + data collection | Phase 9 / §14 | BE | In Progress | 025 |
| 040 | Windows, window_queue_groups & window_assignments schema + seeder | Phase 1 / §5,§6 | BE | Done | 004 |
| 041 | Window routing engine (oldest-eligible assignment service) | Phase 2 / §5,§7 | BE | Done | 005, 040 |
| 042 | Catalog endpoints (offices + services grouped by queue group) | Phase 1 / §7 | BE | Done | 004 |
| 043 | Admin dynamic queue-group attach/detach endpoints | Phase 8 / §7 | BE | Done | 040, 041 |

## How to add a task

1. `cp TEMPLATE.md NNN-kebab-title.md` (next sequence number, zero-padded).
2. Fill in **every** section of the new file.
3. Add a row above and set status to `Todo`.
4. Update the row's status as work progresses; a task is `Done` only when acceptance criteria are
   verified.

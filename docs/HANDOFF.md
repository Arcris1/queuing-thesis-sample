# Build Status — Smart Queue (complete 2026-06-18)

**All 43 tasks Done.** See [`tasks/README.md`](./tasks/README.md) for the per-task index.

## Verified state (all three stacks green)

| Stack | Location | Verify command | Result |
|-------|----------|----------------|--------|
| Backend (Laravel 13, PHP 8.4, Postgres) | `backend/` | `php artisan test` | **154 passing** (2976 assertions) |
| Mobile (Flutter, Material 3) | `mobile/` | `flutter analyze` · `flutter test` | clean · **34 passing** |
| Dashboard (Vue 3 + TS, Tailwind v4) | `dashboard/` | `npm run build` | **passes** (vue-tsc + Vite) |

## What was built

- **Backend (25 endpoints):** JWT auth, catalog, queue join/leave/status/current, the window **routing
  engine** (oldest-eligible, priority-aware, double-assign-safe, dynamic-enable), geofencing (server-side
  Haversine + eligibility), presence (heartbeat → Active/Away/Offline/Removed state machine + scheduled
  reclaim + standby/grace), Reverb broadcasting + pluggable push, **AI wait-time prediction** (pure-PHP
  linear regression, queue-group + window aware), admin analytics + dynamic window↔group attach/detach.
- **Mobile (student app):** login/register, office→service join, live ticket/status (polling realtime +
  AI ETA), background GPS + heartbeat, QR check-in, local notifications (FCM behind a config seam).
- **Dashboard (staff/admin):** login + role gate, live queue board with presence, window controls
  (call/serve/skip/recall) + admin attach/detach, analytics with charts.
- **Research:** ISO 25010 evaluation instrument + data-collection plan in `docs/evaluation/`.

## Run it locally

- Backend: `cd backend && php artisan migrate:fresh --seed && php artisan serve` (http://127.0.0.1:8000).
  AI model: `php artisan ml:seed-history 800 && php artisan ml:train`.
  Realtime/scheduler (optional): `php artisan reverb:start`, `php artisan queue:work`, `php artisan schedule:work`.
- Dashboard: `cd dashboard && npm run dev` (proxies `/api` → 8000).
- Mobile: `cd mobile && flutter run` (base URL defaults to `http://10.0.2.2:8000/api` for Android emulator;
  override with `--dart-define=API_BASE_URL=...`).

## Known deferrals (all intentional, documented in task files / code)

- Realtime ships as **polling** on both clients; a Reverb/Pusher **WebSocket seam** is written but off
  (enable via env/flag).
- **FCM** (push) and the Flutter/Vue WS drivers are **stubbed behind config** — no Firebase project or
  platform files needed for the demo; `mobile/lib/features/notifications/README_FCM.md` documents enabling.
- `User` has no `office_id` → staff authz is role-based; office-scoping is a marked TODO in the window/admin
  Form Requests.
- No frontend unit-test runner is configured (gates are `npm run build` / `flutter test`).
- **Nothing is committed to git** — everything is in the working tree.

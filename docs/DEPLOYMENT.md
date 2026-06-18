# Local Deployment (Docker Desktop)

The whole server stack runs via `docker-compose.yml` at the repo root.

## Services

| Service | Image / build | URL | Notes |
|---------|---------------|-----|-------|
| `db` | `postgres:17` | `localhost:5433` | Postgres (host 5433 → avoids the host's own Postgres on 5432) |
| `backend` | `backend/Dockerfile` (PHP 8.4 + Laravel 13) | http://localhost:8000 | API; on boot it migrates, seeds, creates demo accounts, and trains the AI model |
| `dashboard` | `dashboard/Dockerfile` (Vue build → nginx) | http://localhost:8090 | Staff/admin dashboard; nginx proxies `/api` + `/broadcasting` → backend |

## Run

```bash
docker compose up --build -d     # build + start everything
docker compose logs -f backend   # watch boot (migrate → seed → train → serve)
docker compose ps                # status
docker compose down              # stop (keeps DB volume)
docker compose down -v           # stop + wipe the DB volume
```

First boot takes ~1–2 min (composer install, npm build, model training). The backend
**resets to a fresh seeded state on every restart** (demo-friendly).

## Demo accounts (password for all: `password`)

| Role | Email | Use |
|------|-------|-----|
| Admin | `admin@smartqueue.test` | Dashboard — full controls + dynamic window↔group attach/detach |
| Staff | `staff@smartqueue.test` | Dashboard — live board + window controls |
| Student | `student@smartqueue.test` | Mobile app (`student_no` 2026-0001) |

Open **http://localhost:8090**, log in as admin or staff.

## Mobile app

The Flutter app is a mobile client, not a server, so it isn't in the compose stack. Run it against
the dockerized API with `cd mobile && flutter run --dart-define=API_BASE_URL=http://localhost:8000/api`
(use `http://10.0.2.2:8000/api` on the Android emulator). A Flutter-web container can be added on request.

## Notes / config choices

- `php artisan serve` only forwards allow-listed env vars to request workers, so the entrypoint
  writes DB/broadcast/queue settings into the container's `.env` before serving (see
  `backend/docker-entrypoint.sh`).
- Demo runs single-process: `BROADCAST_CONNECTION=log`, `QUEUE_CONNECTION=sync` (no separate Reverb /
  queue-worker containers). Realtime on the clients is polling by default; the Reverb path is a
  documented seam. Adding `reverb` + `queue` + `scheduler` services is straightforward if needed.
- The `db` image is `postgres:17` (Debian) — the `postgres:17-alpine` tag failed with `exec format
  error` on this Docker install.

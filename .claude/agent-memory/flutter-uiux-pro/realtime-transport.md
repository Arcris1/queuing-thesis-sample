---
name: realtime-transport
description: Smart Queue mobile realtime — polling driver is the default; a Reverb/Pusher WebSocket driver sits behind the same interface, off by default.
metadata:
  type: project
---

The live ticket/status screen (task 029) consumes realtime updates through a
transport-agnostic `RealtimeClient` interface (`lib/features/realtime/`).

**Why two drivers:** the thesis demo needs reliable "realtime" without depending
on a running Reverb socket. So polling is the shipped default and a true WebSocket
path is a wired-but-off seam.

**How to apply:**
- `PollingRealtimeClient` (default) refetches `/queue/status` every
  `AppConfig.queuePollInterval` (5s) and emits snapshot/called/cleared events. It
  enriches the ticket with `/queue/estimate` when the status lacks an ETA.
- `WsRealtimeClient` speaks the Pusher protocol Reverb uses: connect to
  `/app/<key>`, authorize private channels via `POST /broadcasting/auth`, handle
  `ticket.called` (user channel) + `queue.updated` (queue-group channel),
  reply to pings, reconnect with backoff. Enable with
  `--dart-define=REALTIME_TRANSPORT=ws` plus `REVERB_APP_KEY/HOST/PORT/SCHEME`
  (from backend `.env`). Selection happens in `realtimeClientProvider`.
- Both drivers are lifecycle-tied (start on active ticket, stop on
  leave/served/cleared) for battery. The screen never knows which is active.
- Known WS caveats to revisit before relying on it: the user-channel name uses
  ticket id as a placeholder (needs the auth user id), and `/broadcasting/auth`
  must be mounted under the `api` prefix + `auth:api` guard backend-side.

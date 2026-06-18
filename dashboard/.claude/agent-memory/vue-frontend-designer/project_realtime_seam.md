---
name: realtime-seam
description: Live board "realtime" is polling by default; Reverb/Echo swaps in behind a driver seam via VITE_REALTIME_DRIVER
metadata:
  type: project
---

The live queue board (`QueueBoardView.vue` / `useQueueBoardStore`) stays fresh through a
`RealtimeDriver` abstraction in `src/lib/realtime.ts`, not a direct WebSocket.

- Default driver = `PollingDriver` (~5s refetch of `/api/admin/queue/{office}/live`). Satisfies
  "live" for the thesis demo with zero extra infra.
- `EchoDriver` is a documented stub for Laravel Reverb (laravel-echo + pusher-js over the
  `office.{id}` / `queue-group.{id}` private channels). `createRealtimeDriver()` picks it when
  `VITE_REVERB_*` env + `VITE_REALTIME_DRIVER=echo` are set. Events are treated as refetch
  *signals* — the live endpoint stays the source of truth, so reconciliation is trivial.

**Why:** the team wanted "live" without forcing Reverb to run during the demo, but kept the
upgrade path open.

**How to apply:** never hand-roll local queue mutation in the views/store — call the action
endpoint, then `fetchBoard(true)` to reconcile. The server owns eligibility/standby/routing.
Control actions (call/serve/skip/recall, attach/detach) all follow this POST-then-refetch pattern.
See [[dashboard-stack]].

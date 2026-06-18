// Realtime seam for the live queue board.
//
// The board needs "live" updates. Two interchangeable drivers implement the same
// `RealtimeDriver` interface so the store/view never cares which is active:
//
//   1. PollingDriver (DEFAULT) — refetches the live board on a fixed interval
//      (~5s). Reliable for the thesis demo with zero extra infrastructure.
//
//   2. EchoDriver (SEAM, opt-in) — subscribes to Laravel Reverb channels via
//      laravel-echo + pusher-js:
//        - private channel `queue-group.{id}`  event `queue.updated`
//        - private channel `office.{id}`       events `ticket.called` / `queue.updated`
//      On any event it triggers a board refetch (the events are signals; the live
//      endpoint remains the source of truth, which keeps reconciliation trivial).
//
// HOW TO ENABLE REVERB (currently stubbed):
//   1. `npm install laravel-echo pusher-js`
//   2. Add to dashboard `.env`:
//        VITE_REVERB_APP_KEY=...        (from backend .env REVERB_APP_KEY)
//        VITE_REVERB_HOST=127.0.0.1
//        VITE_REVERB_PORT=8080
//        VITE_REVERB_SCHEME=http
//        VITE_REALTIME_DRIVER=echo
//   3. Implement `createEchoDriver` below (the marked stub) using the snippet in
//      the comment, then `createRealtimeDriver` will pick it up automatically.
//
// The store calls `createRealtimeDriver(...)`, which reads VITE_REALTIME_DRIVER
// and returns the right implementation. Defaults to polling.

export interface RealtimeDriver {
  /** Begin delivering refresh signals for the given office. */
  start(officeId: number): void
  /** Stop and release any resources (timers, sockets, subscriptions). */
  stop(): void
}

export interface RealtimeOptions {
  /** Called whenever the board should refetch (poll tick or socket event). */
  onRefresh: () => void
  /** Poll interval in ms (polling driver only). */
  intervalMs?: number
}

const DEFAULT_POLL_MS = 5000

/**
 * Polling driver: invokes `onRefresh` every `intervalMs`. Does NOT fire an
 * immediate refresh on start — the caller performs the initial fetch so loading
 * state is handled explicitly.
 */
class PollingDriver implements RealtimeDriver {
  private timer: ReturnType<typeof setInterval> | null = null
  private readonly onRefresh: () => void
  private readonly intervalMs: number

  constructor(opts: RealtimeOptions) {
    this.onRefresh = opts.onRefresh
    this.intervalMs = opts.intervalMs ?? DEFAULT_POLL_MS
  }

  start(_officeId: number): void {
    this.stop()
    this.timer = setInterval(() => this.onRefresh(), this.intervalMs)
  }

  stop(): void {
    if (this.timer !== null) {
      clearInterval(this.timer)
      this.timer = null
    }
  }
}

/**
 * SEAM: Reverb/Echo driver. Stubbed — falls back to behaving inertly until the
 * deps are installed and the implementation below is filled in. Kept as a class
 * so the wiring (start/stop lifecycle, channel naming) is documented in code.
 *
 * Reference implementation (uncomment after installing laravel-echo + pusher-js):
 *
 *   import Echo from 'laravel-echo'
 *   import Pusher from 'pusher-js'
 *   (window as any).Pusher = Pusher
 *   this.echo = new Echo({
 *     broadcaster: 'reverb',
 *     key: import.meta.env.VITE_REVERB_APP_KEY,
 *     wsHost: import.meta.env.VITE_REVERB_HOST,
 *     wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
 *     forceTLS: import.meta.env.VITE_REVERB_SCHEME === 'https',
 *     enabledTransports: ['ws', 'wss'],
 *     authEndpoint: '/api/broadcasting/auth',
 *     auth: { headers: { Authorization: `Bearer ${localStorage.getItem('sq_token')}` } },
 *   })
 *   this.echo.private(`office.${officeId}`)
 *     .listen('.queue.updated', () => this.onRefresh())
 *     .listen('.ticket.called', () => this.onRefresh())
 */
class EchoDriver implements RealtimeDriver {
  private readonly onRefresh: () => void
  // private echo: unknown = null   // set in the real implementation

  constructor(opts: RealtimeOptions) {
    this.onRefresh = opts.onRefresh
  }

  start(_officeId: number): void {
    // STUB: see the reference snippet above. Until wired, no events arrive — the
    // store should pair this with a slow polling backstop if used in production.
    if (import.meta.env.DEV) {
      // eslint-disable-next-line no-console
      console.warn(
        '[realtime] EchoDriver is a stub. Install laravel-echo + pusher-js and ' +
          'implement createEchoDriver to use Reverb. Falling back to no-op.',
      )
    }
    // Touch onRefresh so the field is "used" for the type-checker and future impl.
    void this.onRefresh
  }

  stop(): void {
    // this.echo?.disconnect()
  }
}

/**
 * Factory: returns the configured driver. Reads `VITE_REALTIME_DRIVER` ('polling'
 * | 'echo'); anything other than 'echo' yields the polling driver.
 */
export function createRealtimeDriver(opts: RealtimeOptions): RealtimeDriver {
  const driver = import.meta.env.VITE_REALTIME_DRIVER
  if (driver === 'echo') return new EchoDriver(opts)
  return new PollingDriver(opts)
}

// Public "Now Serving" queue display types — mirror the PUBLIC (unauthenticated)
// API contract for the wall-mounted kiosk monitors.
//
// Endpoint (no auth required):
//   GET /api/queue/current
//   → { data: OfficeCurrent[] }   (the `{ data: ... }` envelope, unwrapped one level)
//
// Returns ALL offices. `current_number` is the now-serving ticket number for the
// group (e.g. "A-007") or `null` when nobody is currently being served.

/** A queue group's live now-serving snapshot for the public display. */
export interface QueueGroupCurrent {
  id: number
  name: string
  prefix: string
  /** Now-serving ticket number (e.g. "A-007"), or null when nobody is being served. */
  current_number: string | null
  waiting_count: number
}

/** One office's public now-serving snapshot, with all of its queue groups. */
export interface OfficeCurrent {
  office: {
    id: number
    name: string
  }
  queue_groups: QueueGroupCurrent[]
}

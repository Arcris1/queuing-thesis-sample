// Queue/board types mirroring the Laravel API contract.
//
// Endpoints:
//  - GET  /api/offices
//  - GET  /api/admin/queue/{office}/live
//  - POST /api/windows/{window}/available | serve | skip | recall
//  - POST /api/admin/windows/{window}/queue-groups       { queue_group_id }
//  - DELETE /api/admin/windows/{window}/queue-groups/{queueGroup}
//
// All responses use the `{ data: ... }` envelope (unwrapped one level by callers).

export interface Office {
  id: number
  name: string
  latitude: number
  longitude: number
  geofence_radius_m: number
}

/** Ticket lifecycle status (backend enum). */
export type TicketStatus =
  | 'waiting'
  | 'called'
  | 'serving'
  | 'served'
  | 'skipped'
  | 'standby'
  | 'cancelled'
  | 'no_show'

/** Presence state machine status (backend enum). */
export type PresenceStatus = 'active' | 'away' | 'offline' | 'removed'

export interface TicketEta {
  estimated_minutes: number
  confidence: number | null
  basis: string | null
}

export interface TicketServiceRef {
  id: number
  name: string
}

export interface QueueTicket {
  id: number
  ticket_number: string
  status: TicketStatus
  priority: number
  position: number
  presence_status: PresenceStatus
  eta: TicketEta | null
  service: TicketServiceRef
}

/** Per-group tallies returned by the live board (`counts` in the API). */
export interface GroupCounts {
  waiting: number
  active: number
  away: number
  offline: number
  standby: number
}

export interface QueueGroup {
  id: number
  name: string
  prefix: string
  now_serving: string | null
  waiting_count: number
  counts: GroupCounts
  tickets: QueueTicket[]
}

/** A queue group reference as attached to a window. */
export interface QueueGroupRef {
  id: number
  name: string
  prefix: string
}

export type WindowStatus = 'available' | 'busy' | 'closed' | 'paused'

export interface AssignmentStudent {
  id: number
  name: string
  student_no: string | null
}

export interface CurrentAssignmentTicket {
  id: number
  ticket_number: string
  service: TicketServiceRef
  student: AssignmentStudent
}

export interface CurrentAssignment {
  ticket: CurrentAssignmentTicket
  since: string
}

export interface QueueWindow {
  id: number
  name: string
  status: WindowStatus
  queue_groups: QueueGroupRef[]
  current_assignment: CurrentAssignment | null
}

export interface LiveBoard {
  office: Office
  queue_groups: QueueGroup[]
  windows: QueueWindow[]
}

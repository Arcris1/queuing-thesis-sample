// Analytics types mirroring GET /api/admin/analytics?office_id=&from=&to=
// Response is wrapped in the `{ data: ... }` envelope.

export interface PeakHour {
  hour: number
  served: number
}

export interface QueueGroupBreakdown {
  queue_group_id: number
  name: string
  served: number
  avg_service_minutes: number
}

export interface ServiceBreakdown {
  service_id: number
  name: string
  served: number
  avg_service_minutes: number
}

export interface WindowBreakdown {
  window_id: number
  name: string
  served: number
  avg_service_minutes: number
}

export interface WindowUtilization {
  window_id: number
  window_name?: string
  busy_minutes: number
}

export interface AnalyticsSummary {
  avg_wait_minutes: number
  avg_service_minutes: number
  served: number
  missed: number
  peak_hours: PeakHour[]
  by_queue_group: QueueGroupBreakdown[]
  by_service: ServiceBreakdown[]
  by_window: WindowBreakdown[]
  window_utilization: WindowUtilization[]
}

export interface AnalyticsFilters {
  office_id: number | null
  from: string | null
  to: string | null
}

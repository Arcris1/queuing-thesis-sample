import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { isAxiosError } from 'axios'
import api from '@/lib/api'
import type { AnalyticsFilters, AnalyticsSummary } from '@/types/analytics'

/**
 * Analytics store — fetches the aggregated metrics for an office + date range.
 * Response uses the `{ data: ... }` envelope.
 */
export const useAnalyticsStore = defineStore('analytics', () => {
  const summary = ref<AnalyticsSummary | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)

  const filters = ref<AnalyticsFilters>({
    office_id: null,
    from: defaultFrom(),
    to: defaultTo(),
  })

  const hasData = computed(() => summary.value !== null)

  /** True when the loaded summary carries no servable activity. */
  const isEmpty = computed(() => {
    const s = summary.value
    if (!s) return false
    return s.served === 0 && s.missed === 0 && (s.peak_hours?.length ?? 0) === 0
  })

  async function fetchAnalytics(): Promise<void> {
    if (filters.value.office_id === null) return
    loading.value = true
    error.value = null
    try {
      const params: Record<string, string | number> = {
        office_id: filters.value.office_id,
      }
      if (filters.value.from) params.from = filters.value.from
      if (filters.value.to) params.to = filters.value.to

      const { data } = await api.get<{ data: AnalyticsSummary }>('/admin/analytics', { params })
      summary.value = normalize(data.data)
    } catch (err) {
      error.value = toMessage(err)
      summary.value = null
    } finally {
      loading.value = false
    }
  }

  function setFilters(next: Partial<AnalyticsFilters>): void {
    filters.value = { ...filters.value, ...next }
  }

  return { summary, loading, error, filters, hasData, isEmpty, fetchAnalytics, setFilters }
})

/** Defensive normalization — ensure arrays/numbers exist even if the API omits them. */
function normalize(raw: AnalyticsSummary): AnalyticsSummary {
  return {
    avg_wait_minutes: raw?.avg_wait_minutes ?? 0,
    avg_service_minutes: raw?.avg_service_minutes ?? 0,
    served: raw?.served ?? 0,
    missed: raw?.missed ?? 0,
    peak_hours: raw?.peak_hours ?? [],
    by_queue_group: raw?.by_queue_group ?? [],
    by_service: raw?.by_service ?? [],
    by_window: raw?.by_window ?? [],
    window_utilization: raw?.window_utilization ?? [],
  }
}

function defaultFrom(): string {
  const d = new Date()
  d.setDate(d.getDate() - 6) // last 7 days inclusive
  return toDateInput(d)
}

function defaultTo(): string {
  return toDateInput(new Date())
}

function toDateInput(d: Date): string {
  const yyyy = d.getFullYear()
  const mm = String(d.getMonth() + 1).padStart(2, '0')
  const dd = String(d.getDate()).padStart(2, '0')
  return `${yyyy}-${mm}-${dd}`
}

function toMessage(err: unknown): string {
  if (isAxiosError(err)) {
    const status = err.response?.status
    if (status === 403) return 'You do not have permission to view analytics.'
    if (status === 422) return 'Please check the selected office and date range.'
    if (!err.response) return 'Cannot reach the server. Check your connection.'
    return 'Could not load analytics. Please try again.'
  }
  return 'Could not load analytics. Please try again.'
}

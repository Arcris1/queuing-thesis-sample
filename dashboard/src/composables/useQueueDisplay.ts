import { computed, onScopeDispose, ref } from 'vue'
import { isAxiosError } from 'axios'
import api from '@/lib/api'
import type { OfficeCurrent } from '@/types/display'

/**
 * Public "Now Serving" data layer for the unauthenticated kiosk display.
 *
 * Fetches GET /api/queue/current (the public, no-auth endpoint), exposes the
 * full offices list + a getter by officeId, and polls on an interval so the
 * wall monitor stays live without anyone touching it.
 *
 * Responses use the `{ data: ... }` envelope, so we unwrap one level.
 *
 * Resilience: the offices snapshot is only replaced on a *successful* fetch, so
 * a transient poll failure keeps the last-known board on screen (an error flag
 * is raised but the numbers don't vanish). The interval is torn down on scope
 * dispose (component unmount), so navigating away never leaks a timer.
 */

export interface UseQueueDisplayOptions {
  /** Poll interval in milliseconds. Defaults to 4000 (~4s). */
  pollMs?: number
  /** Start polling immediately on creation. Defaults to true. */
  immediate?: boolean
}

const DEFAULT_POLL_MS = 4000

export function useQueueDisplay(options: UseQueueDisplayOptions = {}) {
  const pollMs = options.pollMs ?? DEFAULT_POLL_MS

  const offices = ref<OfficeCurrent[]>([])
  // `loading` is true only on the first/explicit load (drives the skeleton);
  // background polls flip `refreshing` instead so the screen never flickers.
  const loading = ref(false)
  const refreshing = ref(false)
  const error = ref<string | null>(null)
  /** Epoch ms of the last *successful* fetch (drives "updated Xs ago"). */
  const lastUpdatedAt = ref<number | null>(null)
  /** True once at least one successful fetch has populated `offices`. */
  const hasData = computed(() => lastUpdatedAt.value !== null)

  let timer: ReturnType<typeof setInterval> | null = null
  let inFlight = false

  /** Look up a single office's snapshot by id (string or number tolerated). */
  function officeById(officeId: number | string | null | undefined): OfficeCurrent | null {
    if (officeId === null || officeId === undefined) return null
    const id = Number(officeId)
    if (Number.isNaN(id)) return null
    return offices.value.find((o) => o.office?.id === id) ?? null
  }

  async function fetchCurrent(): Promise<void> {
    // Guard against overlapping requests if a poll fires while one is pending.
    if (inFlight) return
    inFlight = true

    if (!hasData.value) loading.value = true
    else refreshing.value = true

    try {
      const res = await api.get<{ data: OfficeCurrent[] }>('/queue/current')
      // Be defensive: the payload should be an array, but null/shape drift
      // shouldn't blank the board.
      const list = res.data?.data
      offices.value = Array.isArray(list) ? list : []
      lastUpdatedAt.value = Date.now()
      error.value = null
    } catch (err) {
      // Keep last-known data on a transient poll failure — only surface the flag.
      error.value = resolveError(err)
    } finally {
      loading.value = false
      refreshing.value = false
      inFlight = false
    }
  }

  function startPolling(): void {
    stopPolling()
    void fetchCurrent()
    timer = setInterval(() => void fetchCurrent(), pollMs)
  }

  function stopPolling(): void {
    if (timer !== null) {
      clearInterval(timer)
      timer = null
    }
  }

  if (options.immediate ?? true) {
    startPolling()
  }

  // Clean up the interval automatically when the owning component unmounts.
  onScopeDispose(stopPolling)

  return {
    offices,
    loading,
    refreshing,
    error,
    lastUpdatedAt,
    hasData,
    officeById,
    fetchCurrent,
    startPolling,
    stopPolling,
  }
}

function resolveError(err: unknown): string {
  if (isAxiosError(err)) {
    if (err.response) {
      return `Couldn’t reach the queue service (${err.response.status}).`
    }
    return 'Network problem — retrying…'
  }
  return 'Something went wrong loading the queue.'
}

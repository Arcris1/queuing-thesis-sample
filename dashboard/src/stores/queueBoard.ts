import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { isAxiosError } from 'axios'
import api from '@/lib/api'
import { createRealtimeDriver, type RealtimeDriver } from '@/lib/realtime'
import type { LiveBoard, Office, QueueGroup, QueueWindow } from '@/types/queue'

/**
 * Queue board store — owns the live snapshot for one selected office and keeps it
 * fresh via the realtime driver (polling by default; Reverb/Echo behind a seam).
 *
 * Responses use the `{ data: ... }` envelope, so we unwrap one level.
 *
 * Control actions (available/serve/skip/recall, attach/detach) post to the API
 * and then reconcile by refetching the live board — the server is authoritative
 * for eligibility/standby/routing, so we never hand-roll local queue state.
 */
export const useQueueBoardStore = defineStore('queueBoard', () => {
  // --- Offices ---
  const offices = ref<Office[]>([])
  const officesLoading = ref(false)
  const officesError = ref<string | null>(null)
  const selectedOfficeId = ref<number | null>(null)

  // --- Live board ---
  const board = ref<LiveBoard | null>(null)
  const boardLoading = ref(false) // true only on the first/explicit load
  const refreshing = ref(false) // true on background refetches
  const boardError = ref<string | null>(null)
  const lastUpdatedAt = ref<number | null>(null)

  // --- Per-action busy tracking (keyed by `window:{id}:{action}`) ---
  const actionPending = ref<Record<string, boolean>>({})

  let driver: RealtimeDriver | null = null

  const queueGroups = computed<QueueGroup[]>(() => board.value?.queue_groups ?? [])
  const windows = computed<QueueWindow[]>(() => board.value?.windows ?? [])

  const selectedOffice = computed<Office | null>(
    () => offices.value.find((o) => o.id === selectedOfficeId.value) ?? null,
  )

  /** Aggregate presence counts across all groups (for summary tiles). */
  const totals = computed(() => {
    let waiting = 0
    let active = 0
    let away = 0
    let offline = 0
    for (const group of queueGroups.value) {
      waiting += group.waiting_count ?? group.counts?.waiting ?? 0
      active += group.counts?.active ?? 0
      away += group.counts?.away ?? 0
      offline += group.counts?.offline ?? 0
    }
    return { waiting, active, away, offline }
  })

  function isActionPending(windowId: number, action: string): boolean {
    return actionPending.value[`${windowId}:${action}`] === true
  }

  function setActionPending(windowId: number, action: string, value: boolean) {
    actionPending.value = { ...actionPending.value, [`${windowId}:${action}`]: value }
  }

  async function fetchOffices(): Promise<void> {
    officesLoading.value = true
    officesError.value = null
    try {
      const { data } = await api.get<{ data: Office[] }>('/offices')
      offices.value = data.data ?? []
      // Default-select the first office if none chosen yet.
      if (selectedOfficeId.value === null && offices.value.length > 0) {
        selectedOfficeId.value = offices.value[0].id
      }
    } catch (err) {
      officesError.value = toMessage(err)
    } finally {
      officesLoading.value = false
    }
  }

  /** Fetch the live board for the selected office. `background` skips the spinner. */
  async function fetchBoard(background = false): Promise<void> {
    const officeId = selectedOfficeId.value
    if (officeId === null) return

    if (background) refreshing.value = true
    else boardLoading.value = true
    boardError.value = null

    try {
      const { data } = await api.get<{ data: LiveBoard }>(`/admin/queue/${officeId}/live`)
      // Guard against switching office mid-flight.
      if (selectedOfficeId.value === officeId) {
        board.value = data.data
        lastUpdatedAt.value = Date.now()
      }
    } catch (err) {
      // Don't blow away a good board on a transient background refresh failure.
      if (!background || board.value === null) board.value = background ? board.value : null
      boardError.value = toMessage(err)
    } finally {
      boardLoading.value = false
      refreshing.value = false
    }
  }

  /** Select an office and (re)start the realtime driver bound to it. */
  async function selectOffice(officeId: number): Promise<void> {
    if (selectedOfficeId.value === officeId && board.value) return
    selectedOfficeId.value = officeId
    board.value = null
    boardError.value = null
    await fetchBoard(false)
    startRealtime()
  }

  /** Start (or restart) the realtime driver for the current office. */
  function startRealtime(): void {
    stopRealtime()
    if (selectedOfficeId.value === null) return
    driver = createRealtimeDriver({ onRefresh: () => void fetchBoard(true) })
    driver.start(selectedOfficeId.value)
  }

  function stopRealtime(): void {
    driver?.stop()
    driver = null
  }

  // --- Control actions (task 037) ---

  type WindowAction = 'available' | 'serve' | 'skip' | 'recall'

  /**
   * POST a window action and reconcile via refetch. Returns the assignment ticket
   * for `available` (may be null when nothing is eligible), else null. Throws on
   * failure so callers can surface a toast.
   */
  async function windowAction(
    windowId: number,
    action: WindowAction,
  ): Promise<{ ticket_number: string } | null> {
    setActionPending(windowId, action, true)
    try {
      const { data } = await api.post<{ data: { ticket_number: string } | null }>(
        `/windows/${windowId}/${action}`,
      )
      await fetchBoard(true)
      return data.data
    } catch (err) {
      throw new Error(toMessage(err))
    } finally {
      setActionPending(windowId, action, false)
    }
  }

  async function callNext(windowId: number) {
    return windowAction(windowId, 'available')
  }
  async function serve(windowId: number) {
    return windowAction(windowId, 'serve')
  }
  async function skip(windowId: number) {
    return windowAction(windowId, 'skip')
  }
  async function recall(windowId: number) {
    return windowAction(windowId, 'recall')
  }

  /** Admin: attach a queue group to a window (§5.4 "no idle windows"). */
  async function attachQueueGroup(windowId: number, queueGroupId: number): Promise<void> {
    setActionPending(windowId, 'attach', true)
    try {
      await api.post(`/admin/windows/${windowId}/queue-groups`, {
        queue_group_id: queueGroupId,
      })
      await fetchBoard(true)
    } catch (err) {
      throw new Error(toMessage(err))
    } finally {
      setActionPending(windowId, 'attach', false)
    }
  }

  /** Admin: detach a queue group from a window. */
  async function detachQueueGroup(windowId: number, queueGroupId: number): Promise<void> {
    setActionPending(windowId, `detach:${queueGroupId}`, true)
    try {
      await api.delete(`/admin/windows/${windowId}/queue-groups/${queueGroupId}`)
      await fetchBoard(true)
    } catch (err) {
      throw new Error(toMessage(err))
    } finally {
      setActionPending(windowId, `detach:${queueGroupId}`, false)
    }
  }

  /** Tear down on unmount. */
  function dispose(): void {
    stopRealtime()
  }

  return {
    // state
    offices,
    officesLoading,
    officesError,
    selectedOfficeId,
    selectedOffice,
    board,
    boardLoading,
    refreshing,
    boardError,
    lastUpdatedAt,
    // derived
    queueGroups,
    windows,
    totals,
    // helpers
    isActionPending,
    // actions
    fetchOffices,
    fetchBoard,
    selectOffice,
    startRealtime,
    stopRealtime,
    callNext,
    serve,
    skip,
    recall,
    attachQueueGroup,
    detachQueueGroup,
    dispose,
  }
})

function toMessage(err: unknown): string {
  if (isAxiosError(err)) {
    const status = err.response?.status
    const data = err.response?.data as { message?: string } | undefined
    if (status === 403) return 'You do not have permission to do that.'
    if (status === 404) return 'That window or ticket no longer exists.'
    if (status === 409) return data?.message ?? 'That action conflicts with the current state.'
    if (status === 422) return data?.message ?? 'That action is not allowed right now.'
    if (!err.response) return 'Cannot reach the server. Check your connection.'
    return data?.message ?? 'Something went wrong. Please try again.'
  }
  if (err instanceof Error && err.message) return err.message
  return 'Something went wrong. Please try again.'
}

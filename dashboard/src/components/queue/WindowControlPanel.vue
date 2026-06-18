<script setup lang="ts">
import { computed, ref } from 'vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import { windowStatusMeta, elapsedSince } from '@/lib/queueStatus'
import { useToasts } from '@/composables/useToasts'
import { useQueueBoardStore } from '@/stores/queueBoard'
import type { CurrentAssignment, QueueGroup, QueueWindow } from '@/types/queue'

// Per-window control surface (task 037): status, current assignment, and the
// call/serve/skip/recall actions. Admin-only attach/detach of queue groups (§5.4)
// is gated behind `canManage`.
const props = defineProps<{
  window: QueueWindow
  /** All queue groups in the office — source for the attach picker. */
  allGroups: QueueGroup[]
  /** Whether the current user may mutate window↔group attachments (admin). */
  canManage: boolean
}>()

const store = useQueueBoardStore()
const toasts = useToasts()

const statusMeta = computed(() => windowStatusMeta(props.window.status))
const assignment = computed<CurrentAssignment | null>(() => props.window.current_assignment ?? null)
const hasAssignment = computed(() => assignment.value !== null)

const since = computed(() => elapsedSince(assignment.value?.since))

const attachedIds = computed(() => new Set((props.window.queue_groups ?? []).map((g) => g.id)))
const attachableGroups = computed(() =>
  (props.allGroups ?? []).filter((g) => !attachedIds.value.has(g.id)),
)

// --- Skip confirmation ---
const confirmSkipOpen = ref(false)

// --- Attach picker ---
const selectedGroupToAttach = ref<number | ''>('')

function pending(action: string): boolean {
  return store.isActionPending(props.window.id, action)
}

const anyPending = computed(
  () =>
    pending('available') ||
    pending('serve') ||
    pending('skip') ||
    pending('recall') ||
    pending('attach'),
)

async function onCallNext() {
  try {
    const result = await store.callNext(props.window.id)
    const ticket = result && 'ticket' in result ? result.ticket : null
    if (ticket) {
      toasts.success(`Called ${ticket.ticket_number} to ${props.window.name}.`)
    } else {
      toasts.info('No eligible student is waiting right now.')
    }
  } catch (err) {
    toasts.error(messageOf(err))
  }
}

async function onServe() {
  try {
    await store.serve(props.window.id)
    toasts.success('Marked as served.')
  } catch (err) {
    toasts.error(messageOf(err))
  }
}

async function onRecall() {
  try {
    await store.recall(props.window.id)
    toasts.info('Recalled the current ticket.')
  } catch (err) {
    toasts.error(messageOf(err))
  }
}

function requestSkip() {
  confirmSkipOpen.value = true
}

async function confirmSkip() {
  try {
    await store.skip(props.window.id)
    toasts.success('Ticket skipped.')
    confirmSkipOpen.value = false
  } catch (err) {
    toasts.error(messageOf(err))
    confirmSkipOpen.value = false
  }
}

async function onAttach() {
  if (selectedGroupToAttach.value === '') return
  const groupId = Number(selectedGroupToAttach.value)
  try {
    await store.attachQueueGroup(props.window.id, groupId)
    toasts.success('Queue group attached to window.')
    selectedGroupToAttach.value = ''
  } catch (err) {
    toasts.error(messageOf(err))
  }
}

async function onDetach(groupId: number, groupName: string) {
  try {
    await store.detachQueueGroup(props.window.id, groupId)
    toasts.info(`Detached ${groupName}.`)
  } catch (err) {
    toasts.error(messageOf(err))
  }
}

function messageOf(err: unknown): string {
  return err instanceof Error && err.message ? err.message : 'Something went wrong.'
}
</script>

<template>
  <section
    class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
    :aria-label="`Window ${props.window.name}`"
  >
    <!-- Header: name + status -->
    <header class="flex items-center justify-between gap-3">
      <div class="min-w-0">
        <h3 class="truncate text-sm font-semibold text-slate-900">{{ props.window.name }}</h3>
        <p v-if="props.window.queue_groups?.length" class="mt-0.5 truncate text-xs text-slate-400">
          Serving:
          <span v-for="(g, i) in props.window.queue_groups" :key="g.id">
            {{ g.prefix }}<span v-if="i < props.window.queue_groups.length - 1">, </span>
          </span>
        </p>
        <p v-else class="mt-0.5 text-xs text-amber-600">No queue groups attached</p>
      </div>
      <StatusBadge :meta="statusMeta" />
    </header>

    <!-- Current assignment -->
    <div
      v-if="hasAssignment && assignment"
      class="rounded-xl border border-brand-100 bg-brand-50/60 p-3"
    >
      <div class="flex items-center justify-between gap-2">
        <p class="text-[11px] font-medium uppercase tracking-wide text-brand-700">Now serving</p>
        <p v-if="since" class="text-xs text-slate-500 tabular-nums" aria-label="Elapsed time">
          {{ since }}
        </p>
      </div>
      <p class="mt-1 text-lg font-bold text-slate-900 tabular-nums">
        {{ assignment.ticket.ticket_number }}
      </p>
      <p class="text-sm text-slate-600">{{ assignment.ticket.service?.name }}</p>
      <p class="mt-0.5 text-xs text-slate-500">
        {{ assignment.ticket.student?.name }}
        <span v-if="assignment.ticket.student?.student_no" class="text-slate-400">
          · {{ assignment.ticket.student.student_no }}
        </span>
      </p>
    </div>
    <div
      v-else
      class="rounded-xl border border-dashed border-slate-200 px-3 py-4 text-center text-sm text-slate-500"
    >
      No active assignment
    </div>

    <!-- Action buttons -->
    <div class="grid grid-cols-2 gap-2">
      <button
        type="button"
        class="col-span-2 inline-flex items-center justify-center gap-2 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 active:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-60"
        :disabled="anyPending"
        @click="onCallNext"
      >
        <svg
          v-if="pending('available')"
          class="h-4 w-4 animate-spin"
          viewBox="0 0 24 24"
          fill="none"
          aria-hidden="true"
        >
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
        </svg>
        <span>Call next</span>
      </button>

      <button
        type="button"
        class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 disabled:cursor-not-allowed disabled:opacity-50"
        :disabled="!hasAssignment || anyPending"
        @click="onServe"
      >
        <span v-if="pending('serve')" aria-hidden="true">…</span>
        <span>Serve</span>
      </button>

      <button
        type="button"
        class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700 transition hover:bg-rose-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-600 disabled:cursor-not-allowed disabled:opacity-50"
        :disabled="!hasAssignment || anyPending"
        @click="requestSkip"
      >
        <span v-if="pending('skip')" aria-hidden="true">…</span>
        <span>Skip</span>
      </button>

      <button
        type="button"
        class="col-span-2 inline-flex items-center justify-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 disabled:cursor-not-allowed disabled:opacity-50"
        :disabled="!hasAssignment || anyPending"
        @click="onRecall"
      >
        <span v-if="pending('recall')" aria-hidden="true">…</span>
        <span>Recall</span>
      </button>
    </div>

    <!-- Admin: attach / detach queue groups (§5.4) -->
    <div v-if="props.canManage" class="border-t border-slate-100 pt-3">
      <p class="text-[11px] font-medium uppercase tracking-wide text-slate-400">
        Manage queue groups
      </p>

      <!-- Attached groups with detach -->
      <ul v-if="props.window.queue_groups?.length" class="mt-2 flex flex-wrap gap-1.5">
        <li v-for="g in props.window.queue_groups" :key="g.id">
          <span
            class="inline-flex items-center gap-1 rounded-full bg-slate-100 py-0.5 pl-2 pr-1 text-xs font-medium text-slate-700"
          >
            {{ g.name }}
            <button
              type="button"
              class="flex h-4 w-4 items-center justify-center rounded-full text-slate-400 transition hover:bg-rose-100 hover:text-rose-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-rose-600 disabled:opacity-50"
              :aria-label="`Detach ${g.name} from ${props.window.name}`"
              :disabled="store.isActionPending(props.window.id, `detach:${g.id}`) || anyPending"
              @click="onDetach(g.id, g.name)"
            >
              <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path
                  d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"
                />
              </svg>
            </button>
          </span>
        </li>
      </ul>

      <!-- Attach picker -->
      <form class="mt-2 flex items-end gap-2" @submit.prevent="onAttach">
        <div class="flex-1">
          <label :for="`attach-${props.window.id}`" class="sr-only">Attach queue group</label>
          <select
            :id="`attach-${props.window.id}`"
            v-model="selectedGroupToAttach"
            class="w-full rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-sm text-slate-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 disabled:opacity-60"
            :disabled="attachableGroups.length === 0 || anyPending"
          >
            <option value="">
              {{ attachableGroups.length === 0 ? 'All groups attached' : 'Add a queue group…' }}
            </option>
            <option v-for="g in attachableGroups" :key="g.id" :value="g.id">{{ g.name }}</option>
          </select>
        </div>
        <button
          type="submit"
          class="inline-flex items-center justify-center rounded-lg border border-brand-200 bg-brand-50 px-3 py-1.5 text-sm font-semibold text-brand-700 transition hover:bg-brand-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="selectedGroupToAttach === '' || pending('attach') || anyPending"
        >
          {{ pending('attach') ? 'Adding…' : 'Add' }}
        </button>
      </form>
    </div>

    <ConfirmDialog
      :open="confirmSkipOpen"
      title="Skip this ticket?"
      :message="
        assignment
          ? `${assignment.ticket.ticket_number} will be skipped and moved out of the active slot. This cannot be undone.`
          : 'This ticket will be skipped.'
      "
      confirm-label="Skip ticket"
      tone="danger"
      :busy="pending('skip')"
      @confirm="confirmSkip"
      @cancel="confirmSkipOpen = false"
    />
  </section>
</template>

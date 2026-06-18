<script setup lang="ts">
import { computed } from 'vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { presenceMeta, ticketStatusMeta, formatConfidence } from '@/lib/queueStatus'
import type { QueueTicket } from '@/types/queue'

// One ticket row in the waiting list. Reusable across the live board and the
// queue-control panels. Presentational only — no actions live here.
const props = defineProps<{
  ticket: QueueTicket
}>()

const presence = computed(() => presenceMeta(props.ticket.presence_status))
const status = computed(() => ticketStatusMeta(props.ticket.status))

const eta = computed(() => props.ticket.eta)
const confidenceLabel = computed(() => formatConfidence(eta.value?.confidence))

const isPriority = computed(() => (props.ticket.priority ?? 0) > 0)
</script>

<template>
  <li
    class="flex items-center gap-3 rounded-xl border border-slate-100 bg-white px-3 py-2.5 transition hover:border-slate-200"
  >
    <!-- Position -->
    <span
      class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600 tabular-nums"
      aria-hidden="true"
    >
      {{ props.ticket.position }}
    </span>

    <div class="min-w-0 flex-1">
      <div class="flex items-center gap-2">
        <span class="truncate text-sm font-semibold text-slate-900 tabular-nums">
          {{ props.ticket.ticket_number }}
        </span>
        <span
          v-if="isPriority"
          class="inline-flex items-center gap-1 rounded-full bg-violet-50 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-violet-700 ring-1 ring-inset ring-violet-600/20"
        >
          <span aria-hidden="true">★</span>
          <span>Priority</span>
        </span>
      </div>
      <p class="truncate text-xs text-slate-500">{{ props.ticket.service?.name ?? 'Service' }}</p>
    </div>

    <!-- ETA -->
    <div v-if="eta" class="hidden text-right sm:block">
      <p class="text-sm font-medium text-slate-900 tabular-nums">~{{ eta.estimated_minutes }} min</p>
      <p class="text-[11px] text-slate-400">
        <span v-if="confidenceLabel">{{ confidenceLabel }} conf.</span>
        <span v-else>est. wait</span>
      </p>
    </div>

    <!-- Presence + status badges -->
    <div class="flex shrink-0 flex-col items-end gap-1">
      <StatusBadge :meta="presence" compact />
      <StatusBadge :meta="status" compact />
    </div>
  </li>
</template>

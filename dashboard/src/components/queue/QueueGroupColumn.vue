<script setup lang="ts">
import { computed } from 'vue'
import QueueTicketRow from '@/components/queue/QueueTicketRow.vue'
import { PRESENCE_META } from '@/lib/queueStatus'
import type { QueueGroup } from '@/types/queue'

// One column/card per queue group on the live board: now-serving headline,
// waiting count, presence breakdown, and the ordered ticket list.
const props = defineProps<{
  group: QueueGroup
}>()

const tickets = computed(() => props.group.tickets ?? [])

const presenceTiles = computed(() => {
  const c = props.group.counts ?? { active: 0, away: 0, offline: 0 }
  return [
    { key: 'active', count: c.active ?? 0, meta: PRESENCE_META.active },
    { key: 'away', count: c.away ?? 0, meta: PRESENCE_META.away },
    { key: 'offline', count: c.offline ?? 0, meta: PRESENCE_META.offline },
  ]
})
</script>

<template>
  <section
    class="flex flex-col rounded-2xl border border-slate-200 bg-white shadow-sm"
    :aria-label="`Queue group ${props.group.name}`"
  >
    <!-- Header -->
    <header class="border-b border-slate-100 p-4">
      <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
          <div class="flex items-center gap-2">
            <span
              class="inline-flex items-center rounded-md bg-brand-50 px-1.5 py-0.5 text-xs font-bold text-brand-700"
            >
              {{ props.group.prefix }}
            </span>
            <h2 class="truncate text-sm font-semibold text-slate-900">{{ props.group.name }}</h2>
          </div>
        </div>
        <div class="shrink-0 text-right">
          <p class="text-[11px] font-medium uppercase tracking-wide text-slate-400">Waiting</p>
          <p class="text-lg font-bold leading-none text-slate-900 tabular-nums">
            {{ props.group.waiting_count ?? 0 }}
          </p>
        </div>
      </div>

      <!-- Now serving -->
      <div class="mt-3 rounded-xl bg-slate-50 px-3 py-2.5">
        <p class="text-[11px] font-medium uppercase tracking-wide text-slate-400">Now serving</p>
        <p
          class="mt-0.5 text-2xl font-bold tabular-nums"
          :class="props.group.now_serving ? 'text-brand-700' : 'text-slate-300'"
        >
          {{ props.group.now_serving ?? '—' }}
        </p>
      </div>

      <!-- Presence breakdown -->
      <dl class="mt-3 grid grid-cols-3 gap-2">
        <div
          v-for="tile in presenceTiles"
          :key="tile.key"
          class="flex flex-col items-center gap-0.5 rounded-lg px-1 py-1.5 ring-1 ring-inset"
          :class="tile.meta.classes"
        >
          <dt class="flex items-center gap-1 text-[11px] font-medium">
            <span class="h-1.5 w-1.5 rounded-full" :class="tile.meta.dot" aria-hidden="true" />
            {{ tile.meta.label }}
          </dt>
          <dd class="text-base font-bold leading-none tabular-nums">{{ tile.count }}</dd>
        </div>
      </dl>
    </header>

    <!-- Ticket list -->
    <div class="flex-1 p-3">
      <ul v-if="tickets.length > 0" class="flex flex-col gap-2">
        <QueueTicketRow v-for="ticket in tickets" :key="ticket.id" :ticket="ticket" />
      </ul>
      <div
        v-else
        class="flex h-full min-h-24 flex-col items-center justify-center rounded-xl border border-dashed border-slate-200 px-4 py-6 text-center"
      >
        <p class="text-sm font-medium text-slate-500">No one waiting</p>
        <p class="mt-0.5 text-xs text-slate-400">Tickets will appear here as students join.</p>
      </div>
    </div>
  </section>
</template>

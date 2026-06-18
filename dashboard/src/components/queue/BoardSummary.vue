<script setup lang="ts">
import { computed } from 'vue'
import { PRESENCE_META } from '@/lib/queueStatus'

// Aggregate summary tiles across all queue groups (waiting + presence).
const props = defineProps<{
  waiting: number
  active: number
  away: number
  offline: number
}>()

const tiles = computed(() => [
  {
    key: 'waiting',
    label: 'Waiting',
    value: props.waiting,
    valueClass: 'text-slate-900',
    dot: 'bg-blue-500',
  },
  {
    key: 'active',
    label: PRESENCE_META.active.label,
    value: props.active,
    valueClass: 'text-emerald-700',
    dot: PRESENCE_META.active.dot,
  },
  {
    key: 'away',
    label: PRESENCE_META.away.label,
    value: props.away,
    valueClass: 'text-amber-700',
    dot: PRESENCE_META.away.dot,
  },
  {
    key: 'offline',
    label: PRESENCE_META.offline.label,
    value: props.offline,
    valueClass: 'text-slate-600',
    dot: PRESENCE_META.offline.dot,
  },
])
</script>

<template>
  <dl class="grid grid-cols-2 gap-3 sm:grid-cols-4">
    <div
      v-for="tile in tiles"
      :key="tile.key"
      class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
    >
      <dt class="flex items-center gap-1.5 text-xs font-medium text-slate-500">
        <span class="h-2 w-2 rounded-full" :class="tile.dot" aria-hidden="true" />
        {{ tile.label }}
      </dt>
      <dd class="mt-1 text-2xl font-bold tabular-nums" :class="tile.valueClass">{{ tile.value }}</dd>
    </div>
  </dl>
</template>

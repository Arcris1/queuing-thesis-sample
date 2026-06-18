<script setup lang="ts">
import { computed } from 'vue'

// Accessible horizontal bar list for analytics breakdowns (per-service,
// per-group, window utilization). Hand-rolled Tailwind bars — no chart lib —
// so it stays light and screen-reader friendly via <meter>/aria roles.
export interface BarItem {
  key: string | number
  label: string
  /** Numeric value that drives the bar length. */
  value: number
  /** Display string for the value (e.g. "12 served", "8.4 min"). */
  display: string
}

const props = defineProps<{
  items: BarItem[]
  emptyLabel?: string
  barClass?: string
}>()

const maxValue = computed(() => {
  const max = Math.max(0, ...props.items.map((i) => i.value))
  return max === 0 ? 1 : max
})

function widthPct(value: number): number {
  return Math.round((Math.max(0, value) / maxValue.value) * 100)
}
</script>

<template>
  <ul v-if="props.items.length > 0" class="flex flex-col gap-3">
    <li v-for="item in props.items" :key="item.key">
      <div class="flex items-baseline justify-between gap-3">
        <span class="truncate text-sm font-medium text-slate-700">{{ item.label }}</span>
        <span class="shrink-0 text-sm font-semibold text-slate-900 tabular-nums">
          {{ item.display }}
        </span>
      </div>
      <div
        class="mt-1 h-2 w-full overflow-hidden rounded-full bg-slate-100"
        role="meter"
        :aria-valuenow="item.value"
        :aria-valuemin="0"
        :aria-valuemax="maxValue"
        :aria-label="`${item.label}: ${item.display}`"
      >
        <div
          class="h-full rounded-full transition-[width] duration-500"
          :class="props.barClass ?? 'bg-brand-500'"
          :style="{ width: `${widthPct(item.value)}%` }"
        />
      </div>
    </li>
  </ul>
  <p v-else class="py-6 text-center text-sm text-slate-400">
    {{ props.emptyLabel ?? 'No data for this range.' }}
  </p>
</template>

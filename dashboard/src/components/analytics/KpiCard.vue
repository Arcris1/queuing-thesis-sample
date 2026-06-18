<script setup lang="ts">
// A single KPI tile for the analytics summary row.
const props = defineProps<{
  label: string
  value: string | number
  unit?: string
  accent?: 'brand' | 'emerald' | 'amber' | 'rose' | 'slate'
}>()

const accentClasses: Record<NonNullable<typeof props.accent>, string> = {
  brand: 'text-brand-700',
  emerald: 'text-emerald-700',
  amber: 'text-amber-700',
  rose: 'text-rose-700',
  slate: 'text-slate-900',
}
</script>

<template>
  <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ props.label }}</p>
    <p class="mt-2 flex items-baseline gap-1">
      <span
        class="text-3xl font-bold tabular-nums"
        :class="accentClasses[props.accent ?? 'slate']"
      >
        {{ props.value }}
      </span>
      <span v-if="props.unit" class="text-sm font-medium text-slate-400">{{ props.unit }}</span>
    </p>
  </div>
</template>

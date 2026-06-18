<script setup lang="ts">
import type { BadgeMeta } from '@/lib/queueStatus'

// Accessible status chip: color + glyph + label (never color alone — WCAG).
const props = defineProps<{
  meta: BadgeMeta
  /** Render compact (dot only with sr-only label) for dense rows. */
  compact?: boolean
}>()
</script>

<template>
  <span
    v-if="props.compact"
    class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset"
    :class="props.meta.classes"
  >
    <span class="h-1.5 w-1.5 rounded-full" :class="props.meta.dot" aria-hidden="true" />
    <span>{{ props.meta.label }}</span>
  </span>
  <span
    v-else
    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset"
    :class="props.meta.classes"
  >
    <span aria-hidden="true">{{ props.meta.glyph }}</span>
    <span>{{ props.meta.label }}</span>
  </span>
</template>

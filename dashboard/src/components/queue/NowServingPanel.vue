<script setup lang="ts">
// A single queue group's "NOW SERVING" panel for the public wall display.
// Renders the group name + prefix, a HUGE now-serving number, and the waiting
// count. When the now-serving number CHANGES, it flashes + counts-in to draw
// the eye (motion kept to ~150–400ms, respects reduced-motion).
import { ref, watch } from 'vue'
import type { QueueGroupCurrent } from '@/types/display'

const props = defineProps<{ group: QueueGroupCurrent }>()

// `flash` toggles the highlight animation classes for one tick when the
// served number changes to a new, non-null value.
const flash = ref(false)
let flashTimer: ReturnType<typeof setTimeout> | null = null

watch(
  () => props.group.current_number,
  (next, prev) => {
    if (next && next !== prev) {
      flash.value = false
      // Re-trigger on the next frame so the animation restarts cleanly.
      requestAnimationFrame(() => {
        flash.value = true
        if (flashTimer) clearTimeout(flashTimer)
        flashTimer = setTimeout(() => (flash.value = false), 1200)
      })
    }
  },
)
</script>

<template>
  <section
    class="flex flex-col items-center justify-center rounded-3xl border border-slate-800 bg-slate-900/70 p-6 text-center shadow-xl shadow-black/30 transition-colors duration-300 sm:p-8"
    :class="flash ? 'border-brand-400 bg-brand-950/40' : ''"
  >
    <!-- Group identity -->
    <div class="flex items-center gap-3">
      <span
        class="inline-flex items-center justify-center rounded-lg bg-brand-600/20 px-3 py-1 text-lg font-bold tracking-wider text-brand-300 sm:text-xl"
      >
        {{ group.prefix }}
      </span>
      <h2 class="text-xl font-semibold text-slate-200 sm:text-2xl">{{ group.name }}</h2>
    </div>

    <p class="mt-4 text-sm font-medium uppercase tracking-[0.3em] text-slate-500 sm:text-base">
      Now Serving
    </p>

    <!-- The HUGE number -->
    <div class="my-2 flex min-h-[1.2em] items-center justify-center">
      <span
        v-if="group.current_number"
        :key="group.current_number"
        class="block font-mono font-black leading-none tracking-tight text-white tabular-nums"
        :class="flash ? 'now-serving-flash' : ''"
        style="font-size: clamp(3.5rem, 14vw, 11rem)"
        aria-live="polite"
        :aria-label="`Now serving ${group.current_number}`"
      >
        {{ group.current_number }}
      </span>
      <span
        v-else
        class="block font-black leading-none text-slate-600"
        style="font-size: clamp(2.5rem, 9vw, 7rem)"
        aria-live="polite"
        aria-label="No one is currently being served. Please wait."
      >
        <span aria-hidden="true">—</span>
        <span class="mt-2 block text-base font-semibold uppercase tracking-widest text-slate-500 sm:text-xl">
          Please wait
        </span>
      </span>
    </div>

    <!-- Waiting count -->
    <p class="mt-3 text-lg font-semibold text-slate-300 sm:text-2xl">
      <span class="tabular-nums">{{ group.waiting_count }}</span>
      <span class="text-slate-500"> {{ group.waiting_count === 1 ? 'person' : 'people' }} waiting</span>
    </p>
  </section>
</template>

<style scoped>
/* Quick count-in: a slight scale + brightness pop when a new number lands. */
.now-serving-flash {
  animation: now-serving-pop 380ms cubic-bezier(0.22, 1, 0.36, 1);
}

@keyframes now-serving-pop {
  0% {
    transform: scale(0.82);
    opacity: 0;
    filter: brightness(2);
  }
  60% {
    transform: scale(1.06);
    opacity: 1;
  }
  100% {
    transform: scale(1);
    filter: brightness(1);
  }
}

@media (prefers-reduced-motion: reduce) {
  .now-serving-flash {
    animation: none;
  }
}
</style>

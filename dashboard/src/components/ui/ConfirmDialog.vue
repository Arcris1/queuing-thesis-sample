<script setup lang="ts">
import { nextTick, ref, watch } from 'vue'

// Accessible confirmation modal for destructive actions (e.g. Skip).
// Focus is trapped to the dialog; Escape cancels; backdrop click cancels.
const props = withDefaults(
  defineProps<{
    open: boolean
    title: string
    message: string
    confirmLabel?: string
    cancelLabel?: string
    tone?: 'danger' | 'default'
    busy?: boolean
  }>(),
  { confirmLabel: 'Confirm', cancelLabel: 'Cancel', tone: 'default', busy: false },
)

const emit = defineEmits<{ confirm: []; cancel: [] }>()

const confirmBtn = ref<HTMLButtonElement | null>(null)

watch(
  () => props.open,
  (open) => {
    if (open) nextTick(() => confirmBtn.value?.focus())
  },
)

function onCancel() {
  if (!props.busy) emit('cancel')
}
</script>

<template>
  <Transition
    enter-active-class="transition duration-150 ease-out"
    enter-from-class="opacity-0"
    enter-to-class="opacity-100"
    leave-active-class="transition duration-100 ease-in"
    leave-from-class="opacity-100"
    leave-to-class="opacity-0"
  >
    <div
      v-if="props.open"
      class="fixed inset-0 z-40 flex items-center justify-center p-4"
      role="dialog"
      aria-modal="true"
      :aria-label="props.title"
      @keydown.esc="onCancel"
    >
      <div class="absolute inset-0 bg-slate-900/40" @click="onCancel" aria-hidden="true" />

      <div class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
        <h2 class="text-lg font-semibold text-slate-900">{{ props.title }}</h2>
        <p class="mt-2 text-sm text-slate-600">{{ props.message }}</p>

        <div class="mt-6 flex justify-end gap-3">
          <button
            type="button"
            class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 disabled:opacity-60"
            :disabled="props.busy"
            @click="onCancel"
          >
            {{ props.cancelLabel }}
          </button>
          <button
            ref="confirmBtn"
            type="button"
            class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold text-white transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
            :class="
              props.tone === 'danger'
                ? 'bg-rose-600 hover:bg-rose-700 focus-visible:outline-rose-600'
                : 'bg-brand-600 hover:bg-brand-700 focus-visible:outline-brand-600'
            "
            :disabled="props.busy"
            @click="emit('confirm')"
          >
            <svg
              v-if="props.busy"
              class="h-4 w-4 animate-spin"
              viewBox="0 0 24 24"
              fill="none"
              aria-hidden="true"
            >
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
            </svg>
            <span>{{ props.confirmLabel }}</span>
          </button>
        </div>
      </div>
    </div>
  </Transition>
</template>

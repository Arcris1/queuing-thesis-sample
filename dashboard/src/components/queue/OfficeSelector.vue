<script setup lang="ts">
import type { Office } from '@/types/queue'

// Office picker for the live board / analytics. Native <select> for built-in
// keyboard handling and screen-reader support.
const props = defineProps<{
  offices: Office[]
  modelValue: number | null
  loading?: boolean
  label?: string
}>()

const emit = defineEmits<{ 'update:modelValue': [number] }>()

function onChange(event: Event) {
  const value = Number((event.target as HTMLSelectElement).value)
  if (!Number.isNaN(value)) emit('update:modelValue', value)
}
</script>

<template>
  <div class="flex flex-col gap-1.5">
    <label :for="'office-select'" class="text-xs font-medium text-slate-500">
      {{ props.label ?? 'Office' }}
    </label>
    <div class="relative">
      <select
        id="office-select"
        class="w-full appearance-none rounded-lg border border-slate-200 bg-white py-2 pl-3 pr-9 text-sm font-medium text-slate-900 shadow-sm transition hover:border-slate-300 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 disabled:cursor-not-allowed disabled:opacity-60"
        :value="props.modelValue ?? ''"
        :disabled="props.loading || props.offices.length === 0"
        @change="onChange"
      >
        <option v-if="props.offices.length === 0" value="" disabled>
          {{ props.loading ? 'Loading offices…' : 'No offices available' }}
        </option>
        <option v-for="office in props.offices" :key="office.id" :value="office.id">
          {{ office.name }}
        </option>
      </select>
      <svg
        class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
        viewBox="0 0 20 20"
        fill="currentColor"
        aria-hidden="true"
      >
        <path
          fill-rule="evenodd"
          d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z"
          clip-rule="evenodd"
        />
      </svg>
    </div>
  </div>
</template>

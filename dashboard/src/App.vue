<script setup lang="ts">
// Root shell — renders the active route and hosts the global toast queue.
import ToastHost from '@/components/ui/ToastHost.vue'
</script>

<template>
  <RouterView />
  <ToastHost />
</template>

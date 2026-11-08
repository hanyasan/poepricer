<script setup lang="ts">
defineProps<{ label: string }>()
</script>

<template>
  <UCard>
    <p class="text-sm text-muted">{{ label }}</p>
    <p class="mt-1 text-2xl font-semibold"><slot /></p>
  </UCard>
</template>

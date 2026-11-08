<script setup lang="ts">
defineProps<{ label: string }>()
</script>

<template>
  <UCard>
    <p class="text-sm text-muted">{{ label }}</p>
    <p class="mt-1 flex flex-wrap items-baseline gap-x-2 text-2xl font-semibold">
      <span><slot /></span>
      <!-- A remark to the value, set apart from it by the gap. -->
      <span v-if="$slots.note" class="text-base font-normal text-muted"><slot name="note" /></span>
    </p>
  </UCard>
</template>

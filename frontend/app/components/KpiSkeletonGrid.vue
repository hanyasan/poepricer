<script setup lang="ts">
/** The place of a row of KpiTile before the data is there; a tile is 110px high, 70px in the compact size. */
withDefaults(defineProps<{ count?: number; compact?: boolean }>(), { count: 4, compact: false })
</script>

<template>
  <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <USkeleton v-for="index in count" :key="index" :class="compact ? 'h-[70px]' : 'h-[110px]'" />
  </div>
</template>

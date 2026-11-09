<script setup lang="ts">
/** The place of a row of KpiTile before the data is there; a tile is 110px high. */
withDefaults(defineProps<{ count?: number }>(), { count: 4 })
</script>

<template>
  <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <USkeleton v-for="index in count" :key="index" class="h-[110px]" />
  </div>
</template>

<script setup lang="ts">
const route = useRoute()
const { marketSeries } = useItemMetrics()
</script>

<template>
  <div class="space-y-4">
    <h2 class="text-lg font-medium">{{ route.params.item }}</h2>
    <LineChart :points="marketSeries" />
  </div>
</template>

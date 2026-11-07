<script setup lang="ts">
const { marketSeries } = useItemMetrics()
</script>

<template>
  <div class="space-y-4">
    <h2 class="text-lg font-medium">Рыночная доходность</h2>
    <LineChart :points="marketSeries" />
  </div>
</template>

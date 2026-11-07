<script setup lang="ts">
import { use } from 'echarts/core'
import { LineChart } from 'echarts/charts'
import { GridComponent, TooltipComponent } from 'echarts/components'
import { CanvasRenderer } from 'echarts/renderers'
import VChart from 'vue-echarts'
import type { SeriesPoint } from '~/types/metrics'

use([LineChart, GridComponent, TooltipComponent, CanvasRenderer])

const props = defineProps<{ points: SeriesPoint[] }>()

const option = computed(() => ({
  tooltip: { trigger: 'axis' },
  grid: { left: 40, right: 16, top: 16, bottom: 24 },
  xAxis: { type: 'category', data: props.points.map((point) => point.bucket) },
  yAxis: { type: 'value' },
  series: [{ type: 'line', data: props.points.map((point) => point.value) }],
}))
</script>

<template>
  <div class="h-72 w-full">
    <ClientOnly>
      <VChart :option="option" autoresize />
    </ClientOnly>
  </div>
</template>

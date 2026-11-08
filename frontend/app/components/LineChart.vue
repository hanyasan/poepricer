<script setup lang="ts">
import { use } from 'echarts/core'
import { LineChart } from 'echarts/charts'
import { GridComponent, TooltipComponent } from 'echarts/components'
import { CanvasRenderer } from 'echarts/renderers'
import VChart from 'vue-echarts'
import type { SeriesPoint } from '~/types/metrics'

use([LineChart, GridComponent, TooltipComponent, CanvasRenderer])

const props = defineProps<{ points: SeriesPoint[] }>()

const dateFormatter = new Intl.DateTimeFormat('ru-RU', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })

function formatBucket(bucket: string): string {
  return dateFormatter.format(new Date(bucket))
}

const option = computed(() => ({
  animation: false,
  tooltip: {
    trigger: 'axis',
    valueFormatter: (value: number) => formatReturn(value),
  },
  grid: { left: 56, right: 16, top: 16, bottom: 28 },
  xAxis: {
    type: 'category',
    data: props.points.map((point) => formatBucket(point.bucket)),
    axisLabel: { hideOverlap: true },
  },
  yAxis: {
    type: 'value',
    scale: true,
    axisLabel: { formatter: (value: number) => `${(value * 100).toFixed(1)}%` },
  },
  series: [{ type: 'line', showSymbol: props.points.length < 40, data: props.points.map((point) => point.value) }],
}))
</script>

<template>
  <div class="h-72 w-full">
    <ClientOnly>
      <VChart :option="option" autoresize />
    </ClientOnly>
  </div>
</template>

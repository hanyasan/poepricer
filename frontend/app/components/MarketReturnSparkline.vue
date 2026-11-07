<script setup lang="ts">
import { use } from 'echarts/core'
import { LineChart } from 'echarts/charts'
import { GridComponent, TooltipComponent } from 'echarts/components'
import { CanvasRenderer } from 'echarts/renderers'
import VChart from 'vue-echarts'
import type { SeriesPoint } from '~/types/metrics'

use([LineChart, GridComponent, TooltipComponent, CanvasRenderer])

const props = defineProps<{ points: SeriesPoint[] }>()

const formatter = new Intl.DateTimeFormat('ru-RU', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })

const option = computed(() => ({
  animation: false,
  grid: { left: 0, right: 0, top: 4, bottom: 4 },
  tooltip: {
    trigger: 'axis',
    confine: true,
    formatter: (params: Array<{ dataIndex: number }>) => {
      const point = props.points[params[0]!.dataIndex]!
      return `${formatter.format(new Date(point.bucket))}<br/>${(point.value * 100).toFixed(2)}%`
    },
  },
  xAxis: { type: 'category', show: false, boundaryGap: false, data: props.points.map((point) => point.bucket) },
  yAxis: { type: 'value', show: false, scale: true },
  series: [
    {
      type: 'line',
      showSymbol: props.points.length === 1,
      symbolSize: 6,
      smooth: false,
      lineStyle: { width: 1.5 },
      areaStyle: { opacity: 0.08 },
      data: props.points.map((point) => point.value),
    },
  ],
}))
</script>

<template>
  <div class="h-16 w-full">
    <ClientOnly>
      <VChart :option="option" autoresize />
    </ClientOnly>
  </div>
</template>

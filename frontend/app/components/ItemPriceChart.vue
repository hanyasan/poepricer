<script setup lang="ts">
import { use } from 'echarts/core'
import { LineChart } from 'echarts/charts'
import { GridComponent, TooltipComponent } from 'echarts/components'
import { CanvasRenderer } from 'echarts/renderers'
import VChart from 'vue-echarts'
import type { ItemSeriesPoint } from '~/types/metrics'

use([LineChart, GridComponent, TooltipComponent, CanvasRenderer])

const props = defineProps<{ points: ItemSeriesPoint[] }>()

const { colors, symbolSize, xAxisLine, gridLine, marker } = useChartTheme()

const option = computed(() => ({
  animation: false,
  tooltip: {
    trigger: 'axis',
    valueFormatter: (value: number) => formatPrice(value),
  },
  grid: { left: 56, right: 16, top: 16, bottom: 28 },
  xAxis: {
    type: 'category',
    data: props.points.map((point) => formatBucketLabel(point.bucket)),
    axisLine: xAxisLine.value,
    axisLabel: { hideOverlap: true },
  },
  yAxis: {
    type: 'value',
    scale: true,
    splitLine: gridLine.value,
    axisLabel: { formatter: (value: number) => formatPrice(value) },
  },
  series: [
    {
      name: 'Цена',
      type: 'line',
      showSymbol: props.points.length < 40,
      symbol: 'circle',
      symbolSize,
      lineStyle: { color: colors.primary },
      itemStyle: marker(colors.primary),
      data: props.points.map((point) => point.price),
    },
  ],
}))
</script>

<template>
  <div class="h-72 w-full">
    <ClientOnly>
      <VChart :option="option" autoresize />
    </ClientOnly>
  </div>
</template>

<script setup lang="ts">
import { use } from 'echarts/core'
import { LineChart } from 'echarts/charts'
import { GridComponent, TooltipComponent } from 'echarts/components'
import { CanvasRenderer } from 'echarts/renderers'
import VChart from 'vue-echarts'
import type { ItemSeriesPoint } from '~/types/metrics'

use([LineChart, GridComponent, TooltipComponent, CanvasRenderer])

/** A residual beyond this many MADs of the item is an unusual move. */
const BAND_MULTIPLIER = 2

const props = defineProps<{ points: ItemSeriesPoint[] }>()

const { colors, symbolSize, xAxisLine, gridLine, marker } = useChartTheme()

const labels = computed(() => props.points.map((point) => formatBucketLabel(point.bucket)))

function tooltip(params: unknown): string {
  const [first] = params as { dataIndex: number }[]
  const point = first === undefined ? undefined : props.points[first.dataIndex]

  if (point === undefined) return ''

  const lines = [
    labels.value[first!.dataIndex],
    `Остаток: ${orDash(point.residual, formatReturn)}`,
    point.residualMad === null ? 'Полоса: —' : `Полоса: ±${formatPercent(BAND_MULTIPLIER * point.residualMad)}`,
  ]

  return lines.join('<br>')
}

/**
 * The band is a stack of an invisible line at its lower edge and a filled one of
 * its width, so it follows the MAD of every bucket. Buckets without a MAD have no band.
 */
const option = computed(() => ({
  animation: false,
  tooltip: { trigger: 'axis', formatter: tooltip },
  grid: { left: 56, right: 16, top: 16, bottom: 28 },
  xAxis: { type: 'category', data: labels.value, axisLine: xAxisLine.value, axisLabel: { hideOverlap: true } },
  yAxis: {
    type: 'value',
    scale: true,
    splitLine: gridLine.value,
    axisLabel: { formatter: (value: number) => `${(value * 100).toFixed(1)}%` },
  },
  series: [
    {
      name: 'Нижняя граница',
      type: 'line',
      stack: 'band',
      silent: true,
      symbol: 'none',
      lineStyle: { opacity: 0 },
      data: props.points.map((point) => (point.residualMad === null ? null : -BAND_MULTIPLIER * point.residualMad)),
    },
    {
      name: 'Полоса',
      type: 'line',
      stack: 'band',
      silent: true,
      symbol: 'none',
      lineStyle: { opacity: 0 },
      areaStyle: { color: colors.primary, opacity: 0.15 },
      data: props.points.map((point) => (point.residualMad === null ? null : 2 * BAND_MULTIPLIER * point.residualMad)),
    },
    {
      name: 'Остаток',
      type: 'line',
      showSymbol: props.points.length < 40,
      symbol: 'circle',
      symbolSize,
      lineStyle: { color: colors.primary },
      itemStyle: marker(colors.primary),
      data: props.points.map((point) => point.residual),
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

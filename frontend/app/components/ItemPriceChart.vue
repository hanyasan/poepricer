<script setup lang="ts">
import type { ItemSeriesPoint } from '~/types/metrics'

const props = defineProps<{ points: ItemSeriesPoint[] }>()

const { t } = useI18n()
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
      name: t('chart.price'),
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
  <ChartFrame :option="option" class="h-72 w-full" />
</template>

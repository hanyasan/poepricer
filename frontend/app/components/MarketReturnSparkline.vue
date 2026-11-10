<script setup lang="ts">
import type { SeriesPoint } from '~/types/metrics'

const props = defineProps<{ points: SeriesPoint[] }>()

const { colors, symbolSize, marker } = useChartTheme()

const option = computed(() => ({
  animation: false,
  grid: { left: 0, right: 0, top: 4, bottom: 4 },
  tooltip: {
    trigger: 'axis',
    confine: true,
    formatter: (params: Array<{ dataIndex: number }>) => {
      const point = props.points[params[0]!.dataIndex]!

      return `${dateTimeFormatter().format(new Date(point.bucket))}<br/>${formatReturn(point.value)}`
    },
  },
  xAxis: { type: 'category', show: false, boundaryGap: false, data: props.points.map((point) => point.bucket) },
  yAxis: { type: 'value', show: false, scale: true },
  series: [
    {
      type: 'line',
      showSymbol: props.points.length === 1,
      symbol: 'circle',
      symbolSize,
      smooth: false,
      lineStyle: { width: 1.5, color: colors.primary },
      itemStyle: marker(colors.primary),
      areaStyle: { color: colors.primary, opacity: 0.08 },
      data: props.points.map((point) => point.value),
    },
  ],
}))
</script>

<template>
  <ChartFrame :option="option" class="h-16 w-full" />
</template>

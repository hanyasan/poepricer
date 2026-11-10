<script setup lang="ts">
import type { ItemBeta } from '~/types/metrics'

const props = defineProps<{ betas: ItemBeta[] }>()

const { t } = useI18n()
const { colors, symbolSize, xAxisLine, gridLine, marker } = useChartTheme()

const option = computed(() => ({
  animation: false,
  tooltip: { trigger: 'axis', valueFormatter: (value: number) => value.toFixed(2) },
  grid: { left: 40, right: 16, top: 16, bottom: 28 },
  xAxis: {
    type: 'category',
    data: props.betas.map((beta) => formatBucketLabel(beta.calculatedAt)),
    axisLine: xAxisLine.value,
    axisLabel: { hideOverlap: true },
  },
  yAxis: {
    type: 'value',
    scale: true,
    splitLine: gridLine.value,
    axisLabel: { formatter: (value: number) => value.toFixed(1) },
  },
  series: [
    {
      name: t('item.beta.beta'),
      type: 'line',
      symbol: 'circle',
      symbolSize,
      lineStyle: { color: colors.primary },
      itemStyle: marker(colors.primary),
      data: props.betas.map((beta) => beta.beta),
    },
    {
      name: t('chart.rawBeta'),
      type: 'line',
      symbol: 'circle',
      symbolSize,
      lineStyle: { type: 'dashed', color: colors.secondary },
      itemStyle: marker(colors.secondary),
      data: props.betas.map((beta) => beta.rawBeta),
    },
  ],
}))
</script>

<template>
  <ChartFrame :option="option" class="h-48 w-full" />
</template>

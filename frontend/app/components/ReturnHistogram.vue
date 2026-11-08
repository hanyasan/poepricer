<script setup lang="ts">
import { use } from 'echarts/core'
import { BarChart } from 'echarts/charts'
import { GridComponent, MarkLineComponent, TooltipComponent } from 'echarts/components'
import { CanvasRenderer } from 'echarts/renderers'
import VChart from 'vue-echarts'
import type { MarketBin } from '~/types/metrics'

use([BarChart, GridComponent, MarkLineComponent, TooltipComponent, CanvasRenderer])

const props = defineProps<{
  bins: MarketBin[]
  /** Return of the market, drawn as a vertical line. */
  marketReturn: number | null
  /** Index of the selected bar. */
  selected: number | null
}>()

const emit = defineEmits<{ select: [index: number] }>()

const { t } = useI18n()
const { colors, xAxisLine, gridLine, textColor } = useChartTheme()
const { rangeLabel, axisLabel } = useBinLabels()

/**
 * Position of the market return in bar widths from the left edge of the plot, for the
 * hidden value axis the line is drawn on (a category axis snaps a position to a bar). The
 * lower tail is the first width, the middle bars follow with the width of their range,
 * a return beyond them is drawn in the middle of its tail.
 */
const marketPosition = computed<number | null>(() => {
  const { bins, marketReturn } = props
  const middle = bins.slice(1, -1)
  const first = middle[0]
  const last = middle.at(-1)

  if (marketReturn === null || first?.from == null || last?.to == null) return null

  const width = (last.to - first.from) / middle.length
  const position = (marketReturn - first.from) / width + 1

  return Math.min(Math.max(position, 0.5), bins.length - 0.5)
})

function barColor(bin: MarketBin, index: number): string {
  if (index === props.selected) return colors.selected

  const lowerEdge = bin.from ?? (bin.to ?? 0) - 1

  return lowerEdge >= 0 ? colors.up : colors.down
}

function tooltip(params: unknown): string {
  const [first] = params as { dataIndex: number }[]
  const bin = first === undefined ? undefined : props.bins[first.dataIndex]

  return bin === undefined ? '' : [rangeLabel(bin), t('market.histogram.itemsCount', { count: bin.count })].join('<br>')
}

const option = computed(() => ({
  animation: false,
  tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' }, formatter: tooltip },
  grid: { left: 40, right: 16, top: 24, bottom: 40 },
  xAxis: [
    {
      type: 'category',
      data: props.bins.map(axisLabel),
      axisLine: xAxisLine.value,
      axisLabel: { hideOverlap: true, interval: 'auto' },
    },
    // Hidden, only the line of the market return is drawn against it.
    { type: 'value', show: false, min: 0, max: props.bins.length, axisPointer: { show: false } },
  ],
  yAxis: { type: 'value', minInterval: 1, splitLine: gridLine.value },
  series: [
    {
      type: 'bar',
      barCategoryGap: '10%',
      data: props.bins.map((bin, index) => ({ value: bin.count, itemStyle: { color: barColor(bin, index) } })),
    },
    {
      type: 'line',
      xAxisIndex: 1,
      silent: true,
      data: [],
      markLine:
        marketPosition.value === null || props.marketReturn === null
          ? undefined
          : {
              silent: true,
              symbol: 'none',
              lineStyle: { color: colors.secondary, type: 'dashed', width: 2 },
              label: {
                formatter: t('market.histogram.marketReturn', { value: formatReturn(props.marketReturn) }),
                position: 'end',
                // No outline: ECharts draws a white one by default, which glares on the dark theme.
                color: textColor.value,
                textBorderWidth: 0,
              },
              data: [{ xAxis: marketPosition.value }],
            },
    },
  ],
}))

function onClick(params: { dataIndex: number }): void {
  emit('select', params.dataIndex)
}
</script>

<template>
  <div class="h-72 w-full" role="img" :aria-label="$t('market.histogram.ariaLabel')">
    <ClientOnly>
      <VChart :option="option" autoresize @click="onClick" />
      <template #fallback>
        <USkeleton class="h-full w-full" />
      </template>
    </ClientOnly>
  </div>
</template>

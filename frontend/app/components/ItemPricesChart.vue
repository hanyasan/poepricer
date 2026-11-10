<script setup lang="ts">
import type { ItemPricePoint, PriceWindow } from '~/types/metrics'

const props = defineProps<{
  points: ItemPricePoint[]
  /** Bounds of the window, so the axis shows all of it even when the points do not fill it. */
  from: string | null
  to: string | null
  window: PriceWindow
  currencyName: string
}>()

/** A pause longer than this many typical intervals between price checks breaks the line. */
const GAP_FACTOR = 3

const { t } = useI18n()
const { colors, symbolSize, xAxisLine, gridLine, marker } = useChartTheme()

const windowHours = computed(() => PRICE_WINDOW_HOURS[props.window])

const times = computed(() => props.points.map((point) => new Date(point.at).getTime()))

/** Typical interval between price checks: the median of the gaps between neighbours. */
const typicalInterval = computed(() => {
  const gaps = times.value.slice(1).map((time, index) => time - times.value[index]!).sort((a, b) => a - b)

  return gaps.length === 0 ? 0 : gaps[Math.floor(gaps.length / 2)]!
})

/** The price line with a null between the points that lie too far apart, so the line breaks there. */
const priceData = computed<([number, number] | [number, null])[]>(() => {
  const data: ([number, number] | [number, null])[] = []

  props.points.forEach((point, index) => {
    const time = times.value[index]!
    const previous = times.value[index - 1]

    if (previous !== undefined && typicalInterval.value > 0 && time - previous > typicalInterval.value * GAP_FACTOR) {
      data.push([previous + (time - previous) / 2, null])
    }

    data.push([time, point.price])
  })

  return data
})

const axisBounds = computed(() => ({
  min: props.from === null ? undefined : new Date(props.from).getTime(),
  max: props.to === null ? undefined : new Date(props.to).getTime(),
}))

/** The bars take the bottom third of the plot: the units axis reaches three times the highest bar. */
const UNITS_HEADROOM = 3

/**
 * Lower bound of the price axis: half of the price range under the lowest price, so the line lies over
 * the bars, rounded down to a round number and never below zero for prices that are not negative.
 */
function priceAxisMin(range: { min: number; max: number }): number {
  const span = range.max - range.min
  const lowered = range.min - (span > 0 ? span : Math.abs(range.max) || 1) * 0.5
  const step = 10 ** Math.floor(Math.log10(span > 0 ? span : Math.abs(range.max) || 1)) / 10
  const rounded = Math.floor(lowered / step) * step

  return range.min >= 0 ? Math.max(0, rounded) : rounded
}

const maxUnits = computed(() => Math.max(0, ...props.points.map((point) => point.units)))

const option = computed(() => ({
  animation: false,
  tooltip: {
    trigger: 'axis',
    axisPointer: { type: 'line' },
    formatter: (params: { axisValue: number; seriesIndex: number; value: [number, number | null] }[]) => {
      const price = params.find((item) => item.seriesIndex === 0)?.value[1]
      const units = params.find((item) => item.seriesIndex === 1)?.value[1]
      const lines = [formatChartTime(params[0]?.axisValue ?? 0, windowHours.value)]

      if (price !== null && price !== undefined) {
        lines.push(t('chart.priceValue', { value: `${formatPrice(price)} ${props.currencyName}` }))
      }

      if (units !== null && units !== undefined) {
        lines.push(t('chart.unitsValue', { value: formatUnits(units) }))
      }

      return lines.join('<br>')
    },
  },
  // One plot and one time axis for both series, so the pointer and the tooltip are the same for the
  // price and for the trades. The price is lifted over the bars by the lower bound of its axis.
  grid: { left: 56, right: 56, top: 16, bottom: 28 },
  xAxis: {
    type: 'time' as const,
    ...axisBounds.value,
    // The axis line stays at the bottom of the plot instead of crossing the price axis at zero.
    axisLine: { ...xAxisLine.value, onZero: false },
    axisLabel: { hideOverlap: true, formatter: (value: number) => formatChartTime(value, windowHours.value) },
    splitLine: { show: false },
  },
  yAxis: [
    {
      type: 'value' as const,
      scale: true,
      min: priceAxisMin,
      splitLine: gridLine.value,
      axisLabel: { formatter: (value: number) => formatPrice(value) },
    },
    {
      type: 'value' as const,
      position: 'right' as const,
      min: 0,
      max: Math.max(maxUnits.value, 1) * UNITS_HEADROOM,
      splitNumber: UNITS_HEADROOM,
      splitLine: { show: false },
      axisLabel: { formatter: (value: number) => (value <= maxUnits.value ? formatUnits(value) : '') },
    },
  ],
  series: [
    {
      name: t('chart.price'),
      type: 'line',
      yAxisIndex: 0,
      z: 3,
      connectNulls: false,
      showSymbol: props.points.length < 40,
      symbol: 'circle',
      symbolSize,
      lineStyle: { color: colors.primary },
      itemStyle: marker(colors.primary),
      data: priceData.value,
    },
    {
      name: t('chart.units'),
      type: 'bar',
      yAxisIndex: 1,
      z: 2,
      barMinWidth: 1,
      barMaxWidth: 8,
      itemStyle: { color: colors.secondary, opacity: 0.6 },
      data: props.points.map((point, index) => [times.value[index], point.units]),
    },
  ],
}))
</script>

<template>
  <ChartFrame :option="option" class="h-96 w-full" role="img" :aria-label="$t('itemPrices.chartLabel')" />
</template>

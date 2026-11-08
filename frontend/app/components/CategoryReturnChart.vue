<script setup lang="ts">
import { use } from 'echarts/core'
import { BarChart } from 'echarts/charts'
import { GridComponent, TooltipComponent } from 'echarts/components'
import { CanvasRenderer } from 'echarts/renderers'
import VChart from 'vue-echarts'
import type { MarketCategory } from '~/types/metrics'

use([BarChart, GridComponent, TooltipComponent, CanvasRenderer])

/** Height of the row of a category and the space for the axis. */
const ROW_HEIGHT = 32
const AXIS_HEIGHT = 40

const props = defineProps<{ categories: MarketCategory[] }>()

const { t } = useI18n()
const { colors, xAxisLine, gridLine } = useChartTheme()

function categoryName(category: MarketCategory): string {
  return category.category ?? t('items.noCategory')
}

function tooltip(params: unknown): string {
  const [first] = params as { dataIndex: number }[]
  const category = first === undefined ? undefined : props.categories[first.dataIndex]

  if (category === undefined) return ''

  return [
    categoryName(category),
    t('market.categories.median', { value: formatReturn(category.median) }),
    t('market.categories.count', { count: category.count }),
  ].join('<br>')
}

const height = computed(() => `${props.categories.length * ROW_HEIGHT + AXIS_HEIGHT}px`)

/** The best category goes first, so the category axis is reversed to read from the top. */
const option = computed(() => ({
  animation: false,
  tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' }, formatter: tooltip },
  grid: { left: 8, right: 24, top: 8, bottom: 28, containLabel: true },
  xAxis: {
    type: 'value',
    splitLine: gridLine.value,
    axisLabel: { formatter: (value: number) => formatReturn(value) },
  },
  yAxis: {
    type: 'category',
    inverse: true,
    data: props.categories.map(categoryName),
    axisLine: xAxisLine.value,
  },
  series: [
    {
      type: 'bar',
      data: props.categories.map((category) => ({
        value: category.median,
        itemStyle: { color: category.median >= 0 ? colors.up : colors.down },
      })),
    },
  ],
}))
</script>

<template>
  <div class="w-full" :style="{ height }" role="img" :aria-label="$t('market.categories.ariaLabel')">
    <ClientOnly>
      <VChart :option="option" autoresize />
      <template #fallback>
        <USkeleton class="h-full w-full" />
      </template>
    </ClientOnly>
  </div>
</template>

<script setup lang="ts">
import type { SeriesPoint } from '~/types/metrics'

const props = defineProps<{ points: SeriesPoint[] }>()

const WIDTH = 96
const HEIGHT = 28
const PADDING = 2

const hasLine = computed(() => props.points.length >= 2)

/** The line is scaled to its own range, a flat series is drawn in the middle. */
const line = computed(() => {
  const values = props.points.map((point) => point.value)
  const min = Math.min(...values)
  const range = Math.max(...values) - min

  return values
    .map((value, index) => {
      const x = PADDING + (index / (values.length - 1)) * (WIDTH - PADDING * 2)
      const y = range === 0 ? HEIGHT / 2 : HEIGHT - PADDING - ((value - min) / range) * (HEIGHT - PADDING * 2)

      return `${x.toFixed(1)},${y.toFixed(1)}`
    })
    .join(' ')
})

const trendClass = computed(() => {
  const first = props.points[0]?.value ?? 0
  const last = props.points[props.points.length - 1]?.value ?? 0

  return last === first ? 'text-muted' : last > first ? 'text-success' : 'text-error'
})

const label = computed(() =>
  hasLine.value
    ? `Цена за 7 дней: с ${formatPrice(props.points[0]!.value)} до ${formatPrice(props.points[props.points.length - 1]!.value)}`
    : 'Недостаточно данных для графика',
)
</script>

<template>
  <svg
    v-if="hasLine"
    :width="WIDTH"
    :height="HEIGHT"
    :viewBox="`0 0 ${WIDTH} ${HEIGHT}`"
    :class="trendClass"
    role="img"
    :aria-label="label"
  >
    <title>{{ label }}</title>
    <polyline :points="line" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round" />
  </svg>
  <span v-else class="text-muted" :title="label">—</span>
</template>

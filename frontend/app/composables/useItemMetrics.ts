import type { ItemMetricRow, SeriesPoint } from '~/types/metrics'

/**
 * Placeholder data until the Laravel API routes exist.
 */
export function useItemMetrics() {
  const items = ref<ItemMetricRow[]>([
    { slug: 'sample-item', name: 'Sample item', price: 1.5, logReturn: 0.02, residual: 0.01, residualMad: 0.03, beta: 1, observations: 14, units: 25, returnWeight: 1 },
  ])

  const marketSeries = ref<SeriesPoint[]>([
    { bucket: '2026-01-01T00:00:00Z', value: 0.01 },
    { bucket: '2026-01-01T12:00:00Z', value: -0.02 },
    { bucket: '2026-01-02T00:00:00Z', value: 0.03 },
    { bucket: '2026-01-02T12:00:00Z', value: 0.0 },
  ])

  return { items, marketSeries }
}

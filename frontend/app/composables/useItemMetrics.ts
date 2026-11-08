import type { SeriesPoint } from '~/types/metrics'

/**
 * Placeholder data until the Laravel API routes exist.
 */
export function useItemMetrics() {
  const marketSeries = ref<SeriesPoint[]>([
    { bucket: '2026-01-01T00:00:00Z', value: 0.01 },
    { bucket: '2026-01-01T12:00:00Z', value: -0.02 },
    { bucket: '2026-01-02T00:00:00Z', value: 0.03 },
    { bucket: '2026-01-02T12:00:00Z', value: 0.0 },
  ])

  return { marketSeries }
}

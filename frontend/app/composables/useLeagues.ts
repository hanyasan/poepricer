import type { League } from '~/types/metrics'

/**
 * Placeholder data until the Laravel API routes exist; the interface is the
 * one the real `/api` endpoints are expected to fulfil.
 */
export function useLeagues() {
  const leagues = ref<League[]>([
    { game: 'poe1', slug: 'standard', name: 'Standard', isActive: true, lastSnapshotAt: null, basketSize: 0 },
    { game: 'poe2', slug: 'standard', name: 'Standard', isActive: true, lastSnapshotAt: null, basketSize: 0 },
  ])

  return { leagues }
}

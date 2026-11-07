import type { League } from '~/types/metrics'

export type LeaguesStatus = 'idle' | 'pending' | 'success' | 'error'

/**
 * Leagues of the main page from `GET /api/leagues`: active leagues only,
 * ordered by game, each with the market return series of the last 7 days.
 */
export const useLeaguesStore = defineStore('leagues', () => {
  const items = ref<League[]>([])
  const status = ref<LeaguesStatus>('idle')

  function bySlug(slug: string): League | undefined {
    return items.value.find((league) => league.slug === slug)
  }

  async function load(): Promise<void> {
    status.value = 'pending'

    try {
      items.value = await $fetch<League[]>('/api/leagues')
      status.value = 'success'
    } catch {
      status.value = 'error'
    }
  }

  return { items, status, bySlug, load }
})

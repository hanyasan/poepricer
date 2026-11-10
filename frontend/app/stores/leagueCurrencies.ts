import type { LeagueCurrency } from '~/types/metrics'

/**
 * Metric currencies of every league the visitor has opened, from
 * `GET /api/leagues/{slug}/currencies`.
 *
 * The list is kept for the whole session, so the pages of a league and the pages of its
 * items read the same currencies from here instead of asking the API on every transition.
 */
export const useLeagueCurrenciesStore = defineStore('leagueCurrencies', () => {
  /** Currencies by league slug, the first one is the default. */
  const byLeague = ref<Record<string, LeagueCurrency[]>>({})

  /** Requests in flight by league slug, so parallel asks for one league share a single request. */
  const requests = new Map<string, Promise<LeagueCurrency[]>>()

  /** Currencies of the league, `undefined` until they are loaded. */
  function bySlug(slug: string): LeagueCurrency[] | undefined {
    return byLeague.value[slug]
  }

  /** Currencies of the league from the store, or from the API when the store has none yet. */
  function load(slug: string): Promise<LeagueCurrency[]> {
    const cached = byLeague.value[slug]

    if (cached) {
      return Promise.resolve(cached)
    }

    let request = requests.get(slug)

    if (!request) {
      request = requestCurrencies(slug).finally(() => requests.delete(slug))
      requests.set(slug, request)
    }

    return request
  }

  async function requestCurrencies(slug: string): Promise<LeagueCurrency[]> {
    const currencies = await $fetch<LeagueCurrency[]>(`/api/leagues/${slug}/currencies`)

    byLeague.value[slug] = currencies

    return currencies
  }

  return { byLeague, bySlug, load }
})

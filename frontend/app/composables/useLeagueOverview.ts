import type { LeagueOverview } from '~/types/metrics'

/**
 * Overview of the league in the selected currency and period; it is requested
 * again whenever one of them changes.
 */
export function useLeagueOverview(slug: MaybeRefOrGetter<string>) {
  const { currency, period } = storeToRefs(useFiltersStore())

  return useFetch<LeagueOverview>(() => `/api/leagues/${toValue(slug)}/overview`, {
    query: computed(() => ({ currency: currency.value ?? undefined, period: period.value })),
  })
}

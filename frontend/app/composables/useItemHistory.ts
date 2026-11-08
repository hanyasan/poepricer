import type { ItemHistory } from '~/types/metrics'

/**
 * Series for the charts of the item and the recalculations of its beta within
 * the selected period; requested again when the currency or the period changes.
 */
export function useItemHistory(leagueSlug: MaybeRefOrGetter<string>, itemSlug: MaybeRefOrGetter<string>) {
  const { currency, period } = storeToRefs(useFiltersStore())

  return useFetch<ItemHistory>(() => `/api/leagues/${toValue(leagueSlug)}/items/${toValue(itemSlug)}/history`, {
    query: computed(() => ({ currency: currency.value ?? undefined, period: period.value })),
  })
}

import type { ItemSummary } from '~/types/metrics'

/**
 * Card of the item in the selected currency: description, metrics of the last
 * bucket and the latest beta. It does not depend on the period.
 */
export function useItemSummary(leagueSlug: MaybeRefOrGetter<string>, itemSlug: MaybeRefOrGetter<string>) {
  const { currency } = storeToRefs(useFiltersStore())

  return useFetch<ItemSummary>(() => `/api/leagues/${toValue(leagueSlug)}/items/${toValue(itemSlug)}/summary`, {
    query: computed(() => ({ currency: currency.value ?? undefined })),
  })
}

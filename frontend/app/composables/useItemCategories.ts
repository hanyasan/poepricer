import type { ItemCategories } from '~/types/metrics'

/**
 * Categories of the items of the league for the filter. They are requested once
 * per league, apart from the table, and cached on the server.
 */
export function useItemCategories(slug: MaybeRefOrGetter<string>) {
  return useFetch<ItemCategories>(() => `/api/leagues/${toValue(slug)}/items/categories`)
}

import type { InjectionKey, Ref } from 'vue'
import type { ItemSummary } from '~/types/metrics'

const itemSummaryKey: InjectionKey<Ref<ItemSummary | null | undefined>> = Symbol('itemSummary')

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

/**
 * Hands the summary the item layout has loaded down to its tabs, so a tab does not
 * request it again.
 */
export function provideItemSummary(summary: Ref<ItemSummary | null | undefined>): void {
  provide(itemSummaryKey, summary)
}

/** The summary loaded by the item layout; the tabs only render under it. */
export function useProvidedItemSummary(): Ref<ItemSummary | null | undefined> {
  const summary = inject(itemSummaryKey)

  if (summary === undefined) {
    throw new Error('useProvidedItemSummary needs the item layout above it')
  }

  return summary
}

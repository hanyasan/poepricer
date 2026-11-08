import type { ItemBucketsPage } from '~/types/metrics'

/**
 * The table of the item values by bucket, newest first. The page lives in the URL
 * query, the currency and the period come from the filters store; a change of
 * either of them returns the table to the first page.
 */
export function useItemBuckets(leagueSlug: MaybeRefOrGetter<string>, itemSlug: MaybeRefOrGetter<string>) {
  const route = useRoute()
  const router = useRouter()
  const { currency, period } = storeToRefs(useFiltersStore())

  const page = computed(() => {
    const raw = Array.isArray(route.query.page) ? route.query.page[0] : route.query.page
    const value = Number.parseInt(raw ?? '', 10)

    return Number.isInteger(value) && value >= 1 ? value : 1
  })

  const request = useFetch<ItemBucketsPage>(() => `/api/leagues/${toValue(leagueSlug)}/items/${toValue(itemSlug)}/buckets`, {
    query: computed(() => ({
      currency: currency.value ?? undefined,
      period: period.value,
      page: page.value,
    })),
  })

  function setPage(value: number): void {
    const { page: _page, ...rest } = route.query

    void router.push({ query: value === 1 ? rest : { ...rest, page: String(value) } })
  }

  watch([currency, period], () => {
    if (page.value > 1) {
      const { page: _page, ...rest } = route.query

      void router.replace({ query: rest })
    }
  })

  // A page past the end, from an old link, goes to the last one. The server renders it
  // as is, so the first check runs in the browser right after hydration.
  watch(
    request.data,
    (buckets) => {
      if (buckets && buckets.data.length === 0 && buckets.meta.total > 0 && page.value > buckets.meta.lastPage) {
        void router.replace({ query: { ...route.query, page: String(buckets.meta.lastPage) } })
      }
    },
    { immediate: import.meta.client },
  )

  return { ...request, page, setPage }
}

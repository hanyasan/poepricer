import type { LeagueCurrency } from '~/types/metrics'

/**
 * Loads the metric currencies of the league into the filters store, which keeps
 * the selected one valid for the league. An unknown league is a 404 page.
 *
 * The selection is shared by every page of the league: the `currency` query
 * parameter makes it a part of the link there.
 */
export async function useLeagueCurrencies(slug: MaybeRefOrGetter<string>) {
  const filters = useFiltersStore()
  const { t } = useI18n()
  const route = useRoute()
  const router = useRouter()

  const { data, error, status, refresh } = await useFetch<LeagueCurrency[]>(
    () => `/api/leagues/${toValue(slug)}/currencies`,
  )

  if (error.value?.statusCode === 404) {
    throw createError({ statusCode: 404, statusMessage: t('layout.leagueNotFound'), fatal: true })
  }

  const queryCurrency = computed(() => {
    const raw = route.query.currency

    return (Array.isArray(raw) ? raw[0] : raw) ?? null
  })

  // The `currency` query parameter makes the selection a part of the link.
  watch(
    data,
    (items) => {
      filters.setCurrencies(items ?? [])

      if (queryCurrency.value && filters.currencies.some((item) => item.slug === queryCurrency.value)) {
        filters.currency = queryCurrency.value
      }
    },
    { immediate: true },
  )

  watch(queryCurrency, (value) => {
    if (value && value !== filters.currency && filters.currencies.some((item) => item.slug === value)) {
      filters.currency = value
    }
  })

  watch(
    () => filters.currency,
    (value) => {
      if (value && value !== queryCurrency.value) {
        void router.replace({ query: { ...route.query, currency: value } })
      }
    },
  )

  return { status, error, refresh }
}

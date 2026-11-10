import type { LeagueCurrency } from '~/types/metrics'

/**
 * Loads the metric currencies of the league into the filters store, which keeps
 * the selected one valid for the league. An unknown league is a 404 page.
 *
 * The currencies themselves live in the league currencies store for the whole session,
 * so moving between the pages of a league and the pages of its items does not ask the
 * API again. The selection is shared by every page of the league: the `currency` query
 * parameter makes it a part of the link there.
 */
export async function useLeagueCurrencies(slug: MaybeRefOrGetter<string | undefined>) {
  const currenciesStore = useLeagueCurrenciesStore()
  const filters = useFiltersStore()
  const { t } = useI18n()
  const route = useRoute()
  const router = useRouter()

  /**
   * The slug of the league on screen. The layout outlives the transition away from a league,
   * and the route has no league parameter by then, so a missing slug is `null` and not a request.
   */
  const leagueSlug = computed<string | null>(() => {
    const value = toValue(slug)

    return typeof value === 'string' && value !== '' ? value : null
  })

  const queryCurrency = computed(() => {
    const raw = route.query.currency

    return (Array.isArray(raw) ? raw[0] : raw) ?? null
  })

  /** The `currency` query parameter makes the selection a part of the link. */
  function selectQueryCurrency(items: LeagueCurrency[]): void {
    if (queryCurrency.value && items.some((item) => item.slug === queryCurrency.value)) {
      filters.currency = queryCurrency.value
    }
  }

  /**
   * Puts the currencies of the league into the filters store; a missing league is a 404 page.
   * Without a league — the route of another page, a layout on its way out — there is nothing to load.
   */
  async function load(slugValue: string | null): Promise<void> {
    if (slugValue === null) {
      return
    }

    let items: LeagueCurrency[]

    try {
      items = await currenciesStore.load(slugValue)
    } catch (error) {
      if ((error as { statusCode?: number }).statusCode === 404) {
        throw createError({ statusCode: 404, statusMessage: t('layout.leagueNotFound'), fatal: true })
      }

      // Any other failure leaves the selector empty, as it did before the store existed.
      filters.setCurrencies([])

      return
    }

    filters.setCurrencies(items)
    selectQueryCurrency(items)
  }

  await load(leagueSlug.value)

  // A layout survives the transition to another league, so the currencies follow the slug.
  watch(leagueSlug, (value) => void load(value))

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
}

import type { ItemPrices, PriceWindow } from '~/types/metrics'

export const PRICE_WINDOWS: PriceWindow[] = ['6h', '24h', '48h', '7d']
export const DEFAULT_PRICE_WINDOW: PriceWindow = '48h'

/** Hours in each window; the chart axis and the labels depend on the length. */
export const PRICE_WINDOW_HOURS: Record<PriceWindow, number> = { '6h': 6, '24h': 24, '48h': 48, '7d': 168 }

function firstValue(value: unknown): string | undefined {
  const first = Array.isArray(value) ? value[0] : value

  return typeof first === 'string' && first !== '' ? first : undefined
}

/**
 * The window and the currency of the price series. They live in the URL query, so a link keeps
 * the view; they do not depend on the metric filters. `key` names the request of the page, so
 * the toolbar can read its answer without asking again.
 */
export function useItemPriceControls(leagueSlug: MaybeRefOrGetter<string>, itemSlug: MaybeRefOrGetter<string>) {
  const route = useRoute()
  const router = useRouter()

  const priceWindow = computed<PriceWindow>(() => {
    const raw = firstValue(route.query.window)

    return PRICE_WINDOWS.find((value) => value === raw) ?? DEFAULT_PRICE_WINDOW
  })
  const currency = computed(() => firstValue(route.query.currency) ?? null)
  const key = computed(
    () => `item-prices:${toValue(leagueSlug)}:${toValue(itemSlug)}:${priceWindow.value}:${currency.value ?? ''}`,
  )

  function setWindow(value: PriceWindow): void {
    void router.replace({
      query: { ...route.query, window: value === DEFAULT_PRICE_WINDOW ? undefined : value },
    })
  }

  function setCurrency(value: string): void {
    void router.replace({ query: { ...route.query, currency: value } })
  }

  return { priceWindow, currency, key, setWindow, setCurrency }
}

/**
 * Price series of the item by price check. The server falls back to the most covered currency
 * when the asked one has no prices in the window, so the query may keep a stale currency and
 * the page shows the currency of the answer.
 */
export function useItemPrices(leagueSlug: MaybeRefOrGetter<string>, itemSlug: MaybeRefOrGetter<string>) {
  const controls = useItemPriceControls(leagueSlug, itemSlug)

  const request = useFetch<ItemPrices>(() => `/api/leagues/${toValue(leagueSlug)}/items/${toValue(itemSlug)}/prices`, {
    key: controls.key,
    query: computed(() => ({ window: controls.priceWindow.value, currency: controls.currency.value ?? undefined })),
    // The server render of the toolbar and of the page ask for the same series; the second
    // call joins the pending request instead of cancelling it.
    dedupe: 'defer',
  })

  return { ...request, ...controls }
}

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
 * Price series of the item by price check. The window and the currency live in the URL
 * query, so a link keeps the view; they do not depend on the metric filters. The server falls
 * back to the most covered currency when the asked one has no prices in the window, so the
 * query may keep a stale currency and the page shows the currency of the answer.
 */
export function useItemPrices(leagueSlug: MaybeRefOrGetter<string>, itemSlug: MaybeRefOrGetter<string>) {
  const route = useRoute()
  const router = useRouter()

  const priceWindow = computed<PriceWindow>(() => {
    const raw = firstValue(route.query.window)

    return PRICE_WINDOWS.find((value) => value === raw) ?? DEFAULT_PRICE_WINDOW
  })
  const currency = computed(() => firstValue(route.query.currency) ?? null)

  const request = useFetch<ItemPrices>(() => `/api/leagues/${toValue(leagueSlug)}/items/${toValue(itemSlug)}/prices`, {
    query: computed(() => ({ window: priceWindow.value, currency: currency.value ?? undefined })),
  })

  function setWindow(value: PriceWindow): void {
    void router.replace({
      query: { ...route.query, window: value === DEFAULT_PRICE_WINDOW ? undefined : value },
    })
  }

  function setCurrency(value: string): void {
    void router.replace({ query: { ...route.query, currency: value } })
  }

  return { ...request, priceWindow, currency, setWindow, setCurrency }
}

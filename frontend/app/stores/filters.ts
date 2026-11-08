import type { LeagueCurrency, MetricPeriod } from '~/types/metrics'

export const useFiltersStore = defineStore('filters', () => {
  /** Metric currencies of the league on screen, the first one is the default. */
  const currencies = ref<LeagueCurrency[]>([])
  /** Trade id of the selected currency, `null` until the league currencies are known. */
  const currency = ref<string | null>(null)
  const period = ref<MetricPeriod>('7d')

  const currencyName = computed(
    () => currencies.value.find((item) => item.slug === currency.value)?.name ?? currency.value,
  )

  /**
   * Replaces the currencies and keeps the selection when the league still has
   * it, otherwise falls back to the default one.
   */
  function setCurrencies(items: LeagueCurrency[]): void {
    currencies.value = items

    if (!items.some((item) => item.slug === currency.value)) {
      currency.value = items[0]?.slug ?? null
    }
  }

  return { currencies, currency, period, currencyName, setCurrencies }
})

import type { Game, MetricCurrency, MetricPeriod } from '~/types/metrics'

export const currenciesByGame: Record<Game, MetricCurrency[]> = {
  poe1: ['divine', 'chaos'],
  poe2: ['divine', 'exalted'],
}

export const useFiltersStore = defineStore('filters', () => {
  const currency = ref<MetricCurrency>('divine')
  const period = ref<MetricPeriod>('7d')

  return { currency, period }
})

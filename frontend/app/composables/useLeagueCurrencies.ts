import type { LeagueCurrency } from '~/types/metrics'

/**
 * Loads the metric currencies of the league into the filters store, which keeps
 * the selected one valid for the league. An unknown league is a 404 page.
 */
export async function useLeagueCurrencies(slug: MaybeRefOrGetter<string>) {
  const filters = useFiltersStore()

  const { data, error, status, refresh } = await useFetch<LeagueCurrency[]>(
    () => `/api/leagues/${toValue(slug)}/currencies`,
  )

  if (error.value?.statusCode === 404) {
    throw createError({ statusCode: 404, statusMessage: 'Лига не найдена', fatal: true })
  }

  watch(data, (items) => filters.setCurrencies(items ?? []), { immediate: true })

  return { status, error, refresh }
}

import type { LocationQueryValue } from 'vue-router'
import type { ItemsPage } from '~/types/metrics'

export type ItemsSortField =
  | 'name'
  | 'price'
  | 'logReturn'
  | 'residual'
  | 'residualMad'
  | 'beta'
  | 'observations'
  | 'units'
  | 'unusualRatio'

export type SortDirection = 'asc' | 'desc'

const SORT_FIELDS: readonly ItemsSortField[] = [
  'name',
  'price',
  'logReturn',
  'residual',
  'residualMad',
  'beta',
  'observations',
  'units',
  'unusualRatio',
]

export const DEFAULT_ITEMS_SORT = { field: 'units', direction: 'desc' } as const satisfies {
  field: ItemsSortField
  direction: SortDirection
}

type QueryKey = 'search' | 'category' | 'uncategorized' | 'sort' | 'page'

type QueryPatch = Partial<Record<QueryKey, string | undefined>>

function firstValue(value: LocationQueryValue | LocationQueryValue[] | undefined): string | undefined {
  const first = Array.isArray(value) ? value[0] : value

  return first === null || first === undefined || first === '' ? undefined : first
}

function serializeSort(field: ItemsSortField, direction: SortDirection): string {
  return `${direction === 'desc' ? '-' : ''}${field}`
}

/**
 * The items table of the league. Search, category, sort and page live in the URL
 * query, so links and the back button work; the currency comes from the filters
 * store. The table is requested again whenever one of them changes.
 */
export function useLeagueItems(slug: MaybeRefOrGetter<string>) {
  const route = useRoute()
  const router = useRouter()
  const { currency } = storeToRefs(useFiltersStore())

  const search = computed(() => firstValue(route.query.search))
  const uncategorized = computed(() => firstValue(route.query.uncategorized) === '1')
  const category = computed(() => (uncategorized.value ? undefined : firstValue(route.query.category)))

  /** Sort of the URL; an unknown value falls back to the default instead of failing the request. */
  const sort = computed(() => {
    const raw = firstValue(route.query.sort) ?? ''
    const descending = raw.startsWith('-')
    const field = SORT_FIELDS.find((item) => item === (descending ? raw.slice(1) : raw))

    return field === undefined
      ? { ...DEFAULT_ITEMS_SORT }
      : { field, direction: descending ? ('desc' as const) : ('asc' as const) }
  })

  const page = computed(() => {
    const value = Number.parseInt(firstValue(route.query.page) ?? '', 10)

    return Number.isInteger(value) && value >= 1 ? value : 1
  })

  const request = useFetch<ItemsPage>(() => `/api/leagues/${toValue(slug)}/items`, {
    query: computed(() => ({
      currency: currency.value ?? undefined,
      search: search.value,
      category: category.value,
      uncategorized: uncategorized.value ? 1 : undefined,
      sort: serializeSort(sort.value.field, sort.value.direction),
      page: page.value,
    })),
  })

  /** Changes URL query values; an empty value or the first page is left out of the URL. */
  function update(patch: QueryPatch, options: { replace?: boolean } = {}): void {
    const next: Record<string, LocationQueryValue | LocationQueryValue[] | undefined> = { ...route.query, ...patch }

    for (const key of Object.keys(next)) {
      if (next[key] === undefined || (key === 'page' && next[key] === '1')) {
        delete next[key]
      }
    }

    void (options.replace ? router.replace({ query: next }) : router.push({ query: next }))
  }

  function setSearch(value: string): void {
    update({ search: value.trim() || undefined, page: undefined }, { replace: true })
  }

  /** `null` shows all items, `'uncategorized'` the ones without a category. */
  function setCategory(value: string | 'uncategorized' | null): void {
    update({
      category: value === null || value === 'uncategorized' ? undefined : value,
      uncategorized: value === 'uncategorized' ? '1' : undefined,
      page: undefined,
    })
  }

  /** The default sort is left out of the URL. */
  function setSort(field: ItemsSortField, direction: SortDirection): void {
    const isDefault = field === DEFAULT_ITEMS_SORT.field && direction === DEFAULT_ITEMS_SORT.direction

    update({ sort: isDefault ? undefined : serializeSort(field, direction), page: undefined })
  }

  function setPage(value: number): void {
    update({ page: String(value) })
  }

  // A page past the end, from an old link or after the list shrank, goes to the last one.
  // The server renders it as is, so the first check runs in the browser right after hydration.
  watch(
    request.data,
    (items) => {
      if (items && items.data.length === 0 && items.meta.total > 0 && page.value > items.meta.lastPage) {
        update({ page: String(items.meta.lastPage) }, { replace: true })
      }
    },
    { immediate: import.meta.client },
  )

  return { ...request, search, category, uncategorized, sort, page, setSearch, setCategory, setSort, setPage }
}

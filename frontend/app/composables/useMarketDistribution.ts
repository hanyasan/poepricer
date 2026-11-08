import type { LocationQueryValue } from 'vue-router'
import type { MarketDistribution } from '~/types/metrics'

type QueryPatch = Partial<Record<'bucket' | 'all', string | undefined>>

function firstValue(value: LocationQueryValue | LocationQueryValue[] | undefined): string | undefined {
  const first = Array.isArray(value) ? value[0] : value

  return first === null || first === undefined || first === '' ? undefined : first
}

/**
 * Distribution of the item returns of one bucket of the league. The bucket and the
 * thinly traded items switch live in the URL query, so links and the back button work;
 * the currency comes from the filters store. It is requested again when one of them changes.
 */
export function useMarketDistribution(slug: MaybeRefOrGetter<string>) {
  const route = useRoute()
  const router = useRouter()
  const { currency } = storeToRefs(useFiltersStore())

  /** Bucket of the URL; none shows the latest one. */
  const bucket = computed(() => firstValue(route.query.bucket))
  const includeAll = computed(() => firstValue(route.query.all) === '1')

  const request = useFetch<MarketDistribution>(() => `/api/leagues/${toValue(slug)}/market/distribution`, {
    query: computed(() => ({
      currency: currency.value ?? undefined,
      bucket: bucket.value,
      all: includeAll.value ? 1 : undefined,
    })),
  })

  function update(patch: QueryPatch): void {
    const next: Record<string, LocationQueryValue | LocationQueryValue[] | undefined> = { ...route.query, ...patch }

    for (const key of Object.keys(next)) {
      if (next[key] === undefined) {
        delete next[key]
      }
    }

    void router.push({ query: next })
  }

  /** `null` shows the latest bucket and leaves the parameter out of the URL. */
  function setBucket(value: string | null): void {
    update({ bucket: value ?? undefined })
  }

  function setIncludeAll(value: boolean): void {
    update({ all: value ? '1' : undefined })
  }

  return { ...request, bucket, includeAll, setBucket, setIncludeAll }
}

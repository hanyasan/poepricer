<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui'
import type { MarketItem } from '~/types/metrics'

// The distribution is of one bucket, so the period selector of the league header is hidden.
definePageMeta({ usesPeriod: false })

const route = useRoute()
const { t } = useI18n()
const localePath = useLocalePath()
const { leagueName } = useLeagueName()
const { rangeLabel } = useBinLabels()

useBreadcrumbSchema(() => [
  { name: t('layout.leagues'), path: '/' },
  { name: leagueName.value, path: `/${route.params.league}` },
  { name: t('league.market') },
])

useSeoMeta({
  title: () => t('seo.market.title', { league: leagueName.value }),
  description: () => t('seo.market.description', { league: leagueName.value }),
  ogTitle: () => t('seo.market.title', { league: leagueName.value }),
  ogDescription: () => t('seo.market.description', { league: leagueName.value }),
  // A chosen bucket or the thinly traded items make a view of the page, not a page of its own.
  robots: () => (Object.keys(route.query).length > 0 ? 'noindex, follow' : undefined),
})

const slug = computed(() => route.params.league as string)

// useFetch makes the server wait for the response before it renders the page.
const { data: distribution, status, error, refresh, bucket, includeAll, setBucket, setIncludeAll } = useMarketDistribution(slug)

/** The URL asks for a bucket the league does not have. */
const bucketMissing = computed(() => bucket.value !== undefined && error.value?.statusCode === 422)

const selectedBin = ref<number | null>(null)

// Another bucket or currency has other bars.
watch(distribution, () => {
  selectedBin.value = null
})

function selectBin(index: number): void {
  selectedBin.value = selectedBin.value === index ? null : index
}

const selectedRange = computed(() => {
  const bin = selectedBin.value === null ? undefined : distribution.value?.bins[selectedBin.value]

  return bin === undefined ? undefined : { bin, label: rangeLabel(bin) }
})

const selectedItems = computed(() => {
  const range = selectedRange.value

  return range === undefined ? [] : (distribution.value?.items ?? []).filter((item) => binContains(range.bin, item.logReturn))
})

const bucketItems = computed(() => {
  const current = distribution.value

  if (current === undefined || current.bucket === null) return []

  // The asked bucket can be older than the latest ones the list holds.
  const starts = [...new Set([...current.buckets, current.bucket])].sort((a, b) => Date.parse(b) - Date.parse(a))

  return starts.map((start) => ({
    value: start,
    label: start === current.buckets[0] ? `${formatBucketLabel(start)} (${t('market.latestBucket')})` : formatBucketLabel(start),
  }))
})

function onBucketChange(value: string): void {
  setBucket(value === distribution.value?.buckets[0] ? null : value)
}

const total = computed(() => {
  const breadth = distribution.value?.breadth

  return breadth === undefined ? 0 : breadth.up + breadth.down + breadth.flat
})

function share(count: number): string {
  return t('market.breadth.share', { percent: total.value === 0 ? 0 : Math.round((count / total.value) * 100) })
}

const columns = computed<TableColumn<MarketItem>[]>(() => [
  { accessorKey: 'name', header: t('common.columns.item') },
  { accessorKey: 'price', header: t('common.columns.price') },
  { accessorKey: 'logReturn', header: t('common.columns.priceChange') },
  { accessorKey: 'units', header: t('common.columns.tradesPerHour') },
])
</script>

<template>
  <div class="space-y-6">
    <h1 class="text-2xl font-semibold">{{ $t('seo.market.title', { league: leagueName }) }}</h1>

    <UAlert
      v-if="bucketMissing"
      color="warning"
      variant="subtle"
      icon="i-lucide-triangle-alert"
      :title="$t('market.bucketNotFound')"
      :actions="[{ label: $t('market.showLatest'), onClick: () => setBucket(null) }]"
    />

    <UAlert
      v-else-if="error"
      color="error"
      variant="subtle"
      icon="i-lucide-triangle-alert"
      :title="$t('market.loadError')"
      :actions="[{ label: $t('common.retry'), onClick: () => refresh() }]"
    />

    <div v-else-if="!distribution" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <USkeleton v-for="index in 4" :key="index" class="h-24" />
    </div>

    <p v-else-if="distribution.currency === null" class="text-muted">{{ $t('common.currenciesNotConfigured') }}</p>

    <p v-else-if="distribution.bucket === null" class="text-muted">{{ $t('common.metricsNotCalculated') }}</p>

    <div v-else class="space-y-6" :aria-busy="status === 'pending'" :class="{ 'opacity-60': status === 'pending' }">
      <div class="flex flex-wrap items-center gap-x-6 gap-y-3">
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
          <ClientOnly>
            <USelect
              :model-value="distribution.bucket"
              :items="bucketItems"
              class="w-72"
              :aria-label="$t('market.bucketLabel')"
              @update:model-value="onBucketChange"
            />
            <template #fallback>
              <USkeleton class="h-8 w-72" />
            </template>
          </ClientOnly>
          <p class="max-w-sm text-sm text-muted">{{ $t('market.bucketHint') }}</p>
        </div>
        <UCheckbox
          class="lg:ml-auto"
          :model-value="includeAll"
          :label="$t('market.includeAll')"
          :description="$t('market.includeAllHint')"
          @update:model-value="setIncludeAll($event === true)"
        />
      </div>

      <p v-if="distribution.items.length === 0" class="text-muted">{{ $t('market.noItems') }}</p>

      <template v-else>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <KpiTile :label="$t('market.breadth.up')">
            <span class="text-success">{{ distribution.breadth.up }}</span>
            <template #note>{{ share(distribution.breadth.up) }}</template>
          </KpiTile>
          <KpiTile :label="$t('market.breadth.down')">
            <span class="text-error">{{ distribution.breadth.down }}</span>
            <template #note>{{ share(distribution.breadth.down) }}</template>
          </KpiTile>
          <KpiTile :label="$t('market.breadth.flat')">
            {{ distribution.breadth.flat }}
            <template #note>{{ share(distribution.breadth.flat) }}</template>
          </KpiTile>
          <KpiTile :label="$t('market.marketReturn')">
            <span v-if="distribution.marketReturn !== null" :class="returnClass(distribution.marketReturn)">
              {{ formatReturn(distribution.marketReturn) }}
            </span>
            <span v-else class="text-muted">—</span>
          </KpiTile>
        </div>
        <p class="text-sm text-muted">{{ $t('market.marketReturnHint') }}</p>

        <section class="space-y-3">
          <h2 class="text-lg font-medium">{{ $t('market.histogram.title') }}</h2>
          <p class="text-sm text-muted">{{ $t('market.histogram.hint') }}</p>
          <ReturnHistogram
            :bins="distribution.bins"
            :market-return="distribution.marketReturn"
            :selected="selectedBin"
            @select="selectBin"
          />
        </section>

        <section v-if="selectedRange" class="space-y-3">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-medium">{{ $t('market.selected.title', { range: selectedRange.label }) }}</h2>
            <UButton color="neutral" variant="outline" icon="i-lucide-x" @click="selectedBin = null">
              {{ $t('market.selected.clear') }}
            </UButton>
          </div>
          <UTable :data="selectedItems" :columns="columns" :ui="{ th: 'align-bottom' }" :empty="$t('market.selected.empty')">
            <template #name-cell="{ row }">
              <NuxtLink :to="localePath(`/${slug}/items/${row.original.slug}`)" class="hover:text-primary">{{ row.original.name }}</NuxtLink>
            </template>
            <template #price-cell="{ row }">{{ formatPrice(row.original.price) }}</template>
            <template #logReturn-cell="{ row }">
              <span :class="returnClass(row.original.logReturn)">{{ formatReturn(row.original.logReturn) }}</span>
            </template>
            <template #units-cell="{ row }">
              <span :class="weightClass(row.original.returnWeight)">{{ formatUnits(row.original.units) }}</span>
            </template>
          </UTable>
        </section>

        <section class="space-y-3">
          <h2 class="text-lg font-medium">{{ $t('market.categories.title') }}</h2>
          <p class="text-sm text-muted">{{ $t('market.categories.hint') }}</p>
          <CategoryReturnChart :categories="distribution.byCategory" />
        </section>
      </template>
    </div>
  </div>
</template>

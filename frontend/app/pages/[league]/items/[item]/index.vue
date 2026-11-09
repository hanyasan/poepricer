<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui'
import type { ItemBucketRow } from '~/types/metrics'

const route = useRoute()
const { t } = useI18n()
const { leagueName } = useLeagueName()

const leagueSlug = computed(() => route.params.league as string)
const itemSlug = computed(() => route.params.item as string)
const { currencyName, period } = storeToRefs(useFiltersStore())

// The layout asks for the summary too; the request is the same, so the answer is shared.
// The three requests start together, so the server renders the page after the slowest one.
const summaryRequest = useItemSummary(leagueSlug, itemSlug)
const historyRequest = useItemHistory(leagueSlug, itemSlug)
const bucketsRequest = useItemBuckets(leagueSlug, itemSlug)

await Promise.all([summaryRequest, historyRequest, bucketsRequest])

const { data: summary } = summaryRequest
const { data: history, error: historyError, refresh: refreshHistory } = historyRequest
const { data: buckets, status: bucketsStatus, error: bucketsError, refresh: refreshBuckets, page, setPage } = bucketsRequest

const periodLabel = computed(() => t(`item.periodSuffix.${period.value}`))

const columns = computed<TableColumn<ItemBucketRow>[]>(() => [
  { accessorKey: 'bucket', header: t('common.columns.bucket') },
  { accessorKey: 'price', header: t('common.columns.price') },
  { accessorKey: 'logReturn', header: t('common.columns.priceChange') },
  { accessorKey: 'residual', header: t('common.columns.residual') },
  { accessorKey: 'residualMad', header: t('common.columns.fluctuation') },
  { accessorKey: 'observations', header: t('common.columns.sample') },
  { accessorKey: 'units', header: t('common.columns.tradesPerHour') },
])

const emptyText = computed(() =>
  (buckets.value?.meta.total ?? 0) > 0 ? t('common.noPage') : t('item.buckets.empty'),
)

// The title and the description are built from the item and its current price. A later page
// of the table of buckets is not a page to index. A missing item never gets here, the layout
// answers it.
const seoTitle = computed(() =>
  summary.value ? t('seo.item.title', { item: summary.value.item.name, league: leagueName.value }) : t('item.notFound'),
)
const seoDescription = computed(() => {
  if (!summary.value) return t('item.notFoundDescription')

  const params = { item: summary.value.item.name, league: leagueName.value }

  return summary.value.current === null
    ? t('seo.item.descriptionNoPrice', params)
    : t('seo.item.description', { ...params, price: formatPrice(summary.value.current.price), currency: currencyName.value ?? '' })
})

useBreadcrumbSchema(() => [
  { name: t('layout.leagues'), path: '/' },
  { name: leagueName.value, path: `/${leagueSlug.value}` },
  { name: t('league.items'), path: `/${leagueSlug.value}/items` },
  { name: summary.value?.item.name ?? t('item.notFound') },
])

useSeoMeta({
  title: seoTitle,
  description: seoDescription,
  ogTitle: seoTitle,
  ogDescription: seoDescription,
  robots: () => (route.query.page !== undefined ? 'noindex, follow' : undefined),
})
</script>

<template>
  <div v-if="summary" class="space-y-6">
    <p v-if="summary.currency === null" class="text-muted">{{ $t('common.currenciesNotConfigured') }}</p>

    <p v-else-if="summary.lastBucket === null" class="text-muted">{{ $t('common.metricsNotCalculated') }}</p>

    <template v-else>
      <i18n-t v-if="summary.current === null" keypath="item.noCurrent" tag="p" scope="global" class="text-muted">
        <template #date><LocalDateTime :value="summary.lastBucket" /></template>
      </i18n-t>

      <p v-if="summary.current === null && summary.currentPrice !== null" class="text-sm text-muted">
        {{ $t('item.currentPrice', { price: formatPrice(summary.currentPrice), currency: currencyName }) }}
      </p>

      <div v-else-if="summary.current !== null" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <KpiTile :label="$t('item.kpi.price', { currency: currencyName })">
          {{ formatPrice(summary.currentPrice ?? summary.current.price) }}
          <template v-if="summary.currentPriceAt" #note>
            {{ $t('common.updated') }} <LocalDateTime :value="summary.currentPriceAt" />
          </template>
        </KpiTile>
        <KpiTile :label="$t('item.kpi.priceChange')">
          <span :class="returnClass(summary.current.logReturn)">{{ orDash(summary.current.logReturn, formatReturn) }}</span>
        </KpiTile>
        <KpiTile :label="$t('item.kpi.residual')">
          <span :class="returnClass(summary.current.residual)">{{ orDash(summary.current.residual, formatReturn) }}</span>
          <template v-if="summary.current.residualMad !== null" #note>
            {{ $t('item.kpi.residualFluctuation', { value: formatPercent(summary.current.residualMad) }) }}
          </template>
        </KpiTile>
        <KpiTile :label="$t('item.kpi.tradesPerHour')">
          <span :class="weightClass(summary.current.returnWeight)">{{ formatUnits(summary.current.units) }}</span>
        </KpiTile>
        <KpiTile :label="$t('item.kpi.sample')">
          <span :class="summary.current.lowData ? 'text-muted' : ''">{{ summary.current.observations }}</span>
        </KpiTile>
        <KpiTile :label="$t('item.kpi.bucket')"><LocalDateTime :value="summary.lastBucket" /></KpiTile>
      </div>

      <UAlert
        v-if="historyError"
        color="error"
        variant="subtle"
        icon="i-lucide-triangle-alert"
        :title="$t('item.chartsLoadError')"
        :actions="[{ label: $t('common.retry'), onClick: () => refreshHistory() }]"
      />

      <p v-else-if="history && history.series.length === 0" class="text-muted">{{ $t('item.noSeries') }}</p>

      <template v-else-if="history">
        <section class="space-y-3">
          <h3 class="text-lg font-medium">{{ $t('item.priceTitle', { period: periodLabel }) }}</h3>
          <ItemPriceChart :points="history.series" />
        </section>

        <section class="space-y-3">
          <h3 class="flex items-center gap-1.5 text-lg font-medium">
            {{ $t('item.residualTitle', { period: periodLabel }) }}
            <InfoTooltip :label="$t('item.residualHelp.label')">
              <p>{{ $t('item.residualHelp.lineOne') }}</p>
              <p>{{ $t('item.residualHelp.lineTwo') }}</p>
            </InfoTooltip>
          </h3>
          <ItemResidualChart :points="history.series" />
        </section>
      </template>

      <section class="space-y-3">
        <h3 class="text-lg font-medium">{{ $t('item.beta.title') }}</h3>
        <p v-if="summary.beta === null" class="text-muted">{{ $t('item.beta.notCalculated') }}</p>
        <template v-else>
          <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <KpiTile :label="$t('item.beta.beta')">{{ summary.beta.beta.toFixed(2) }}</KpiTile>
            <KpiTile :label="$t('item.beta.raw')">{{ summary.beta.rawBeta.toFixed(2) }}</KpiTile>
            <KpiTile :label="$t('item.beta.rSquared')">{{ orDash(summary.beta.rSquared, (value) => value.toFixed(2)) }}</KpiTile>
            <KpiTile :label="$t('item.beta.calculated')"><LocalDateTime :value="summary.beta.calculatedAt" /></KpiTile>
          </div>
          <p class="text-sm text-muted">
            {{ $t('item.beta.explanation') }}
          </p>
          <ItemBetaChart v-if="history && history.betaHistory.length >= 2" :betas="history.betaHistory" />
          <p v-else-if="history" class="text-sm text-muted">
            {{ $t('item.beta.fewRecalculations', { count: history.betaHistory.length }) }}
          </p>
        </template>
      </section>

      <section class="space-y-3">
        <h3 class="text-lg font-medium">{{ $t('item.buckets.title') }}</h3>

        <UAlert
          v-if="bucketsError"
          color="error"
          variant="subtle"
          icon="i-lucide-triangle-alert"
          :title="$t('item.buckets.loadError')"
          :actions="[{ label: $t('common.retry'), onClick: () => refreshBuckets() }]"
        />

        <template v-else-if="buckets">
          <UTable
            :data="buckets.data"
            :columns="columns"
            :ui="{ th: 'align-bottom' }"
            :loading="bucketsStatus === 'pending'"
            :empty="emptyText"
          >
            <template #bucket-cell="{ row }"><LocalDateTime :value="row.original.bucket" /></template>
            <template #price-cell="{ row }">{{ formatPrice(row.original.price) }}</template>
            <template #logReturn-cell="{ row }">
              <span :class="returnClass(row.original.logReturn)">{{ orDash(row.original.logReturn, formatReturn) }}</span>
            </template>
            <template #residual-cell="{ row }">
              <span :class="returnClass(row.original.residual)">{{ orDash(row.original.residual, formatReturn) }}</span>
            </template>
            <template #residualMad-cell="{ row }">{{ orDash(row.original.residualMad, formatPercent) }}</template>
            <template #units-cell="{ row }">
              <span :class="weightClass(row.original.returnWeight)">{{ formatUnits(row.original.units) }}</span>
            </template>
          </UTable>

          <div v-if="buckets.meta.lastPage > 1" class="flex justify-center">
            <UPagination
              :page="page"
              :total="buckets.meta.total"
              :items-per-page="buckets.meta.perPage"
              :sibling-count="1"
              @update:page="setPage"
            />
          </div>
        </template>
      </section>
    </template>
  </div>
</template>

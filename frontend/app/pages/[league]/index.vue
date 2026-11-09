<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui'
import type { UnusualMove } from '~/types/metrics'

const route = useRoute()
const { t } = useI18n()
const localePath = useLocalePath()
const { leagueName } = useLeagueName()

const slug = computed(() => route.params.league as string)
const { currencyName } = storeToRefs(useFiltersStore())
const { data: overview, status, refresh } = await useLeagueOverview(slug)

useBreadcrumbSchema(() => [
  { name: t('layout.leagues'), path: '/' },
  { name: leagueName.value },
])

useSeoMeta({
  title: () => t('seo.league.title', { league: leagueName.value }),
  description: () => t('seo.league.description', { league: leagueName.value }),
  ogTitle: () => t('seo.league.title', { league: leagueName.value }),
  ogDescription: () => t('seo.league.description', { league: leagueName.value }),
})

const columns = computed<TableColumn<UnusualMove>[]>(() => [
  { accessorKey: 'name', header: t('common.columns.item') },
  { accessorKey: 'price', header: t('common.columns.price') },
  { accessorKey: 'logReturn', header: t('common.columns.priceChange') },
  { accessorKey: 'residual', header: t('common.columns.residual') },
  { accessorKey: 'ratio', header: t('common.columns.ratio') },
  { accessorKey: 'beta', header: t('common.columns.beta') },
  { accessorKey: 'observations', header: t('common.columns.sample') },
  { accessorKey: 'units', header: t('common.columns.tradesPerHour') },
])
</script>

<template>
  <div class="space-y-6">
    <h1 class="text-2xl font-semibold">{{ $t('seo.league.title', { league: leagueName }) }}</h1>

    <UAlert
      v-if="status === 'error'"
      color="error"
      variant="subtle"
      icon="i-lucide-triangle-alert"
      :title="$t('overview.loadError')"
      :actions="[{ label: $t('common.retry'), onClick: () => refresh() }]"
    />

    <div v-else-if="!overview" class="space-y-6">
      <KpiSkeletonGrid />
      <div class="space-y-3">
        <USkeleton class="h-7 w-48" />
        <USkeleton class="h-72" />
      </div>
    </div>

    <p v-else-if="overview.currency === null" class="text-muted">{{ $t('common.currenciesNotConfigured') }}</p>

    <template v-else>
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <KpiTile :label="$t('overview.kpi.lastBucketReturn')">
          <span v-if="overview.kpi.marketReturnLastBucket !== null" :class="returnClass(overview.kpi.marketReturnLastBucket)">
            {{ formatReturn(overview.kpi.marketReturnLastBucket) }}
          </span>
          <span v-else class="text-muted">—</span>
        </KpiTile>
        <KpiTile :label="$t('overview.kpi.return7d')">
          <span v-if="overview.kpi.marketReturn7d !== null" :class="returnClass(overview.kpi.marketReturn7d)">
            {{ formatReturn(overview.kpi.marketReturn7d) }}
          </span>
          <span v-else class="text-muted">—</span>
        </KpiTile>
        <KpiTile :label="$t('overview.kpi.basketSize')">
          {{ overview.kpi.basketSize }}
          <template #note>{{ $t('home.outOf', { total: overview.kpi.basketTotal }) }}</template>
        </KpiTile>
        <KpiTile :label="$t('overview.kpi.updated')"><LocalDateTime :value="overview.kpi.lastSnapshotAt" :empty="$t('common.noSnapshots')" /></KpiTile>
      </div>

      <p v-if="overview.lastBucket === null" class="text-muted">{{ $t('common.metricsNotCalculated') }}</p>

      <template v-else>
        <section class="space-y-3">
          <h2 class="text-lg font-medium">{{ $t('overview.marketReturn') }}</h2>
          <LineChart :points="overview.marketReturnSeries" />
        </section>

        <section class="space-y-3">
          <h2 class="flex items-center gap-1.5 text-lg font-medium">
            {{ $t('overview.unusualMoves') }}
            <InfoTooltip :label="$t('overview.unusualHelp.label')">
              <p>{{ $t('overview.unusualHelp.lineOne') }}</p>
              <p>{{ $t('overview.unusualHelp.lineTwo', { currency: currencyName }) }}</p>
              <p>{{ $t('overview.unusualHelp.lineThree') }}</p>
            </InfoTooltip>
          </h2>
          <p v-if="overview.unusualMoves.length === 0" class="text-muted">{{ $t('overview.noUnusualMoves') }}</p>
          <UTable v-else :data="overview.unusualMoves" :columns="columns" :ui="{ th: 'align-bottom' }">
            <template #name-cell="{ row }">
              <NuxtLink :to="localePath(`/${slug}/items/${row.original.slug}`)" class="hover:text-primary">{{ row.original.name }}</NuxtLink>
            </template>
            <template #price-cell="{ row }">{{ formatPrice(row.original.price) }}</template>
            <template #logReturn-cell="{ row }">
              <span :class="returnClass(row.original.logReturn)">{{ formatReturn(row.original.logReturn) }}</span>
            </template>
            <template #residual-cell="{ row }">
              <span :class="returnClass(row.original.residual)">{{ formatReturn(row.original.residual) }}</span>
            </template>
            <template #ratio-cell="{ row }">{{ row.original.ratio.toFixed(1) }}×</template>
            <template #beta-cell="{ row }">{{ row.original.beta.toFixed(2) }}</template>
            <template #units-cell="{ row }">
              <span :class="weightClass(row.original.returnWeight)">{{ formatUnits(row.original.units) }}</span>
            </template>
          </UTable>
        </section>

        <section class="grid gap-4 lg:grid-cols-2">
          <ItemMoveList :title="$t('overview.topGainers')" :moves="overview.topGainers" :league-slug="slug" />
          <ItemMoveList :title="$t('overview.topLosers')" :moves="overview.topLosers" :league-slug="slug" />
        </section>
      </template>
    </template>
  </div>
</template>

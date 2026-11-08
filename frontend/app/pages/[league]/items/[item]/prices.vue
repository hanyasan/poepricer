<script setup lang="ts">
// The metric currency and period of the league toolbar mean nothing here: the prices have their own.
definePageMeta({ usesPriceToolbar: true })

const route = useRoute()
const { t } = useI18n()
const { leagueName } = useLeagueName()

const leagueSlug = computed(() => route.params.league as string)
const itemSlug = computed(() => route.params.item as string)

// The layout asks for the summary too, so the answer is shared; here it gives the name of the item.
const summaryRequest = useItemSummary(leagueSlug, itemSlug)
const pricesRequest = useItemPrices(leagueSlug, itemSlug)

await Promise.all([summaryRequest, pricesRequest])

const { data: summary } = summaryRequest
const { data: prices, status, error, refresh } = pricesRequest

const currencyName = computed(
  () => prices.value?.currencies.find((item) => item.slug === prices.value?.currency)?.name ?? prices.value?.currency ?? '',
)
const changes = computed(() => prices.value?.stats?.changes ?? [])

// The title and the description name the item and its current price. A view picked by the query
// is a copy of the page, so it is not indexed.
const itemName = computed(() => summary.value?.item.name ?? t('item.notFound'))
const seoTitle = computed(() => t('seo.itemPrices.title', { item: itemName.value, league: leagueName.value }))
const seoDescription = computed(() => {
  const params = { item: itemName.value, league: leagueName.value }
  const stats = prices.value?.stats

  return stats
    ? t('seo.itemPrices.description', { ...params, price: formatPrice(stats.current.price), currency: currencyName.value })
    : t('seo.itemPrices.descriptionNoPrice', params)
})

useBreadcrumbSchema(() => [
  { name: t('layout.leagues'), path: '/' },
  { name: leagueName.value, path: `/${leagueSlug.value}` },
  { name: t('league.items'), path: `/${leagueSlug.value}/items` },
  { name: itemName.value, path: `/${leagueSlug.value}/items/${itemSlug.value}` },
  { name: t('item.tabs.prices') },
])

useSeoMeta({
  title: seoTitle,
  description: seoDescription,
  ogTitle: seoTitle,
  ogDescription: seoDescription,
  robots: () => (route.query.window !== undefined || route.query.currency !== undefined ? 'noindex, follow' : undefined),
})
</script>

<template>
  <div class="space-y-6">
    <UAlert
      v-if="error"
      color="error"
      variant="subtle"
      icon="i-lucide-triangle-alert"
      :title="$t('itemPrices.loadError')"
      :actions="[{ label: $t('common.retry'), onClick: () => refresh() }]"
    />

    <USkeleton v-else-if="!prices" class="h-96" />

    <p v-else-if="!prices.stats" class="text-muted">{{ $t('itemPrices.empty') }}</p>

    <div v-else :class="status === 'pending' ? 'space-y-6 opacity-60' : 'space-y-6'">
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <KpiTile :label="$t('itemPrices.kpi.price', { currency: currencyName })">
          {{ formatPrice(prices.stats.current.price) }}
          <template #note><LocalDateTime :value="prices.stats.current.at" /></template>
        </KpiTile>
        <KpiTile v-for="item in changes" :key="item.hours" :label="$t('itemPrices.kpi.change', { hours: item.hours })">
          <span :class="returnClass(item.change)">{{ orDash(item.change, formatReturn) }}</span>
        </KpiTile>
        <KpiTile :label="$t('itemPrices.kpi.min')">{{ formatPrice(prices.stats.min) }}</KpiTile>
        <KpiTile :label="$t('itemPrices.kpi.max')">{{ formatPrice(prices.stats.max) }}</KpiTile>
        <KpiTile :label="$t('itemPrices.kpi.median')">{{ formatPrice(prices.stats.median) }}</KpiTile>
        <KpiTile :label="$t('itemPrices.kpi.averageUnits')">{{ formatUnits(prices.stats.averageUnits) }}</KpiTile>
      </div>

      <section class="space-y-3">
        <h3 class="flex items-center gap-1.5 text-lg font-medium">
          {{ $t('itemPrices.chartTitle', { currency: currencyName }) }}
          <InfoTooltip :label="$t('itemPrices.unitsHelp.label')">
            <p>{{ $t('itemPrices.unitsHelp.lineOne') }}</p>
            <p>{{ $t('itemPrices.unitsHelp.lineTwo') }}</p>
            <p>{{ $t('itemPrices.unitsHelp.lineThree') }}</p>
          </InfoTooltip>
        </h3>
        <ItemPricesChart
          :points="prices.points"
          :from="prices.from"
          :to="prices.to"
          :window="prices.window"
          :currency-name="currencyName"
        />
      </section>
    </div>
  </div>
</template>

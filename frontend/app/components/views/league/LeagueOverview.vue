<script setup lang="ts">
import type { MetricPeriod } from '~/types/metrics'

import LeagueUnusualMoves from "~/components/views/league/LeagueUnusualMoves.vue";

const { t } = useI18n()
const route = useRoute()
const localePath = useLocalePath()

const slug = computed(() => route.params.league as string)

// No await: a component that suspends while a tab is switched on the client leaves the page waiting.
const { data: overview, status, refresh } = useLeagueOverview(slug)

const { period, currency, currencyName } = storeToRefs(useFiltersStore())
const periodItems = computed<{ label: string; value: MetricPeriod }[]>(() => [
  { label: t('league.periods.7d'), value: '7d' },
  { label: t('league.periods.league'), value: 'league' },
])

/** The returns of the buckets are logarithmic, so the return of the whole period is their sum. */
const totalReturn = computed(() => {
  const points = overview.value?.marketReturnSeries ?? []

  return points.length === 0 ? null : points.reduce((sum, point) => sum + point.value, 0)
})
</script>

<template>
    <UAlert
      v-if="status === 'error'"
      color="error"
      variant="subtle"
      icon="i-lucide-triangle-alert"
      :title="$t('overview.loadError')"
      :actions="[{ label: $t('common.retry'), onClick: () => refresh() }]"
    />

    <div v-else-if="!overview" class="space-y-6">
      <USkeleton class="h-8 w-72" />
      <USkeleton class="h-72" />
    </div>

    <p v-else-if="overview.currency === null" class="text-muted">{{ $t('common.currenciesNotConfigured') }}</p>

    <p v-else-if="overview.lastBucket === null" class="text-muted">{{ $t('common.metricsNotCalculated') }}</p>

    <template v-else>
      <section class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div class="flex flex-wrap items-baseline gap-x-4 gap-y-1">
            <h2 class="text-lg font-medium">{{ $t('overview.marketReturn') }}</h2>
            <p v-if="totalReturn !== null" class="text-sm text-muted">
              {{ $t('overview.totalReturn', { period: $t(`overview.totalReturnPeriods.${period}`) }) }}
              <span class="font-medium" :class="returnClass(totalReturn)">{{ formatReturn(totalReturn) }}</span>
            </p>
          </div>
          <USelect v-model="period" :items="periodItems" class="w-36" :aria-label="$t('league.periodLabel')" />
        </div>
        <LineChart :points="overview.marketReturnSeries" />
      </section>

      <section class="grid gap-4 lg:grid-cols-2">
        <ItemMoveList :title="$t('overview.topGainers')" :moves="overview.topGainers" :league-slug="slug" />
        <ItemMoveList :title="$t('overview.topLosers')" :moves="overview.topLosers" :league-slug="slug" />
      </section>

      <LeagueUnusualMoves :moves="overview.unusualMoves" :currency-name="currencyName ?? ''" :league-slug="slug" />

      <div class="flex justify-end">
        <UButton
          :to="{ path: localePath(`/${slug}/items`), query: currency ? { currency } : {} }"
          color="neutral"
          variant="outline"
          trailing-icon="i-lucide-arrow-right"
        >
          {{ $t('league.allItems') }}
        </UButton>
      </div>
    </template>
</template>

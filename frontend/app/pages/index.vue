<script setup lang="ts">
import type { League } from '~/types/metrics'

const { t } = useI18n()
const localePath = useLocalePath()
const { leagues, status, refresh } = await useLeagues()

useSchemaOrg([defineWebSite({ name: 'PoE Pricer', inLanguage: useI18n().locale.value })])

useSeoMeta({
  title: () => t('seo.home.title'),
  description: () => t('seo.home.description'),
  ogTitle: () => t('seo.home.title'),
  ogDescription: () => t('seo.home.description'),
})

const groups = computed(() =>
  GAMES.map((game) => ({ game, title: gameTitle(game), leagues: leagues.value.filter((league) => league.game === game) }))
    .filter((group) => group.leagues.length > 0),
)

function lastReturn(league: League): number | null {
  const series = league.marketReturnSeries
  return series.length > 0 ? series[series.length - 1]!.value : null
}
</script>

<template>
  <div class="space-y-6">
    <h1 class="text-2xl font-semibold">{{ $t('home.title') }}</h1>

    <p class="max-w-3xl text-sm leading-relaxed text-toned">
      {{ $t('home.intro') }}
      <NuxtLink :to="localePath('/about/metrics')" class="text-primary hover:underline">{{ $t('home.metricsLink') }}</NuxtLink>
    </p>

    <div v-if="status === 'pending'" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <USkeleton v-for="index in 3" :key="index" class="h-44" />
    </div>

    <UAlert
      v-else-if="status === 'error'"
      color="error"
      variant="subtle"
      icon="i-lucide-triangle-alert"
      :title="$t('home.loadError')"
      :actions="[{ label: $t('common.retry'), onClick: () => refresh() }]"
    />

    <p v-else-if="groups.length === 0" class="text-muted">{{ $t('home.empty') }}</p>

    <template v-else>
      <section v-for="group in groups" :key="group.game" class="space-y-3">
        <h2 class="text-lg font-medium text-muted">{{ group.title }}</h2>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <NuxtLink v-for="league in group.leagues" :key="league.slug" :to="localePath(`/${league.slug}`)">
            <UCard class="h-full transition hover:ring-primary">
              <p class="text-lg font-medium">{{ league.name }}</p>

              <dl class="mt-3 grid grid-cols-2 gap-2 text-sm">
                <div>
                  <dt class="text-muted">{{ $t('home.inMarket') }}</dt>
                  <dd class="font-medium">
                    {{ league.basketSize }}
                    <span class="font-normal text-muted">{{ $t('home.outOf', { total: league.basketTotal }) }}</span>
                  </dd>
                </div>
                <div>
                  <dt class="text-muted">{{ $t('home.updated') }}</dt>
                  <dd class="font-medium"><LocalDateTime :value="league.lastSnapshotAt" :empty="$t('common.noSnapshots')" /></dd>
                </div>
              </dl>

              <div class="mt-3 flex items-end justify-between text-sm">
                <span class="text-muted">{{ $t('home.return7d') }}</span>
                <span v-if="lastReturn(league) !== null" :class="returnClass(lastReturn(league)!)" class="font-medium">
                  {{ formatReturn(lastReturn(league)!) }}
                </span>
              </div>
              <MarketReturnSparkline :points="league.marketReturnSeries" />
            </UCard>
          </NuxtLink>
        </div>
      </section>
    </template>
  </div>
</template>

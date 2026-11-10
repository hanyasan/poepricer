<script setup lang="ts">
import LeagueMarket from '~/components/views/league/LeagueMarket.vue'
import LeagueOverview from '~/components/views/league/LeagueOverview.vue'

definePageMeta({ layout: 'league' })

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const { leagueName } = useLeagueName()

const tabs = computed(() => [
  { value: 'overview', label: t('league.overview'), slot: 'overview' as const },
  { value: 'market', label: t('league.market'), slot: 'market' as const },
])

/**
 * The tab lives in the URL: the bucket and the thin items of the market tab change the query,
 * which remounts the page, and a local tab would fall back to the overview.
 */
const activeTab = computed({
  get: () => (route.query.tab === 'market' ? 'market' : 'overview'),
  set: (value: string | number) => {
    // The parameters of the market tab mean nothing on the overview.
    const { bucket: _bucket, all: _all, tab: _tab, ...rest } = route.query

    void router.replace({ query: value === 'market' ? { ...rest, tab: 'market' } : rest })
  },
})

useBreadcrumbSchema(() => [
  { name: t('layout.leagues'), path: '/' },
  { name: leagueName.value },
])

useSeoMeta({
  title: () => t('seo.league.title', { league: leagueName.value }),
  description: () => t('seo.league.description', { league: leagueName.value }),
  ogTitle: () => t('seo.league.title', { league: leagueName.value }),
  ogDescription: () => t('seo.league.description', { league: leagueName.value }),
  // The market tab and the bucket picked on it are views of the same page, not pages of their own.
  robots: () => (Object.keys(route.query).length > 0 ? 'noindex, follow' : undefined),
})
</script>

<template>
  <div class="space-y-6">
    <LeagueTitle>{{ $t('seo.league.title', { league: leagueName }) }}</LeagueTitle>
    <UTabs :items="tabs" v-model="activeTab" :unmount-on-hide="false" class="w-full">
      <template #overview>
        <div class="space-y-6 pt-4">
          <LeagueOverview />
        </div>
      </template>
      <template #market>
        <div class="pt-4">
          <LeagueMarket />
        </div>
      </template>
    </UTabs>
  </div>
</template>

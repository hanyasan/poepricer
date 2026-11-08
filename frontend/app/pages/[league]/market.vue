<script setup lang="ts">
const { t } = useI18n()
const { marketSeries } = useItemMetrics()
const { leagueName } = useLeagueName()

const route = useRoute()

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
})
</script>

<template>
  <div class="space-y-4">
    <h1 class="text-2xl font-semibold">{{ $t('seo.market.title', { league: leagueName }) }}</h1>
    <h2 class="text-lg font-medium">{{ $t('market.title') }}</h2>
    <LineChart :points="marketSeries" />
  </div>
</template>

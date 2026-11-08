<script setup lang="ts">
const route = useRoute()
const { t } = useI18n()
const localePath = useLocalePath()

const slug = computed(() => route.params.league as string)
const basePath = computed(() => localePath(`/${slug.value}`))
const { league } = await useLeague(slug)
await useLeagueCurrencies(slug)

// A page can hide the section menu; the league name always links to the overview.
const showNav = computed(() => route.meta.showLeagueNav !== false)

const links = computed(() => [
  // The overview path is the prefix of every other page of the league, so it matches exactly only.
  { label: t('league.overview'), to: basePath.value, exact: true },
  { label: t('league.items'), to: localePath(`/${slug.value}/items`) },
  { label: t('league.market'), to: localePath(`/${slug.value}/market`) },
])
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <NuxtLink :to="basePath" class="text-lg font-semibold hover:text-primary">{{ league?.name ?? slug }}</NuxtLink>
      <LeagueToolbar :show-period="route.meta.usesPeriod !== false" />
    </div>
    <UNavigationMenu v-if="showNav" :items="links" />
    <NuxtPage />
  </div>
</template>

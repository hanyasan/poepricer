<script setup lang="ts">
const route = useRoute()
const { t } = useI18n()
const localePath = useLocalePath()

const slug = computed(() => route.params.league as string)
const basePath = computed(() => localePath(`/${slug.value}`))
const { league } = await useLeague(slug)
// A league outside the main list has no game of its own, so it is taken from the slug.
const game = computed(() => league.value?.game ?? gameFromSlug(slug.value))
await useLeagueCurrencies(slug)

// A page can hide the section menu and picks its own toolbar; the league name always links
// to the overview. The route changes before the new page is ready and the old page stays on
// screen until then, so the menu and the toolbar follow the page that is shown, not the route.
const nuxtApp = useNuxtApp()

function shownPageSettings() {
  return {
    showNav: route.meta.showLeagueNav !== false,
    usesPriceToolbar: route.meta.usesPriceToolbar === true,
    usesPeriod: route.meta.usesPeriod !== false,
  }
}

const shown = ref(shownPageSettings())

const stopListening = nuxtApp.hook('page:finish', () => {
  shown.value = shownPageSettings()
})
onBeforeUnmount(stopListening)

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
      <div class="flex flex-wrap items-baseline gap-x-3">
        <NuxtLink :to="basePath" class="text-lg font-semibold hover:text-primary">{{ league?.name ?? slug }}</NuxtLink>
        <span v-if="game" class="text-sm text-muted">{{ gameTitle(game) }}</span>
      </div>
      <ItemPriceToolbar v-if="shown.usesPriceToolbar" />
      <LeagueToolbar v-else :show-period="shown.usesPeriod" />
    </div>
    <UNavigationMenu v-if="shown.showNav" :items="links" />
    <NuxtPage />
  </div>
</template>

<script setup lang="ts">
const route = useRoute()

const slug = computed(() => route.params.league as string)
const basePath = computed(() => `/${slug.value}`)
const { league } = await useLeague(slug)
await useLeagueCurrencies(slug)

// A page can hide the section menu; the league name then links to the overview.
const showNav = computed(() => route.meta.showLeagueNav !== false)

const links = computed(() => [
  // The overview path is the prefix of every other page of the league, so it matches exactly only.
  { label: 'Обзор', to: basePath.value, exact: true },
  { label: 'Предметы', to: `${basePath.value}/items` },
  { label: 'Рынок', to: `${basePath.value}/market` },
])
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h1 class="text-2xl font-semibold">
        <NuxtLink v-if="!showNav" :to="basePath" class="hover:text-primary">{{ league?.name ?? slug }}</NuxtLink>
        <template v-else>{{ league?.name ?? slug }}</template>
      </h1>
      <LeagueToolbar :show-period="route.meta.usesPeriod !== false" />
    </div>
    <UNavigationMenu v-if="showNav" :items="links" />
    <NuxtPage />
  </div>
</template>

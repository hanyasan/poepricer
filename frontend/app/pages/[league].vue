<script setup lang="ts">
const route = useRoute()

const slug = computed(() => route.params.league as string)
const basePath = computed(() => `/${slug.value}`)
const { league } = await useLeague(slug)
await useLeagueCurrencies(slug)

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
      <h1 class="text-2xl font-semibold">{{ league?.name ?? slug }}</h1>
      <LeagueToolbar :show-period="route.meta.usesPeriod !== false" />
    </div>
    <UNavigationMenu :items="links" />
    <NuxtPage />
  </div>
</template>

<script setup lang="ts">
const route = useRoute()

const slug = computed(() => route.params.league as string)
const basePath = computed(() => `/${slug.value}`)
const { league } = await useLeague(slug)

const links = computed(() => [
  { label: 'Обзор', to: basePath.value },
  { label: 'Предметы', to: `${basePath.value}/items` },
  { label: 'Рынок', to: `${basePath.value}/market` },
])
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h1 class="text-2xl font-semibold">{{ league?.name ?? slug }}</h1>
      <LeagueToolbar v-if="league" :game="league.game" />
    </div>
    <UNavigationMenu :items="links" />
    <NuxtPage />
  </div>
</template>

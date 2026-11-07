<script setup lang="ts">
import type { Game } from '~/types/metrics'

const route = useRoute()

const game = computed(() => route.params.game as Game)
const league = computed(() => route.params.league as string)
const basePath = computed(() => `/${game.value}/${league.value}`)

const links = computed(() => [
  { label: 'Обзор', to: basePath.value },
  { label: 'Предметы', to: `${basePath.value}/items` },
  { label: 'Рынок', to: `${basePath.value}/market` },
])
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h1 class="text-2xl font-semibold">{{ game }} / {{ league }}</h1>
      <LeagueToolbar :game="game" />
    </div>
    <UNavigationMenu :items="links" />
    <NuxtPage />
  </div>
</template>

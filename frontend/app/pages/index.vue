<script setup lang="ts">
const { leagues } = useLeagues()
</script>

<template>
  <div class="space-y-4">
    <h1 class="text-2xl font-semibold">Лиги</h1>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <NuxtLink v-for="league in leagues" :key="`${league.game}-${league.slug}`" :to="`/${league.game}/${league.slug}`">
        <UCard>
          <p class="text-xs uppercase text-muted">{{ league.game }}</p>
          <p class="text-lg font-medium">{{ league.name }}</p>
          <p class="text-sm text-muted">Предметов в корзине: {{ league.basketSize }}</p>
        </UCard>
      </NuxtLink>
    </div>
  </div>
</template>

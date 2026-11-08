<script setup lang="ts">
import type { ItemMove } from '~/types/metrics'

defineProps<{ title: string; moves: ItemMove[]; leagueSlug: string }>()
</script>

<template>
  <UCard>
    <template #header>
      <h3 class="font-medium">{{ title }}</h3>
    </template>

    <p v-if="moves.length === 0" class="text-sm text-muted">Нет данных.</p>

    <ul v-else class="divide-y divide-default">
      <li v-for="move in moves" :key="move.slug" class="flex items-center justify-between gap-3 py-2 text-sm">
        <NuxtLink :to="`/${leagueSlug}/items/${move.slug}`" class="truncate hover:text-primary">{{ move.name }}</NuxtLink>
        <span class="flex shrink-0 items-baseline gap-3">
          <span class="text-muted">{{ formatPrice(move.price) }}</span>
          <span :class="returnClass(move.logReturn)" class="w-20 text-right font-medium">{{ formatReturn(move.logReturn) }}</span>
        </span>
      </li>
    </ul>
  </UCard>
</template>

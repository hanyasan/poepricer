<script setup lang="ts">

import Footer from '~/components/base/Footer.vue'
import Header from  '~/components/base/Header.vue'

const route = useRoute()
const localePath = useLocalePath()

const slug = computed(() => route.params.league as string)
const basePath = computed(() => localePath(`/${slug.value}`))
const game = computed(() => league.value?.game ?? gameFromSlug(slug.value))

const { currency } = storeToRefs(useFiltersStore())
const { league } = await useLeague(slug)
await useLeagueCurrencies(slug)


const itemsPath = computed(() => localePath(`/${slug.value}/items`))

// The selected currency goes with the link, so the switch keeps it in the address.
const query = computed(() => (currency.value ? { currency: currency.value } : {}))

const navItems = computed(() => [
  { label: 'league.overview', to: basePath.value, active: route.path === basePath.value },
  { label: 'league.items', to: itemsPath.value, active: route.path.startsWith(itemsPath.value) },
])

</script>

<template>
  <div class="min-h-screen">
    <Header/>
    <UContainer class="py-6">
        <div class="space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
              <div class="flex flex-wrap items-baseline gap-x-3">
                <NuxtLink :to="basePath" class="text-lg font-semibold hover:text-primary">{{ league?.name ?? slug }}</NuxtLink>
                <span v-if="game" class="text-sm text-muted">{{ gameTitle(game) }}</span>
              </div>
              <LeagueToolbar />
            </div>
            <nav class="flex gap-4 border-b border-default text-sm">
              <NuxtLink
                v-for="item in navItems"
                :key="item.to"
                :to="{ path: item.to, query }"
                class="-mb-px border-b-2 px-1 pb-2 font-medium"
                :class="item.active ? 'border-primary text-highlighted' : 'border-transparent text-muted hover:text-default'"
                :aria-current="item.active ? 'page' : undefined"
              >
                {{ $t(item.label) }}
              </NuxtLink>
            </nav>
          <slot />
        </div>
    </UContainer>
    <Footer/>
  </div>
</template>

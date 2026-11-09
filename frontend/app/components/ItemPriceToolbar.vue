<script setup lang="ts">
import type { ItemPrices, PriceWindow } from '~/types/metrics'

/**
 * The currency and the window of the price series of an item, in the place of the league toolbar
 * on the tab of the prices. The request is the one the page makes, so they share the answer.
 */
const route = useRoute()
const { t } = useI18n()

// While the page is left the route has no item for a moment, before the toolbar goes away;
// the slugs keep the last known value so no request is made for a missing item.
const leagueSlug = ref(route.params.league as string)
const itemSlug = ref(route.params.item as string)

watch(
  () => [route.params.league, route.params.item],
  ([league, item]) => {
    if (typeof league === 'string' && typeof item === 'string') {
      leagueSlug.value = league
      itemSlug.value = item
    }
  },
)

const controls = useItemPriceControls(leagueSlug, itemSlug)
const { priceWindow, setWindow, setCurrency } = controls

// The page makes the request. Only the server render asks here, because the toolbar is drawn
// before the page has its answer; the browser reads the answer the page has put in the payload.
const nuxtApp = useNuxtApp()
const serverRequest = import.meta.server ? useItemPrices(leagueSlug, itemSlug) : null
const prices = computed(() => serverRequest?.data.value ?? (nuxtApp.payload.data[controls.key.value] as ItemPrices | undefined))
const error = computed(() => serverRequest?.error.value ?? nuxtApp.payload._errors[controls.key.value])

const currencyItems = computed(() => (prices.value?.currencies ?? []).map((item) => ({ label: item.name, value: item.slug })))
const windowItems = computed(() => PRICE_WINDOWS.map((value) => ({ label: t(`itemPrices.windows.${value}`), value })))
</script>

<template>
  <div v-if="!error" class="flex flex-wrap gap-3">
    <USelect
      v-if="currencyItems.length > 0"
      :model-value="prices?.currency ?? undefined"
      :items="currencyItems"
      class="w-48"
      :aria-label="$t('itemPrices.currencyLabel')"
      @update:model-value="setCurrency"
    />
    <USelect
      :model-value="priceWindow"
      :items="windowItems"
      class="w-36"
      :aria-label="$t('itemPrices.windowLabel')"
      @update:model-value="(value: PriceWindow) => setWindow(value)"
    />
  </div>
</template>

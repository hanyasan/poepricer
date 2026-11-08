<script setup lang="ts">
import type { PriceWindow } from '~/types/metrics'

/**
 * The currency and the window of the price series of an item, in the place of the league toolbar
 * on the tab of the prices. The request is the one the page makes, so they share the answer.
 */
const route = useRoute()
const { t } = useI18n()

const leagueSlug = computed(() => route.params.league as string)
const itemSlug = computed(() => route.params.item as string)

const { data: prices, error, priceWindow, setWindow, setCurrency } = useItemPrices(leagueSlug, itemSlug)

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

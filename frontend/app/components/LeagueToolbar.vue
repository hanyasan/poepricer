<script setup lang="ts">
import type { MetricPeriod } from '~/types/metrics'

withDefaults(defineProps<{ showPeriod?: boolean }>(), { showPeriod: true })

const filters = useFiltersStore()
const { currencies, currency, period } = storeToRefs(filters)

const currencyItems = computed(() => currencies.value.map((item) => ({ label: item.name, value: item.slug })))

const periodItems: { label: string; value: MetricPeriod }[] = [
  { label: '24 часа', value: '24h' },
  { label: '7 дней', value: '7d' },
  { label: 'Вся лига', value: 'league' },
]
</script>

<template>
  <div class="flex flex-wrap gap-3">
    <USelect
      v-if="currencyItems.length > 0"
      :model-value="currency ?? undefined"
      :items="currencyItems"
      class="w-48"
      @update:model-value="currency = $event"
    />
    <USelect v-if="showPeriod" v-model="period" :items="periodItems" class="w-36" />
  </div>
</template>

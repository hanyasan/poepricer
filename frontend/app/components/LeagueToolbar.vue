<script setup lang="ts">
import type { MetricPeriod } from '~/types/metrics'

withDefaults(defineProps<{ showPeriod?: boolean }>(), { showPeriod: true })

const { t } = useI18n()
const filters = useFiltersStore()
const { currencies, currency, period } = storeToRefs(filters)

const currencyItems = computed(() => currencies.value.map((item) => ({ label: item.name, value: item.slug })))

const periodItems = computed<{ label: string; value: MetricPeriod }[]>(() => [
  { label: t('league.periods.24h'), value: '24h' },
  { label: t('league.periods.7d'), value: '7d' },
  { label: t('league.periods.league'), value: 'league' },
])
</script>

<template>
  <div class="flex flex-wrap gap-3">
    <USelect
      v-if="currencyItems.length > 0"
      :model-value="currency ?? undefined"
      :items="currencyItems"
      class="w-48"
      :aria-label="$t('league.currencyLabel')"
      @update:model-value="currency = $event"
    />
    <USelect v-if="showPeriod" v-model="period" :items="periodItems" class="w-36" />
  </div>
</template>

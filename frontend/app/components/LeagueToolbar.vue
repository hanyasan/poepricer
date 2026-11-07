<script setup lang="ts">
import type { Game, MetricPeriod } from '~/types/metrics'
import { currenciesByGame } from '~/stores/filters'

const props = defineProps<{ game: Game }>()

const filters = useFiltersStore()
const { currency, period } = storeToRefs(filters)

const currencyItems = computed(() => currenciesByGame[props.game].map((value) => ({ label: value, value })))

const periodItems: { label: string; value: MetricPeriod }[] = [
  { label: '24 часа', value: '24h' },
  { label: '7 дней', value: '7d' },
  { label: 'Вся лига', value: 'league' },
]

watchEffect(() => {
  if (!currenciesByGame[props.game].includes(currency.value)) {
    currency.value = currenciesByGame[props.game][0] ?? 'divine'
  }
})
</script>

<template>
  <div class="flex flex-wrap gap-3">
    <USelect v-model="currency" :items="currencyItems" class="w-36" />
    <USelect v-model="period" :items="periodItems" class="w-36" />
  </div>
</template>

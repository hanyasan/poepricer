<script setup lang="ts">
const filters = useFiltersStore()
const { currencies, currency } = storeToRefs(filters)

const currencyItems = computed(() => currencies.value.map((item) => ({ label: item.name, value: item.slug })))
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
  </div>
</template>

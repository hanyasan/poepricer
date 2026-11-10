<script setup lang="ts">
const { currencyName } = storeToRefs(useFiltersStore())
</script>

<template>
  <h1 class="text-2xl font-semibold">
    <slot />
    <span v-if="currencyName" class="ml-2 text-base font-normal text-muted">{{ $t('layout.inCurrency', { currency: currencyName }) }}</span>
  </h1>
</template>

<script setup lang="ts">
/**
 * The browser's time zone is unknown on the server, so the date is rendered on
 * the client only.
 */
const props = defineProps<{ value: string | null; empty?: string }>()

const { localeProperties } = useI18n()

const text = computed(() =>
  props.value === null ? (props.empty ?? '—') : dateTimeFormatter(localeProperties.value.language).format(new Date(props.value)),
)
</script>

<template>
  <ClientOnly>
    {{ text }}
    <template #fallback>{{ value === null ? (empty ?? '—') : '…' }}</template>
  </ClientOnly>
</template>

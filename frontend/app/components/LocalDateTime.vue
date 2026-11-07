<script setup lang="ts">
/**
 * The browser's time zone is unknown on the server, so the date is rendered on
 * the client only.
 */
const props = defineProps<{ value: string | null; empty?: string }>()

const formatter = new Intl.DateTimeFormat('ru-RU', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })

const text = computed(() => (props.value === null ? (props.empty ?? '—') : formatter.format(new Date(props.value))))
</script>

<template>
  <ClientOnly>
    {{ text }}
    <template #fallback>{{ value === null ? (empty ?? '—') : '…' }}</template>
  </ClientOnly>
</template>

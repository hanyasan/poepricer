<script setup lang="ts">
/**
 * A help icon next to a heading; the explanation shows on hover and the icon links
 * to the description of the metrics.
 */
defineProps<{ label: string }>()

const localePath = useLocalePath()
</script>

<template>
  <UTooltip :content="{ side: 'right' }" :ui="{ content: 'h-auto items-start px-3 py-2 shadow-lg' }">
    <NuxtLink :to="localePath('/about/metrics')" :aria-label="label" class="inline-flex text-muted hover:text-primary">
      <UIcon name="i-lucide-circle-help" class="size-4" />
    </NuxtLink>
    <template #content>
      <div class="max-w-xs space-y-1 text-sm leading-relaxed">
        <slot />
        <p class="text-muted">{{ $t('common.openMetricsHint') }}</p>
      </div>
    </template>
  </UTooltip>
</template>

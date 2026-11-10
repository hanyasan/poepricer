<script setup lang="ts">
/**
 * A single KPI figure. `compact` fits a dense row of tiles, such as the ones
 * above the charts of an item page. `help` adds an explanation icon next to the
 * label; its lines are rendered as separate paragraphs.
 */
withDefaults(defineProps<{ label: string; compact?: boolean; help?: string[] }>(), { compact: false })

/** The body padding of UCard is set per breakpoint, so both are overridden. */
const compactBody = { body: 'p-3 sm:p-3' }
</script>

<template>
  <UCard :ui="compact ? compactBody : undefined">
    <p class="flex items-center gap-1.5 text-muted" :class="compact ? 'text-xs' : 'text-sm'">
      <span>{{ label }}</span>
      <InfoTooltip v-if="help && help.length > 0" :label="help[0]!">
        <p v-for="line in help" :key="line">{{ line }}</p>
      </InfoTooltip>
    </p>
    <p
      class="flex flex-wrap items-baseline gap-x-2 font-semibold"
      :class="compact ? 'mt-0.5 text-lg' : 'mt-1 text-2xl'"
    >
      <span><slot /></span>
      <!-- A remark to the value, set apart from it by the gap. -->
      <span v-if="$slots.note" class="font-normal text-muted" :class="compact ? 'text-xs' : 'text-base'">
        <slot name="note" />
      </span>
    </p>
  </UCard>
</template>

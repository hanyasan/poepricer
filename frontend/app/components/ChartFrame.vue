<script setup lang="ts">
/**
 * The place of a chart. The size comes from the class of the caller, so the skeleton shown in
 * the server render and while the chart code loads has the size of the chart and the page does
 * not jump. The chart code (ECharts) is a separate chunk that loads on the client only.
 */
defineProps<{ option: object }>()

const emit = defineEmits<{ click: [params: { dataIndex: number }] }>()
</script>

<template>
  <div class="w-full">
    <ClientOnly>
      <Suspense>
        <LazyEChart :option="option" @click="emit('click', $event)" />
        <template #fallback>
          <USkeleton class="h-full w-full" />
        </template>
      </Suspense>
      <template #fallback>
        <USkeleton class="h-full w-full" />
      </template>
    </ClientOnly>
  </div>
</template>

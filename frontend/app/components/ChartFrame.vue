<script setup lang="ts">
/**
 * The place of a chart. The size comes from the class of the caller, so the skeleton shown in
 * the server render and while the chart code loads has the size of the chart and the page does
 * not jump. The chart code (ECharts) is a separate chunk that loads on the client only.
 *
 * ECharts measures its box when it mounts, and a chart inside a hidden tab has no size, so the
 * chart itself waits until the box of the frame gets a width and a height.
 */
defineProps<{ option: object }>()

const emit = defineEmits<{ click: [params: { dataIndex: number }] }>()

const frame = useTemplateRef<HTMLElement>('frame')
const sized = ref(false)

let observer: ResizeObserver | undefined

onMounted(() => {
  observer = new ResizeObserver(([entry]) => {
    const box = entry?.contentRect
    sized.value = box !== undefined && box.width > 0 && box.height > 0
  })
  observer.observe(frame.value!)
})

onBeforeUnmount(() => observer?.disconnect())
</script>

<template>
  <div ref="frame" class="w-full">
    <ClientOnly>
      <Suspense v-if="sized">
        <LazyEChart :option="option" @click="emit('click', $event)" />
        <template #fallback>
          <USkeleton class="h-full w-full" />
        </template>
      </Suspense>
      <USkeleton v-else class="h-full w-full" />
      <template #fallback>
        <USkeleton class="h-full w-full" />
      </template>
    </ClientOnly>
  </div>
</template>

<script setup lang="ts">
/**
 * The button that takes the page back to the top. It appears only after the page has been
 * scrolled down, so a short page never shows it; the page itself is the window, because
 * `html` scrolls (`main.css`), not a container.
 */
const VISIBLE_AFTER = 400

const { t } = useI18n()

const isVisible = ref(false)

function update(): void {
  isVisible.value = window.scrollY > VISIBLE_AFTER
}

function scrollToTop(): void {
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches

  window.scrollTo({ top: 0, behavior: reduced ? 'auto' : 'smooth' })
}

onMounted(() => {
  update()
  window.addEventListener('scroll', update, { passive: true })
})

onBeforeUnmount(() => {
  window.removeEventListener('scroll', update)
})
</script>

<template>
  <Transition
    enter-active-class="transition duration-150 ease-out"
    enter-from-class="opacity-0 translate-y-2"
    leave-active-class="transition duration-150 ease-in"
    leave-to-class="opacity-0 translate-y-2"
  >
    <UButton
      v-if="isVisible"
      icon="i-lucide-arrow-up"
      color="neutral"
      variant="solid"
      size="lg"
      :aria-label="t('layout.scrollToTop')"
      :title="t('layout.scrollToTop')"
      class="fixed right-4 bottom-4 z-40 rounded-full shadow-lg"
      @click="scrollToTop"
    />
  </Transition>
</template>

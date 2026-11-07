<script setup lang="ts">
const colorMode = useColorMode()

const isDark = computed({
  get: () => colorMode.value === 'dark',
  set: (value: boolean) => {
    colorMode.preference = value ? 'dark' : 'light'
  },
})
</script>

<template>
  <div class="min-h-screen">
    <header class="border-b border-default">
      <UContainer class="flex h-14 items-center justify-between">
        <NuxtLink to="/" class="font-semibold">PoE Pricer</NuxtLink>
        <nav class="flex items-center gap-4 text-sm">
          <NuxtLink to="/">Лиги</NuxtLink>
          <NuxtLink to="/about/metrics">О метриках</NuxtLink>
          <ClientOnly>
            <UButton
              :icon="isDark ? 'i-lucide-sun' : 'i-lucide-moon'"
              color="neutral"
              variant="ghost"
              aria-label="Сменить тему"
              @click="isDark = !isDark"
            />
            <template #fallback><div class="size-8" /></template>
          </ClientOnly>
        </nav>
      </UContainer>
    </header>
    <UContainer class="py-6">
      <slot />
    </UContainer>
  </div>
</template>

<script setup lang="ts">

const { locale, locales } = useI18n()
const localePath = useLocalePath()
const switchLocalePath = useSwitchLocalePath()
const colorMode = useColorMode()

const otherLocale = computed(() => locales.value.find((item) => item.code !== locale.value)!)

const isDark = computed({
  get: () => colorMode.value === 'dark',
  set: (value: boolean) => {
    colorMode.preference = value ? 'dark' : 'light'
  },
})

</script>

<template>
    <header class="border-b border-default">
      <UContainer class="flex h-14 items-center justify-between">
        <NuxtLink :to="localePath('/')" class="flex items-center gap-2 font-semibold">
          <img src="/logo-mark-light.svg" alt="" class="size-7 dark:hidden">
          <img src="/logo-mark.svg" alt="" class="hidden size-7 dark:block">
          PoE Pricer
        </NuxtLink>
        <nav class="flex items-center gap-4 text-sm">
          <NuxtLink :to="localePath('/')">{{ $t('layout.leagues') }}</NuxtLink>
          <NuxtLink :to="localePath('/about/metrics')">{{ $t('layout.aboutMetrics') }}</NuxtLink>
          <!-- A plain link, so robots and visitors without scripts can follow it too. -->
          <NuxtLink
            :to="switchLocalePath(otherLocale.code)"
            :hreflang="otherLocale.code"
            :lang="otherLocale.code"
            :aria-label="$t('layout.switchLanguage', { language: otherLocale.name })"
            class="font-medium uppercase text-muted hover:text-primary"
          >
            {{ otherLocale.code }}
          </NuxtLink>
          <ClientOnly>
            <UButton
              :icon="isDark ? 'i-lucide-sun' : 'i-lucide-moon'"
              color="neutral"
              variant="ghost"
              :aria-label="$t('layout.toggleTheme')"
              @click="isDark = !isDark"
            />
            <template #fallback><div class="size-8" /></template>
          </ClientOnly>
        </nav>
      </UContainer>
    </header>
</template>

<script setup lang="ts">

const { locale, locales } = useI18n()
const localePath = useLocalePath()
const switchLocalePath = useSwitchLocalePath()

const otherLocale = computed(() => locales.value.find((item) => item.code !== locale.value)!)

</script>

<template>
    <footer class="mt-8 border-t border-default">
      <UContainer class="flex flex-wrap items-center justify-between gap-3 py-6 text-sm text-muted">
        <p>{{ $t('layout.footerNote') }}</p>
        <nav class="flex items-center gap-4">
          <NuxtLink :to="localePath('/')" class="hover:text-primary">{{ $t('layout.leagues') }}</NuxtLink>
          <NuxtLink :to="localePath('/about/metrics')" class="hover:text-primary">{{ $t('layout.aboutMetrics') }}</NuxtLink>
          <NuxtLink :to="switchLocalePath(otherLocale.code)" :hreflang="otherLocale.code" :lang="otherLocale.code" class="hover:text-primary">
            {{ otherLocale.name }}
          </NuxtLink>
        </nav>
      </UContainer>
    </footer>
</template>

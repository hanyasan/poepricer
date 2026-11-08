<script setup lang="ts">
import type { NuxtError } from '#app'

const props = defineProps<{ error: NuxtError }>()

const { t } = useI18n()
const localePath = useLocalePath()

const isNotFound = computed(() => props.error.statusCode === 404)
const heading = computed(() => (isNotFound.value ? t('errorPage.notFoundTitle') : t('errorPage.title')))
const description = computed(() => (isNotFound.value ? t('errorPage.notFoundDescription') : t('errorPage.description')))

// An error page is never a page to index.
useSeoMeta({
  title: heading,
  description,
  robots: 'noindex, follow',
})
</script>

<template>
  <UApp>
    <NuxtLayout>
      <div class="space-y-4 py-12 text-center">
        <p class="text-sm text-muted">{{ error.statusCode }}</p>
        <h1 class="text-2xl font-semibold">{{ heading }}</h1>
        <p class="text-muted">{{ description }}</p>
        <UButton :to="localePath('/')" icon="i-lucide-arrow-left" @click.prevent="clearError({ redirect: localePath('/') })">
          {{ $t('errorPage.home') }}
        </UButton>
      </div>
    </NuxtLayout>
  </UApp>
</template>

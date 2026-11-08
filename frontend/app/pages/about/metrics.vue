<script setup lang="ts">
import { metricsBlocks } from '~/data/aboutMetrics'

const { t, locale } = useI18n()
const localePath = useLocalePath()

const blocks = computed(() => metricsBlocks[locale.value as 'ru' | 'en'])

useSeoMeta({
  title: () => t('seo.metrics.title'),
  description: () => t('seo.metrics.description'),
  ogTitle: () => t('seo.metrics.title'),
  ogDescription: () => t('seo.metrics.description'),
})
</script>

<template>
  <div class="max-w-3xl space-y-10">
    <header class="space-y-2">
      <NuxtLink :to="localePath('/')" class="inline-flex items-center gap-1 text-sm text-muted hover:text-primary">
        <UIcon name="i-lucide-arrow-left" class="size-4" />
        {{ $t('metrics.backHome') }}
      </NuxtLink>
      <h1 class="text-2xl font-semibold">{{ $t('metrics.title') }}</h1>
      <p class="text-muted">{{ $t('metrics.subtitle') }}</p>
    </header>

    <nav :aria-label="$t('metrics.contents')" class="space-y-2">
      <p class="text-sm font-medium">{{ $t('metrics.contents') }}</p>
      <ul class="list-disc space-y-1 pl-5 text-sm">
        <li v-for="block in blocks" :key="block.id">
          <a :href="`#${block.id}`" class="text-primary hover:underline">{{ block.title }}</a>
        </li>
      </ul>
    </nav>

    <section v-for="block in blocks" :id="block.id" :key="block.id" class="scroll-mt-20 space-y-4">
      <h2 class="text-xl font-semibold">{{ block.title }}</h2>

      <p v-for="paragraph in block.paragraphs" :key="paragraph" class="text-sm leading-relaxed text-toned">
        {{ paragraph }}
      </p>

      <dl v-if="block.terms" class="divide-y divide-default rounded-lg border border-default">
        <div v-for="term in block.terms" :key="term.title" class="space-y-1 p-4">
          <dt class="font-medium">{{ term.title }}</dt>
          <dd class="text-sm leading-relaxed text-toned">{{ term.description }}</dd>
        </div>
      </dl>

      <ul v-if="block.list" class="list-disc space-y-1.5 pl-5 text-sm leading-relaxed text-toned">
        <li v-for="item in block.list" :key="item">{{ item }}</li>
      </ul>
    </section>
  </div>
</template>

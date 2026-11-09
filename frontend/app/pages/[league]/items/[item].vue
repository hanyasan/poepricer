<script setup lang="ts">
// The item page has a link back to the items and the league name links to the overview.
definePageMeta({ showLeagueNav: false })

const route = useRoute()
const { t } = useI18n()
const localePath = useLocalePath()

const leagueSlug = computed(() => route.params.league as string)
const itemSlug = computed(() => route.params.item as string)

// The tabs take this answer from the layout instead of asking again.
const summaryRequest = useItemSummary(leagueSlug, itemSlug)

await summaryRequest

const { data: summary, error: summaryError, refresh: refreshSummary } = summaryRequest

provideItemSummary(summary)

const notFound = computed(() => summaryError.value?.statusCode === 404)

if (import.meta.server && notFound.value) {
  setResponseStatus(useRequestEvent()!, 404)
}

const basePath = computed(() => localePath(`/${leagueSlug.value}/items/${itemSlug.value}`))

const tabs = computed(() => [
  // The metrics path is the prefix of the other tab, so it matches exactly only.
  { label: t('item.tabs.metrics'), to: basePath.value, exact: true },
  { label: t('item.tabs.prices'), to: `${basePath.value}/prices` },
])

// A missing item is not a page to index; the tabs set the title and the description of the rest.
useSeoMeta({
  title: () => (notFound.value ? t('item.notFound') : undefined),
  description: () => (notFound.value ? t('item.notFoundDescription') : undefined),
  robots: () => (notFound.value ? 'noindex, follow' : undefined),
})
</script>

<template>
  <div class="space-y-6">
    <NuxtLink :to="localePath(`/${leagueSlug}/items`)" class="inline-flex items-center gap-1 text-sm text-muted hover:text-primary">
      <UIcon name="i-lucide-arrow-left" class="size-4" />
      {{ $t('item.backToItems') }}
    </NuxtLink>

    <UAlert
      v-if="notFound"
      color="neutral"
      variant="subtle"
      icon="i-lucide-search-x"
      :title="$t('item.notFound')"
      :description="$t('item.notFoundDescription')"
    />

    <UAlert
      v-else-if="summaryError"
      color="error"
      variant="subtle"
      icon="i-lucide-triangle-alert"
      :title="$t('item.loadError')"
      :actions="[{ label: $t('common.retry'), onClick: () => refreshSummary() }]"
    />

    <div v-else-if="!summary" class="space-y-6">
      <USkeleton class="h-[34px] w-64" />
      <USkeleton class="h-[51px]" />
      <KpiSkeletonGrid :count="6" />
      <USkeleton class="h-[349px]" />
    </div>

    <template v-else>
      <div class="flex flex-wrap items-center gap-3">
        <h1 class="text-2xl font-semibold">{{ summary.item.name }}</h1>
        <UBadge v-if="summary.item.category" color="neutral" variant="subtle">{{ summary.item.category }}</UBadge>
        <UBadge v-if="summary.current?.isUnusual" color="warning" variant="subtle" icon="i-lucide-activity">
          {{ $t('item.unusualMove') }}
        </UBadge>
        <UBadge v-if="summary.current?.lowData" color="neutral" variant="subtle">{{ $t('common.lowData') }}</UBadge>
      </div>

      <UNavigationMenu :items="tabs" />

      <NuxtPage />
    </template>
  </div>
</template>

<script setup lang="ts">
import Footer from '~/components/base/Footer.vue'
import Header from '~/components/base/Header.vue'

const route = useRoute()
const { t } = useI18n()
const localePath = useLocalePath()

const leagueSlug = computed(() => route.params.league as string)
const itemSlug = computed(() => route.params.item as string)

const basePath = computed(() => localePath(`/${leagueSlug.value}/items/${itemSlug.value}`))
const leaguePath = computed(() => localePath(`/${leagueSlug.value}`))
const pricesPath = computed(() => `${basePath.value}/prices`)
const slug = computed(() => route.params.league as string)
const game = computed(() => league.value?.game ?? gameFromSlug(slug.value))

const isPricesTab = computed(() => route.path.startsWith(pricesPath.value))

// The league must exist before its item does. Its metric currencies go to the store, the same
// selection as on the pages of the league, so the selector shows the same currency here.
await useLeagueCurrencies(leagueSlug)

// The tabs take this answer from the layout instead of asking again, and it survives the
// switch between them.
const summaryRequest = useItemSummary(leagueSlug, itemSlug)

const { league } = await useLeague(slug)

await summaryRequest

const { data: summary, error: summaryError, refresh: refreshSummary } = summaryRequest

provideItemSummary(summary)

const notFound = computed(() => summaryError.value?.statusCode === 404)

if (import.meta.server && notFound.value) {
  setResponseStatus(useRequestEvent()!, 404)
}

// The metrics path is the prefix of the prices one, so the metrics tab matches its path exactly only.
const navItems = computed(() => [
  { label: t('item.tabs.metrics'), to: basePath.value, exact: true },
  { label: t('item.tabs.prices'), to: pricesPath.value, exact: false },
])

function isActive(to: string, exact: boolean): boolean {
  return exact ? route.path === to : route.path.startsWith(to)
}

// A missing item is not a page to index; the tabs set the title and the description of the rest.
useSeoMeta({
  title: () => (notFound.value ? t('item.notFound') : undefined),
  description: () => (notFound.value ? t('item.notFoundDescription') : undefined),
  robots: () => (notFound.value ? 'noindex, follow' : undefined),
})
</script>

<template>
  <div class="min-h-screen">
    <Header />
    <UContainer class="py-6">
      <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div class="flex flex-wrap items-baseline gap-x-3">
            <NuxtLink :to="leaguePath" class="text-lg font-semibold hover:text-primary">{{ league?.name ?? slug }}</NuxtLink>
            <span v-if="game" class="text-sm text-muted">{{ gameTitle(game) }}</span>
          </div>
        </div>

        <NuxtLink
          :to="localePath(`/${leagueSlug}/items`)"
          class="inline-flex items-center gap-1 text-sm text-muted hover:text-primary"
        >
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
          <KpiSkeletonGrid :count="6" compact />
          <USkeleton class="h-[349px]" />
        </div>

        <template v-else>
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-3">
              <h1 class="text-2xl font-semibold">{{ summary.item.name }}</h1>
              <UBadge v-if="summary.item.category" color="neutral" variant="subtle">{{ summary.item.category }}</UBadge>
              <UBadge v-if="summary.current?.isUnusual" color="warning" variant="subtle" icon="i-lucide-activity">
                {{ $t('item.unusualMove') }}
              </UBadge>
              <UBadge v-if="summary.current?.lowData" color="neutral" variant="subtle">{{ $t('common.lowData') }}</UBadge>
            </div>
            <!-- The prices have their own currency and window, the metrics take the currency of the league. -->
            <ItemPriceToolbar v-if="isPricesTab" />
            <LeagueToolbar v-else />
          </div>

          <nav class="flex gap-4 border-b border-default text-sm">
            <NuxtLink
              v-for="link in navItems"
              :key="link.to"
              :to="link.to"
              class="-mb-px border-b-2 px-1 pb-2 font-medium"
              :class="isActive(link.to, link.exact) ? 'border-primary text-highlighted' : 'border-transparent text-muted hover:text-default'"
              :aria-current="isActive(link.to, link.exact) ? 'page' : undefined"
            >
              {{ link.label }}
            </NuxtLink>
          </nav>

          <slot />
        </template>
      </div>
    </UContainer>
    <Footer />
  </div>
</template>

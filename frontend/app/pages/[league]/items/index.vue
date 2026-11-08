<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui'
import type { ItemMetricRow } from '~/types/metrics'
import type { ItemsSortField } from '~/composables/useLeagueItems'

// The table always shows the last bucket, so the period selector of the league header is hidden.
definePageMeta({ usesPeriod: false })

const route = useRoute()
const { t } = useI18n()
const localePath = useLocalePath()
const { leagueName } = useLeagueName()

useBreadcrumbSchema(() => [
  { name: t('layout.leagues'), path: '/' },
  { name: leagueName.value, path: `/${route.params.league}` },
  { name: t('league.items') },
])

useSeoMeta({
  title: () => t('seo.items.title', { league: leagueName.value }),
  description: () => t('seo.items.description', { league: leagueName.value }),
  ogTitle: () => t('seo.items.title', { league: leagueName.value }),
  ogDescription: () => t('seo.items.description', { league: leagueName.value }),
  // A table of a search, a category or a sort order, or its later page, is not a page of its own.
  robots: () => (Object.keys(route.query).length > 0 ? 'noindex, follow' : undefined),
})

const slug = computed(() => route.params.league as string)

// useFetch makes the server wait for the response before it renders the page.
const { data: items, status, error, refresh, search, category, uncategorized, sort, page, setSearch, setCategory, setSort, setPage } =
  useLeagueItems(slug)
const { data: categories } = useItemCategories(slug)

const ALL_CATEGORIES = '__all__'
const NO_CATEGORY = 'uncategorized'

/** Columns the server can sort by, in the order of the table. */
const sortableColumns = computed<{ field: ItemsSortField; label: string }[]>(() => [
  { field: 'name', label: t('common.columns.item') },
  { field: 'price', label: t('common.columns.price') },
  { field: 'logReturn', label: t('common.columns.priceChange') },
  { field: 'residual', label: t('common.columns.residual') },
  { field: 'residualMad', label: t('common.columns.fluctuation') },
  { field: 'beta', label: t('common.columns.beta') },
  { field: 'observations', label: t('common.columns.sample') },
  { field: 'units', label: t('common.columns.tradesPerHour') },
])

const columns = computed<TableColumn<ItemMetricRow>[]>(() => [
  ...sortableColumns.value.map((column) => ({ accessorKey: column.field, header: column.label })),
  { id: 'sparkline', header: t('items.priceWeek') },
])

const categoryItems = computed(() => [
  { label: t('items.allCategories'), value: ALL_CATEGORIES },
  ...(categories.value?.categories ?? []).map((name) => ({ label: name, value: name })),
  ...(categories.value?.hasUncategorized ? [{ label: t('items.noCategory'), value: NO_CATEGORY }] : []),
])

const selectedCategory = computed(() => (uncategorized.value ? NO_CATEGORY : (category.value ?? ALL_CATEGORIES)))

function onCategoryChange(value: string): void {
  setCategory(value === ALL_CATEGORIES ? null : value === NO_CATEGORY ? 'uncategorized' : value)
}

// The search field is typed into freely and reaches the URL, and so the request, after a pause.
const searchText = ref(search.value ?? '')
let searchTimer: ReturnType<typeof setTimeout> | undefined

watch(searchText, (value) => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => setSearch(value), 300)
})

// Back and forward change the search in the URL without the field knowing.
watch(search, (value) => {
  if ((value ?? '') !== searchText.value.trim()) {
    searchText.value = value ?? ''
  }
})

onBeforeUnmount(() => clearTimeout(searchTimer))

function sortIcon(field: ItemsSortField): string {
  if (sort.value.field !== field) return 'i-lucide-arrow-up-down'
  return sort.value.direction === 'asc' ? 'i-lucide-arrow-up' : 'i-lucide-arrow-down'
}

function sortLabel(field: ItemsSortField, label: string): string {
  if (sort.value.field !== field) return t('items.sortBy', { column: label })
  return t(sort.value.direction === 'asc' ? 'items.sortedAscending' : 'items.sortedDescending', { column: label })
}

/** A new column starts descending, the name ascending; a second click flips it. */
function toggleSort(field: ItemsSortField): void {
  if (sort.value.field === field) {
    setSort(field, sort.value.direction === 'asc' ? 'desc' : 'asc')
    return
  }

  setSort(field, field === 'name' ? 'asc' : 'desc')
}

const unusualFirst = computed(() => sort.value.field === 'unusualRatio')

function toggleUnusualFirst(): void {
  if (unusualFirst.value) {
    setSort('units', 'desc')
    return
  }

  setSort('unusualRatio', 'desc')
}

const tableMeta = {
  class: {
    tr: (row: { original: ItemMetricRow }) => (row.original.isUnusual ? 'bg-warning/10' : ''),
  },
}

const hasFilters = computed(() => search.value !== undefined || category.value !== undefined || uncategorized.value)

const emptyText = computed(() => {
  if ((items.value?.meta.total ?? 0) > 0) return t('common.noPage')

  return hasFilters.value ? t('items.noMatches') : t('items.noItems')
})
</script>

<template>
  <div class="space-y-4">
    <h1 class="text-2xl font-semibold">{{ $t('seo.items.title', { league: leagueName }) }}</h1>

    <UAlert
      v-if="error"
      color="error"
      variant="subtle"
      icon="i-lucide-triangle-alert"
      :title="$t('items.loadError')"
      :actions="[{ label: $t('common.retry'), onClick: () => refresh() }]"
    />

    <USkeleton v-else-if="!items" class="h-96" />

    <p v-else-if="items.meta.currency === null" class="text-muted">{{ $t('common.currenciesNotConfigured') }}</p>

    <p v-else-if="items.meta.lastBucket === null" class="text-muted">{{ $t('common.metricsNotCalculated') }}</p>

    <template v-else>
      <div class="flex flex-wrap items-center gap-3">
        <UInput v-model="searchText" icon="i-lucide-search" :placeholder="$t('items.searchPlaceholder')" class="w-64" maxlength="100" />
        <USelect
          :model-value="selectedCategory"
          :items="categoryItems"
          class="w-56"
          :aria-label="$t('items.category')"
          @update:model-value="onCategoryChange"
        />
        <UButton
          icon="i-lucide-activity"
          color="warning"
          :variant="unusualFirst ? 'solid' : 'outline'"
          :aria-pressed="unusualFirst"
          @click="toggleUnusualFirst"
        >
          {{ $t('items.unusualFirst') }}
        </UButton>
      </div>

      <p class="flex items-center gap-1.5 text-sm text-muted">
        {{ $t('items.found', { total: items.meta.total }) }}
        <UTooltip :content="{ side: 'right' }" :ui="{ content: 'h-auto items-start px-3 py-2 shadow-lg' }">
          <NuxtLink :to="localePath('/about/metrics')" :aria-label="$t('items.help.label')" class="inline-flex hover:text-primary">
            <UIcon name="i-lucide-circle-help" class="size-4" />
          </NuxtLink>
          <template #content>
            <div class="max-w-xs space-y-1 text-sm leading-relaxed">
              <p>{{ $t('items.help.lineOne') }}</p>
              <p>{{ $t('items.help.lineTwo') }}</p>
              <p>{{ $t('items.help.lineThree') }}</p>
              <p class="text-muted">{{ $t('common.openMetricsHint') }}</p>
            </div>
          </template>
        </UTooltip>
      </p>

      <UTable
        :data="items.data"
        :columns="columns"
        :ui="{ th: 'align-bottom' }"
        :meta="tableMeta"
        :loading="status === 'pending'"
        :empty="emptyText"
      >
        <template v-for="column in sortableColumns" #[`${column.field}-header`]>
          <button
            type="button"
            class="inline-flex items-end gap-1 text-left font-semibold hover:text-primary"
            :aria-label="sortLabel(column.field, column.label)"
            @click="toggleSort(column.field)"
          >
            {{ column.label }}
            <UIcon :name="sortIcon(column.field)" class="mb-0.5 size-4 shrink-0" :class="sort.field === column.field ? 'text-primary' : 'text-muted'" />
          </button>
        </template>

        <template #name-cell="{ row }">
          <div class="flex items-center gap-2">
            <NuxtLink :to="localePath(`/${slug}/items/${row.original.slug}`)" class="hover:text-primary">{{ row.original.name }}</NuxtLink>
            <UBadge v-if="row.original.lowData" color="neutral" variant="subtle" size="sm">{{ $t('common.lowData') }}</UBadge>
          </div>
        </template>
        <template #price-cell="{ row }">{{ orDash(row.original.price, formatPrice) }}</template>
        <template #logReturn-cell="{ row }">
          <span :class="returnClass(row.original.logReturn)">{{ orDash(row.original.logReturn, formatReturn) }}</span>
        </template>
        <template #residual-cell="{ row }">
          <span :class="returnClass(row.original.residual)">{{ orDash(row.original.residual, formatReturn) }}</span>
        </template>
        <template #residualMad-cell="{ row }">{{ orDash(row.original.residualMad, formatPercent) }}</template>
        <template #beta-cell="{ row }">{{ orDash(row.original.beta, (value) => value.toFixed(2)) }}</template>
        <template #observations-cell="{ row }">
          <span :class="row.original.lowData ? 'text-muted' : ''">{{ row.original.observations }}</span>
        </template>
        <template #units-cell="{ row }">
          <span :class="weightClass(row.original.returnWeight)">{{ formatUnits(row.original.units) }}</span>
        </template>
        <template #sparkline-cell="{ row }">
          <ItemSparkline :points="row.original.sparkline" />
        </template>
      </UTable>

      <div v-if="items.meta.lastPage > 1" class="flex justify-center">
        <UPagination
          :page="page"
          :total="items.meta.total"
          :items-per-page="items.meta.perPage"
          :sibling-count="1"
          @update:page="setPage"
        />
      </div>
    </template>
  </div>
</template>

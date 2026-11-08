<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui'
import type { ItemBucketRow } from '~/types/metrics'

// The item page has a link back to the items and the league name links to the overview.
definePageMeta({ showLeagueNav: false })

const route = useRoute()

const leagueSlug = computed(() => route.params.league as string)
const itemSlug = computed(() => route.params.item as string)
const { currencyName, period } = storeToRefs(useFiltersStore())

// The three requests start together, so the server renders the page after the slowest one.
const summaryRequest = useItemSummary(leagueSlug, itemSlug)
const historyRequest = useItemHistory(leagueSlug, itemSlug)
const bucketsRequest = useItemBuckets(leagueSlug, itemSlug)

await Promise.all([summaryRequest, historyRequest, bucketsRequest])

const { data: summary, error: summaryError, refresh: refreshSummary } = summaryRequest
const { data: history, error: historyError, refresh: refreshHistory } = historyRequest
const { data: buckets, status: bucketsStatus, error: bucketsError, refresh: refreshBuckets, page, setPage } = bucketsRequest

const notFound = computed(() => summaryError.value?.statusCode === 404)

if (import.meta.server && notFound.value) {
  setResponseStatus(useRequestEvent()!, 404)
}

const periodLabel = computed(() => ({ '24h': 'за 24 часа', '7d': 'за 7 дней', league: 'за всю лигу' })[period.value])

const columns: TableColumn<ItemBucketRow>[] = [
  { accessorKey: 'bucket', header: 'Корзина' },
  { accessorKey: 'price', header: 'Цена' },
  { accessorKey: 'logReturn', header: 'Δ цены' },
  { accessorKey: 'residual', header: 'Остаток' },
  { accessorKey: 'residualMad', header: 'Колебание' },
  { accessorKey: 'observations', header: 'Выборка' },
  { accessorKey: 'units', header: 'Сделок/ч' },
]

const emptyText = computed(() =>
  (buckets.value?.meta.total ?? 0) > 0 ? 'Такой страницы нет.' : 'За этот период нет значений.',
)
</script>

<template>
  <div class="space-y-6">
    <NuxtLink :to="`/${leagueSlug}/items`" class="inline-flex items-center gap-1 text-sm text-muted hover:text-primary">
      <UIcon name="i-lucide-arrow-left" class="size-4" />
      Все предметы
    </NuxtLink>

    <UAlert
      v-if="notFound"
      color="neutral"
      variant="subtle"
      icon="i-lucide-search-x"
      title="Предмет не найден"
      description="В этой лиге нет такого предмета."
    />

    <UAlert
      v-else-if="summaryError"
      color="error"
      variant="subtle"
      icon="i-lucide-triangle-alert"
      title="Не удалось загрузить предмет"
      :actions="[{ label: 'Повторить', onClick: () => refreshSummary() }]"
    />

    <USkeleton v-else-if="!summary" class="h-96" />

    <template v-else>
      <div class="flex flex-wrap items-center gap-3">
        <h2 class="text-xl font-semibold">{{ summary.item.name }}</h2>
        <UBadge v-if="summary.item.category" color="neutral" variant="subtle">{{ summary.item.category }}</UBadge>
        <UBadge v-if="summary.current?.isUnusual" color="warning" variant="subtle" icon="i-lucide-activity">
          необычное движение
        </UBadge>
        <UBadge v-if="summary.current?.lowData" color="neutral" variant="subtle">мало данных</UBadge>
      </div>

      <p v-if="summary.currency === null" class="text-muted">Для этой лиги не настроены валюты метрик.</p>

      <p v-else-if="summary.lastBucket === null" class="text-muted">Метрики для этой валюты ещё не рассчитаны.</p>

      <template v-else>
        <p v-if="summary.current === null" class="text-muted">
          В последней корзине (<LocalDateTime :value="summary.lastBucket" />) у предмета нет значений. Ниже показаны данные
          за выбранный период.
        </p>

        <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <KpiTile :label="`Цена, ${currencyName}`">{{ formatPrice(summary.current.price) }}</KpiTile>
          <KpiTile label="Δ цены за последнюю корзину">
            <span :class="returnClass(summary.current.logReturn)">{{ orDash(summary.current.logReturn, formatReturn) }}</span>
          </KpiTile>
          <KpiTile label="Остаток">
            <span :class="returnClass(summary.current.residual)">{{ orDash(summary.current.residual, formatReturn) }}</span>
            <span v-if="summary.current.residualMad !== null" class="text-base font-normal text-muted">
              при колебании {{ formatPercent(summary.current.residualMad) }}
            </span>
          </KpiTile>
          <KpiTile label="Сделок в час">
            <span :class="weightClass(summary.current.returnWeight)">{{ formatUnits(summary.current.units) }}</span>
          </KpiTile>
          <KpiTile label="Выборка">
            <span :class="summary.current.lowData ? 'text-muted' : ''">{{ summary.current.observations }}</span>
          </KpiTile>
          <KpiTile label="Корзина"><LocalDateTime :value="summary.lastBucket" /></KpiTile>
        </div>

        <UAlert
          v-if="historyError"
          color="error"
          variant="subtle"
          icon="i-lucide-triangle-alert"
          title="Не удалось загрузить графики"
          :actions="[{ label: 'Повторить', onClick: () => refreshHistory() }]"
        />

        <p v-else-if="history && history.series.length === 0" class="text-muted">За выбранный период у предмета нет значений.</p>

        <template v-else-if="history">
          <section class="space-y-3">
            <h3 class="text-lg font-medium">Цена {{ periodLabel }}</h3>
            <ItemPriceChart :points="history.series" />
          </section>

          <section class="space-y-3">
            <h3 class="flex items-center gap-1.5 text-lg font-medium">
              Остаток {{ periodLabel }}
              <InfoTooltip label="Что такое остаток и полоса на графике">
                <p>Остаток — движение предмета за вычетом рыночного</p>
                <p>Точка за пределами полосы, двух обычных колебаний предмета, — необычное движение</p>
              </InfoTooltip>
            </h3>
            <ItemResidualChart :points="history.series" />
          </section>
        </template>

        <section class="space-y-3">
          <h3 class="text-lg font-medium">Бета</h3>
          <p v-if="summary.beta === null" class="text-muted">Бета этого предмета ещё не рассчитана.</p>
          <template v-else>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
              <KpiTile label="β">{{ summary.beta.beta.toFixed(2) }}</KpiTile>
              <KpiTile label="Сырая β">{{ summary.beta.rawBeta.toFixed(2) }}</KpiTile>
              <KpiTile label="R²">{{ orDash(summary.beta.rSquared, (value) => value.toFixed(2)) }}</KpiTile>
              <KpiTile label="Рассчитана"><LocalDateTime :value="summary.beta.calculatedAt" /></KpiTile>
            </div>
            <p class="text-sm text-muted">
              Показывает, как сильно предмет следует за рынком: 1,5 означает рост на 15% при росте рынка на 10%. При R² около нуля рынок
              почти ничего не объясняет.
            </p>
            <ItemBetaChart v-if="history && history.betaHistory.length >= 2" :betas="history.betaHistory" />
            <p v-else-if="history" class="text-sm text-muted">
              За выбранный период бета пересчитывалась {{ history.betaHistory.length }} раз, для графика нужно хотя бы два пересчёта.
            </p>
          </template>
        </section>

        <section class="space-y-3">
          <h3 class="text-lg font-medium">Значения по корзинам</h3>

          <UAlert
            v-if="bucketsError"
            color="error"
            variant="subtle"
            icon="i-lucide-triangle-alert"
            title="Не удалось загрузить таблицу"
            :actions="[{ label: 'Повторить', onClick: () => refreshBuckets() }]"
          />

          <template v-else-if="buckets">
            <UTable
              :data="buckets.data"
              :columns="columns"
              :ui="{ th: 'align-bottom' }"
              :loading="bucketsStatus === 'pending'"
              :empty="emptyText"
            >
              <template #bucket-cell="{ row }"><LocalDateTime :value="row.original.bucket" /></template>
              <template #price-cell="{ row }">{{ formatPrice(row.original.price) }}</template>
              <template #logReturn-cell="{ row }">
                <span :class="returnClass(row.original.logReturn)">{{ orDash(row.original.logReturn, formatReturn) }}</span>
              </template>
              <template #residual-cell="{ row }">
                <span :class="returnClass(row.original.residual)">{{ orDash(row.original.residual, formatReturn) }}</span>
              </template>
              <template #residualMad-cell="{ row }">{{ orDash(row.original.residualMad, formatPercent) }}</template>
              <template #units-cell="{ row }">
                <span :class="weightClass(row.original.returnWeight)">{{ formatUnits(row.original.units) }}</span>
              </template>
            </UTable>

            <div v-if="buckets.meta.lastPage > 1" class="flex justify-center">
              <UPagination
                :page="page"
                :total="buckets.meta.total"
                :items-per-page="buckets.meta.perPage"
                :sibling-count="1"
                @update:page="setPage"
              />
            </div>
          </template>
        </section>
      </template>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui'
import type { UnusualMove } from '~/types/metrics'

const route = useRoute()

const slug = computed(() => route.params.league as string)
const { currencyName } = storeToRefs(useFiltersStore())
const { data: overview, status, refresh } = await useLeagueOverview(slug)

const columns: TableColumn<UnusualMove>[] = [
  { accessorKey: 'name', header: 'Предмет' },
  { accessorKey: 'price', header: 'Цена' },
  { accessorKey: 'logReturn', header: 'Δ цены' },
  { accessorKey: 'residual', header: 'Остаток' },
  { accessorKey: 'ratio', header: 'Кратность' },
  { accessorKey: 'beta', header: 'β' },
  { accessorKey: 'observations', header: 'Выборка' },
  { accessorKey: 'units', header: 'Сделок/ч' },
]
</script>

<template>
  <div class="space-y-6">
    <UAlert
      v-if="status === 'error'"
      color="error"
      variant="subtle"
      icon="i-lucide-triangle-alert"
      title="Не удалось загрузить обзор лиги"
      :actions="[{ label: 'Повторить', onClick: () => refresh() }]"
    />

    <div v-else-if="!overview" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <USkeleton v-for="index in 4" :key="index" class="h-24" />
    </div>

    <p v-else-if="overview.currency === null" class="text-muted">Для этой лиги не настроены валюты метрик.</p>

    <template v-else>
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <KpiTile label="Доходность последней корзины">
          <span v-if="overview.kpi.marketReturnLastBucket !== null" :class="returnClass(overview.kpi.marketReturnLastBucket)">
            {{ formatReturn(overview.kpi.marketReturnLastBucket) }}
          </span>
          <span v-else class="text-muted">—</span>
        </KpiTile>
        <KpiTile label="Доходность рынка, 7д">
          <span v-if="overview.kpi.marketReturn7d !== null" :class="returnClass(overview.kpi.marketReturn7d)">
            {{ formatReturn(overview.kpi.marketReturn7d) }}
          </span>
          <span v-else class="text-muted">—</span>
        </KpiTile>
        <KpiTile label="Предметов в расчёте рынка">
          {{ overview.kpi.basketSize }}
          <span class="text-base font-normal text-muted">из {{ overview.kpi.basketTotal }}</span>
        </KpiTile>
        <KpiTile label="Обновлено"><LocalDateTime :value="overview.kpi.lastSnapshotAt" empty="нет снимков" /></KpiTile>
      </div>

      <p v-if="overview.lastBucket === null" class="text-muted">Метрики для этой валюты ещё не рассчитаны.</p>

      <template v-else>
        <section class="space-y-3">
          <h2 class="text-lg font-medium">Доходность рынка</h2>
          <LineChart :points="overview.marketReturnSeries" />
        </section>

        <section class="space-y-3">
          <h2 class="flex items-center gap-1.5 text-lg font-medium">
            Необычные движения
            <InfoTooltip label="Что такое необычные движения">
              <p>Предметы, чей остаток в последней корзине больше двух обычных для них колебаний</p>
              <p>Цены в {{ currencyName }}</p>
              <p>Серым выделено число сделок у предметов с малым объёмом: их доходности менее надёжны</p>
            </InfoTooltip>
          </h2>
          <p v-if="overview.unusualMoves.length === 0" class="text-muted">Сейчас таких предметов нет.</p>
          <UTable v-else :data="overview.unusualMoves" :columns="columns" :ui="{ th: 'align-bottom' }">
            <template #name-cell="{ row }">
              <NuxtLink :to="`/${slug}/items/${row.original.slug}`" class="hover:text-primary">{{ row.original.name }}</NuxtLink>
            </template>
            <template #price-cell="{ row }">{{ formatPrice(row.original.price) }}</template>
            <template #logReturn-cell="{ row }">
              <span :class="returnClass(row.original.logReturn)">{{ formatReturn(row.original.logReturn) }}</span>
            </template>
            <template #residual-cell="{ row }">
              <span :class="returnClass(row.original.residual)">{{ formatReturn(row.original.residual) }}</span>
            </template>
            <template #ratio-cell="{ row }">{{ row.original.ratio.toFixed(1) }}×</template>
            <template #beta-cell="{ row }">{{ row.original.beta.toFixed(2) }}</template>
            <template #units-cell="{ row }">
              <span :class="weightClass(row.original.returnWeight)">{{ formatUnits(row.original.units) }}</span>
            </template>
          </UTable>
        </section>

        <section class="grid gap-4 lg:grid-cols-2">
          <ItemMoveList title="Топ роста за последнюю корзину" :moves="overview.topGainers" :league-slug="slug" />
          <ItemMoveList title="Топ падения за последнюю корзину" :moves="overview.topLosers" :league-slug="slug" />
        </section>
      </template>
    </template>
  </div>
</template>

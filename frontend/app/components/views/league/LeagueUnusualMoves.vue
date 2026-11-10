<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui'
import type { UnusualMove } from '~/types/metrics'

defineProps<{
  moves: UnusualMove[]
  currencyName: string
  leagueSlug: string
}>()

const { t } = useI18n()
const localePath = useLocalePath()

const columns = computed<TableColumn<UnusualMove>[]>(() => [
  { accessorKey: 'name', header: t('common.columns.item') },
  { accessorKey: 'price', header: t('common.columns.price') },
  { accessorKey: 'logReturn', header: t('common.columns.priceChange') },
  { accessorKey: 'residual', header: t('common.columns.residual') },
  { accessorKey: 'ratio', header: t('common.columns.ratio') },
  { accessorKey: 'beta', header: t('common.columns.beta') },
  { accessorKey: 'observations', header: t('common.columns.sample') },
  { accessorKey: 'units', header: t('common.columns.tradesPerHour') },
])
</script>

<template>
  <section class="space-y-3">
    <h2 class="flex items-center gap-1.5 text-lg font-medium">
      {{ $t('overview.unusualMoves') }}
      <InfoTooltip :label="$t('overview.unusualHelp.label')">
        <p>{{ $t('overview.unusualHelp.lineOne') }}</p>
        <p>{{ $t('overview.unusualHelp.lineTwo', { currency: currencyName }) }}</p>
        <p>{{ $t('overview.unusualHelp.lineThree') }}</p>
      </InfoTooltip>
    </h2>
    <p v-if="moves.length === 0" class="text-muted">{{ $t('overview.noUnusualMoves') }}</p>
    <UTable v-else :data="moves" :columns="columns" :ui="{ th: 'align-bottom' }">
      <template #name-cell="{ row }">
        <NuxtLink :to="localePath(`/${leagueSlug}/items/${row.original.slug}`)" class="hover:text-primary">{{ row.original.name }}</NuxtLink>
      </template>
      <template #price-cell="{ row }">{{ formatPrice(row.original.price) }}</template>
      <template #logReturn-cell="{ row }">
        <span :class="returnClass(row.original.logReturn)">{{ formatReturn(row.original.logReturn) }}</span>
      </template>
      <template #residual-cell="{ row }">
        <span :class="returnClass(row.original.residual)">{{ formatReturn(row.original.residual) }}</span>
      </template>
      <template #ratio-cell="{ row }">{{ formatRatio(row.original.ratio) }}</template>
      <template #beta-cell="{ row }">{{ formatCoefficient(row.original.beta) }}</template>
      <template #units-cell="{ row }">
        <span :class="weightClass(row.original.returnWeight)">{{ formatUnits(row.original.units) }}</span>
      </template>
    </UTable>
  </section>
</template>

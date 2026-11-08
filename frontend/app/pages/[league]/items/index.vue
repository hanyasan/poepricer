<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui'
import type { ItemMetricRow } from '~/types/metrics'

const { items } = useItemMetrics()

const columns: TableColumn<ItemMetricRow>[] = [
  { accessorKey: 'name', header: 'Предмет' },
  { accessorKey: 'price', header: 'Цена' },
  { accessorKey: 'logReturn', header: 'log_return' },
  { accessorKey: 'residual', header: 'residual' },
  { accessorKey: 'residualMad', header: 'residual_mad' },
  { accessorKey: 'beta', header: 'β' },
  { accessorKey: 'observations', header: 'observations' },
  { accessorKey: 'units', header: 'Сделок/ч' },
]
</script>

<template>
  <UTable :data="items" :columns="columns">
    <template #units-cell="{ row }">
      <span :class="weightClass(row.original.returnWeight)">{{ formatUnits(row.original.units) }}</span>
    </template>
  </UTable>
</template>

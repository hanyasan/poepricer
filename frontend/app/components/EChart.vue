<script setup lang="ts">
import { use } from 'echarts/core'
import { BarChart, LineChart } from 'echarts/charts'
import { GridComponent, MarkLineComponent, TooltipComponent } from 'echarts/components'
import { CanvasRenderer } from 'echarts/renderers'
import VChart from 'vue-echarts'
import type { EChartsOption } from 'echarts'

use([LineChart, BarChart, GridComponent, MarkLineComponent, TooltipComponent, CanvasRenderer])

defineProps<{ option: object }>()

const emit = defineEmits<{ click: [params: { dataIndex: number }] }>()
</script>

<template>
  <VChart :option="(option as EChartsOption)" autoresize @click="emit('click', $event)" />
</template>

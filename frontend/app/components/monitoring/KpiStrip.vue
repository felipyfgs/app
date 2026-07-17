<script setup lang="ts">
/**
 * Contadores compactos da carteira fiscal.
 * As tabs substituem os cards e continuam acionando o filtro server-side.
 */
import type { FiscalKpiKey, FiscalModuleCounters } from '~/types/fiscal-modules'
import { fiscalKpiSituationFilter, fiscalSituationToKpiKey } from '~/types/fiscal-modules'

const props = withDefaults(defineProps<{
  total?: number | null
  totalClients?: number | null
  counters?: FiscalModuleCounters | null
  loading?: boolean
  activeKey?: FiscalKpiKey | null
  activeSituation?: string | null
  showError?: boolean
}>(), {
  showError: true
})

const emit = defineEmits<{
  select: [key: FiscalKpiKey, situation: string | null]
}>()

const resolvedTotal = computed(() => {
  if (props.total != null && Number.isFinite(Number(props.total))) {
    return Number(props.total)
  }
  if (props.totalClients != null && Number.isFinite(Number(props.totalClients))) {
    return Number(props.totalClients)
  }
  return 0
})

const resolvedActiveKey = computed<FiscalKpiKey>(() => {
  if (props.activeKey) return props.activeKey
  if (props.activeSituation != null && String(props.activeSituation).length > 0) {
    return fiscalSituationToKpiKey(props.activeSituation)
  }
  return 'total'
})

type CounterTab = {
  label: string
  value: FiscalKpiKey
  badge: number | string
}

const items = computed((): CounterTab[] => {
  const c = props.counters
  const loadingPlaceholder = props.loading && !c
  const num = (n: number | undefined) => (loadingPlaceholder ? '…' : (n ?? 0))

  const list: CounterTab[] = [
    {
      value: 'total',
      label: 'Total',
      badge: loadingPlaceholder ? '…' : resolvedTotal.value
    },
    {
      value: 'up_to_date',
      label: 'Em dia',
      badge: num(c?.up_to_date)
    },
    {
      value: 'processing',
      label: 'Processando',
      badge: num(c?.processing)
    },
    {
      value: 'pending',
      label: 'Pendências',
      badge: num(c?.pending)
    },
    {
      value: 'attention',
      label: 'Atenção',
      badge: num(c?.attention)
    }
  ]

  if (props.showError) {
    list.push({
      value: 'error',
      label: 'Erro',
      badge: num(c?.error)
    })
  }

  return list
})

function onSelect(key: string | number) {
  const k = String(key) as FiscalKpiKey
  emit('select', k, fiscalKpiSituationFilter(k))
}
</script>

<template>
  <div
    data-testid="fiscal-kpi-strip"
    class="flex min-w-0 items-center gap-2"
  >
    <div class="min-w-0 flex-1 overflow-x-auto">
      <UTabs
        :model-value="resolvedActiveKey"
        :items="items"
        :content="false"
        activation-mode="automatic"
        size="md"
        color="primary"
        variant="pill"
        :ui="{
          root: 'w-max min-w-full',
          list: 'w-max min-w-full justify-start border border-default bg-elevated/60 shadow-xs',
          trigger: 'shrink-0 data-[state=active]:text-highlighted',
          indicator: 'bg-default ring-1 ring-default'
        }"
        aria-label="Filtrar por situação"
        @update:model-value="onSelect"
      />
    </div>
  </div>
</template>

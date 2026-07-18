<script setup lang="ts">
/**
 * Contadores compactos da carteira fiscal.
 * Faixa operacional fixa: Total · Em dia · Processando · Pendências · Atenção
 * (zeros contam). Estados secundários (Bloqueado, Erro, …) só com contagem > 0.
 *
 * Mobile: tabs `sm`, scroll horizontal com overscroll touch (sem quebrar a linha).
 */
import { breakpointsTailwind, useBreakpoints } from '@vueuse/core'
import type { FiscalKpiKey, FiscalModuleCounters } from '~/types/fiscal-modules'
import {
  FISCAL_COUNTER_KPI_KEYS,
  fiscalKpiSituationFilter,
  fiscalSituationToKpiKey,
  normalizeFiscalModuleCounters
} from '~/types/fiscal-modules'
import { fiscalStatusMeta } from '~/utils/fiscal-status'

/** Sempre visíveis (como no DCTFWeb de referência), inclusive em zero. */
const PRIMARY_KPI_KEYS = [
  'up_to_date',
  'processing',
  'pending',
  'attention'
] as const satisfies readonly Exclude<FiscalKpiKey, 'total'>[]

const PRIMARY_KPI_SET = new Set<string>(PRIMARY_KPI_KEYS)

/** Rótulos da faixa (Pendências no plural, alinhado à UX de referência). */
const PRIMARY_KPI_LABELS: Record<(typeof PRIMARY_KPI_KEYS)[number], string> = {
  up_to_date: 'Em dia',
  processing: 'Processando',
  pending: 'Pendências',
  attention: 'Atenção'
}

const props = withDefaults(defineProps<{
  total?: number | null
  totalClients?: number | null
  counters?: FiscalModuleCounters | Partial<FiscalModuleCounters> | null
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

const breakpoints = useBreakpoints(breakpointsTailwind)
const isNarrow = breakpoints.smaller('sm')
const tabSize = computed(() => (isNarrow.value ? 'sm' : 'md'))

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

const normalizedCounters = computed(() => normalizeFiscalModuleCounters(props.counters))

type CounterTab = {
  label: string
  value: FiscalKpiKey
  badge: number | string
}

function kpiLabel(key: Exclude<FiscalKpiKey, 'total'>): string {
  if (key in PRIMARY_KPI_LABELS) {
    return PRIMARY_KPI_LABELS[key as (typeof PRIMARY_KPI_KEYS)[number]]
  }
  const situation = fiscalKpiSituationFilter(key)
  return fiscalStatusMeta(situation).label
}

const items = computed((): CounterTab[] => {
  const c = normalizedCounters.value
  const loadingPlaceholder = props.loading && !props.counters
  const active = resolvedActiveKey.value

  const list: CounterTab[] = [
    {
      value: 'total',
      label: 'Total',
      badge: loadingPlaceholder ? '…' : resolvedTotal.value
    }
  ]

  for (const key of FISCAL_COUNTER_KPI_KEYS) {
    if (key === 'error' && !props.showError) continue
    const count = c[key]
    const isPrimary = PRIMARY_KPI_SET.has(key)
    // Primários sempre; secundários só com contagem ou se estiverem ativos.
    if (!isPrimary && count <= 0 && active !== key) continue

    list.push({
      value: key,
      label: kpiLabel(key),
      badge: loadingPlaceholder ? '…' : count
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
    <div
      class="min-w-0 flex-1 overflow-x-auto overscroll-x-contain [-webkit-overflow-scrolling:touch] touch-pan-x"
    >
      <UTabs
        :model-value="resolvedActiveKey"
        :items="items"
        :content="false"
        activation-mode="automatic"
        :size="tabSize"
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

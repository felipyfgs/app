<script setup lang="ts">
/**
 * Contadores compactos da carteira fiscal.
 * Catálogo: Total sempre; estados com contagem > 0; estado ativo permanece mesmo em zero.
 */
import type { FiscalKpiKey, FiscalModuleCounters } from '~/types/fiscal-modules'
import {
  FISCAL_COUNTER_KPI_KEYS,
  fiscalKpiSituationFilter,
  fiscalSituationToKpiKey,
  normalizeFiscalModuleCounters
} from '~/types/fiscal-modules'
import { fiscalStatusMeta } from '~/utils/fiscal-status'

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
    // Positivos + ativo em zero (mesmo sem contagem).
    if (count > 0 || active === key) {
      const situation = fiscalKpiSituationFilter(key)
      const meta = fiscalStatusMeta(situation)
      list.push({
        value: key,
        label: meta.label,
        badge: loadingPlaceholder ? '…' : count
      })
    }
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

<script setup lang="ts">
/**
 * Faixa de KPIs da carteira fiscal — derivado de HomeStats / NotesInsightsBar.
 * Total, Em dia, Processando, Pendências e Atenção (e Erro) acionáveis.
 *
 * API unificada (aliases aceitos para páginas legadas):
 * - total | totalClients
 * - activeKey | activeSituation (código de situação da URL)
 * - @select(key, situation) — situation null limpa o filtro
 */
import type { FiscalKpiKey, FiscalModuleCounters } from '~/types/fiscal-modules'
import { fiscalKpiSituationFilter, fiscalSituationToKpiKey } from '~/types/fiscal-modules'

const props = withDefaults(defineProps<{
  /** Total de clientes (canônico). */
  total?: number | null
  /** Alias de `total` usado pelas páginas de monitoramento. */
  totalClients?: number | null
  counters?: FiscalModuleCounters | null
  loading?: boolean
  /** KPI ativo (canônico). */
  activeKey?: FiscalKpiKey | null
  /** Alias: situação da URL (`PENDING`, `all`, …) mapeada para KPI. */
  activeSituation?: string | null
  /** Exibe card de Erro (default true). */
  showError?: boolean
}>(), {
  showError: true
})

const emit = defineEmits<{
  /** key + situation filter (null limpa). Páginas usam o 2º argumento. */
  select: [key: FiscalKpiKey, situation: string | null]
}>()

type Chip = {
  key: FiscalKpiKey
  title: string
  icon: string
  value: number | string
  tone?: 'default' | 'warning' | 'error' | 'success' | 'info'
}

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

const chips = computed((): Chip[] => {
  const c = props.counters
  const loadingPlaceholder = props.loading && !c
  const num = (n: number | undefined) => (loadingPlaceholder ? '…' : (n ?? 0))

  const list: Chip[] = [
    {
      key: 'total',
      title: 'Total',
      icon: 'i-lucide-users',
      value: loadingPlaceholder ? '…' : resolvedTotal.value
    },
    {
      key: 'up_to_date',
      title: 'Em dia',
      icon: 'i-lucide-circle-check',
      value: num(c?.up_to_date),
      tone: 'success'
    },
    {
      key: 'processing',
      title: 'Processando',
      icon: 'i-lucide-loader-circle',
      value: num(c?.processing),
      tone: 'info'
    },
    {
      key: 'pending',
      title: 'Pendências',
      icon: 'i-lucide-circle-dashed',
      value: num(c?.pending),
      tone: 'warning'
    },
    {
      key: 'attention',
      title: 'Atenção',
      icon: 'i-lucide-triangle-alert',
      value: num(c?.attention),
      tone: 'warning'
    }
  ]

  if (props.showError) {
    list.push({
      key: 'error',
      title: 'Erro',
      icon: 'i-lucide-circle-x',
      value: num(c?.error),
      tone: 'error'
    })
  }

  return list
})

function leadingClass(tone?: Chip['tone'], active?: boolean) {
  if (active) return 'p-2.5 rounded-full bg-primary/10 ring ring-inset ring-primary/25 flex-col'
  if (tone === 'error') return 'p-2.5 rounded-full bg-error/10 ring ring-inset ring-error/25 flex-col'
  if (tone === 'warning') return 'p-2.5 rounded-full bg-warning/10 ring ring-inset ring-warning/25 flex-col'
  if (tone === 'success') return 'p-2.5 rounded-full bg-success/10 ring ring-inset ring-success/25 flex-col'
  if (tone === 'info') return 'p-2.5 rounded-full bg-info/10 ring ring-inset ring-info/25 flex-col'
  return 'p-2.5 rounded-full bg-primary/10 ring ring-inset ring-primary/25 flex-col'
}

const gridClass = computed(() =>
  props.showError
    ? 'lg:grid-cols-6 gap-3 sm:gap-4 lg:gap-px'
    : 'lg:grid-cols-5 gap-3 sm:gap-4 lg:gap-px'
)

function onSelect(key: FiscalKpiKey) {
  emit('select', key, fiscalKpiSituationFilter(key))
}
</script>

<template>
  <div
    data-testid="fiscal-kpi-strip"
    class="w-full"
  >
    <div class="mb-2 flex items-center justify-between gap-2">
      <p class="text-xs font-medium uppercase tracking-wide text-muted">
        Situação da carteira
      </p>
      <p
        v-if="loading"
        class="text-xs text-dimmed"
      >
        Atualizando…
      </p>
    </div>
    <UPageGrid :class="gridClass">
      <UPageCard
        v-for="chip in chips"
        :key="chip.key"
        :icon="chip.icon"
        :title="chip.title"
        variant="subtle"
        :highlight="resolvedActiveKey === chip.key"
        highlight-color="primary"
        :ui="{
          container: 'gap-y-1.5',
          wrapper: 'items-start',
          leading: leadingClass(chip.tone, resolvedActiveKey === chip.key),
          title: 'font-normal text-muted text-xs uppercase'
        }"
        class="lg:rounded-none first:rounded-l-lg last:rounded-r-lg hover:z-1 cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
        role="button"
        tabindex="0"
        :aria-pressed="resolvedActiveKey === chip.key"
        :aria-label="`Filtrar carteira: ${chip.title}`"
        :data-testid="`fiscal-kpi-${chip.key}`"
        @click="onSelect(chip.key)"
        @keydown.enter.prevent="onSelect(chip.key)"
        @keydown.space.prevent="onSelect(chip.key)"
      >
        <div class="flex items-center gap-2">
          <span class="text-2xl font-semibold tabular-nums text-highlighted">
            {{ chip.value }}
          </span>
        </div>
      </UPageCard>
    </UPageGrid>
  </div>
</template>

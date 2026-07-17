<script setup lang="ts" generic="T">
import type { TableColumn } from '@nuxt/ui'
import type { FiscalModuleSortingState } from '~/composables/useFiscalModulePortfolio'
import type {
  FiscalModuleCounters,
  FiscalMonitoringSurfaceSummary,
  FiscalPortfolioModuleKey,
  FiscalTableEmptyKind,
  MonitoringFilterConfig,
  MonitoringFilterValue
} from '~/types/fiscal-modules'
import {
  fiscalAsOfLabel,
  fiscalDataOriginLabel,
  fiscalKpiSituationFilter,
  fiscalSituationToKpiKey,
  isSurfaceUnavailable,
  isSyntheticFiscalOrigin
} from '~/types/fiscal-modules'
import { resolveMonitoringSurface } from '~/types/saved-list-filters'
import { dataOriginMeta } from '~/utils/fiscal-status'
import { formatDateTime } from '~/utils/format'
import { monitoringFilterSignature } from '~/utils/monitoring-filters'
import { monitoringSelectionScope } from '~/utils/monitoring-selection'

const props = withDefaults(defineProps<{
  title: string
  description?: string
  panelId?: string
  columns: TableColumn<T>[]
  rows: T[]
  loading?: boolean
  refreshing?: boolean
  error?: string | null
  page: number
  lastPage: number
  total: number
  perPage?: number
  filters: MonitoringFilterValue
  filterConfig: MonitoringFilterConfig
  sorting: FiscalModuleSortingState
  getRowId: (row: T, index: number) => string
  getClientId?: (row: T) => number | null
  moduleKey?: FiscalPortfolioModuleKey | null
  submodule?: string
  columnLabels?: Record<string, string>
  initialHiddenColumns?: string[]
  /** Override opt-in da seleção. Omitido preserva a disponibilidade das ações genéricas. */
  selectionEnabled?: boolean
  /** Substitui as ações em massa genéricas pelo slot `bulk-actions`. */
  customBulkActions?: boolean
  /** Tabela densa: scroll horizontal sem comprimir as colunas. */
  horizontalScroll?: boolean
  tableClass?: string
  totalClients?: number
  counters?: FiscalModuleCounters | null
  lastGoodAt?: string | null
  /** Origem fiscal do overview (DEMO / SIMULATED / LIVE). */
  dataOrigin?: string | null
  dataOriginLabel?: string | null
  sourceLabel?: string | null
  /** Observação fiscal oficial (as_of) — não confundir com lastGoodAt. */
  asOf?: string | null
  showModuleNav?: boolean
  showKpis?: boolean
  showColumnVisibility?: boolean
  emptyTitle?: string
  emptyDescription?: string
  emptyKind?: FiscalTableEmptyKind | null
  /**
   * Surface de presets salvos. Se omitido, deriva de moduleKey
   * (ex. installments → monitoring.installments). Páginas sem moduleKey
   * (registrations, tax_processes) devem passar explicitamente.
   */
  surface?: string | null
  /**
   * Resumo público da superfície do overview (result_kind / allows_document).
   * Distinto de `surface` (preset de filtros salvos).
   */
  surfaceSummary?: FiscalMonitoringSurfaceSummary | null
}>(), {
  description: undefined,
  panelId: 'fiscal-module',
  loading: false,
  refreshing: false,
  error: null,
  perPage: 15,
  getClientId: undefined,
  moduleKey: null,
  submodule: '',
  columnLabels: () => ({}),
  initialHiddenColumns: () => [],
  selectionEnabled: undefined,
  customBulkActions: false,
  horizontalScroll: false,
  tableClass: undefined,
  counters: null,
  lastGoodAt: null,
  dataOrigin: null,
  dataOriginLabel: null,
  sourceLabel: null,
  asOf: null,
  showModuleNav: true,
  showKpis: true,
  showColumnVisibility: true,
  emptyTitle: undefined,
  emptyDescription: undefined,
  emptyKind: null,
  surface: null,
  surfaceSummary: null
})

const emit = defineEmits<{
  'update:page': [value: number]
  'update:sorting': [value: FiscalModuleSortingState]
  'quick-filter-change': [filters: MonitoringFilterValue]
  'apply-filters': [filters: MonitoringFilterValue]
  'reset-filters': [filters: MonitoringFilterValue]
  'refresh': []
}>()

const route = useRoute()
const { sessionEpoch } = useDashboard()
const dataTable = useTemplateRef<{ clearSelection: () => void } | null>('dataTable')
const selectedClientIds = ref<number[]>([])
const selectedCount = ref(0)
const bulkAvailable = ref(false)

const activeKpi = computed(() => fiscalSituationToKpiKey(props.filters.situation))
const resolvedSurface = computed(() => {
  if (props.surface) return props.surface
  return resolveMonitoringSurface(props.moduleKey)
})
const resolvedSelectionEnabled = computed(() => {
  if (typeof props.selectionEnabled === 'boolean') {
    return props.selectionEnabled && Boolean(props.getClientId)
  }
  return Boolean(props.moduleKey && props.getClientId && bulkAvailable.value)
})
const selectionScope = computed(() => monitoringSelectionScope({
  officeEpoch: sessionEpoch.value,
  route: route.fullPath,
  page: props.page,
  filters: monitoringFilterSignature(props.filters),
  sorting: props.sorting,
  submodule: props.submodule || ''
}))

const originMeta = computed(() => dataOriginMeta(props.dataOrigin))
const isSyntheticOrigin = computed(() => isSyntheticFiscalOrigin(props.dataOrigin))
const originLabel = computed(() =>
  props.dataOriginLabel?.trim()
  || fiscalDataOriginLabel(props.dataOrigin)
  || originMeta.value.label
)
const provenanceSource = computed(() =>
  props.sourceLabel?.trim() || null
)
const asOfDisplay = computed(() => fiscalAsOfLabel(props.asOf))
const asOfFormatted = computed(() => {
  if (!props.asOf) return asOfDisplay.value
  try {
    return formatDateTime(props.asOf)
  } catch {
    return asOfDisplay.value
  }
})
const surfaceIsUnavailable = computed(() => isSurfaceUnavailable(props.surfaceSummary))
const surfaceUnavailableTitle = computed(() => {
  const label = props.surfaceSummary?.official_state_label?.trim()
  if (label) {
    return `Operação ainda não produtiva no catálogo SERPRO (${label})`
  }
  return 'Operação ainda não produtiva no catálogo SERPRO'
})

function onSelectionChange(payload: { rows: T[], clientIds: number[], count: number }) {
  selectedClientIds.value = payload.clientIds
  selectedCount.value = payload.count
}

function clearSelection() {
  dataTable.value?.clearSelection()
}

function onKpiSelect(key: Parameters<typeof fiscalKpiSituationFilter>[0]) {
  emit('quick-filter-change', {
    ...props.filters,
    situation: fiscalKpiSituationFilter(key) || 'all'
  })
}
</script>

<template>
  <UDashboardPanel :id="panelId">
    <template #header>
      <UDashboardNavbar
        :title="title"
        data-testid="page-navbar"
      >
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
      </UDashboardNavbar>

      <UDashboardToolbar
        v-if="showModuleNav || $slots.nav"
        :ui="{ left: 'min-w-0 flex-1' }"
        data-testid="monitoring-nav-toolbar"
      >
        <template #left>
          <slot name="nav">
            <MonitoringModuleNav />
          </slot>
        </template>
      </UDashboardToolbar>
    </template>

    <template #body>
      <div
        v-if="$slots.submodules"
        class="w-full min-w-0"
        data-testid="fiscal-submodules"
      >
        <slot name="submodules" />
      </div>

      <!-- Superfície UNAVAILABLE (ex. DASN-SIMEI) — antes dos KPIs; sem dados sintéticos. -->
      <UAlert
        v-if="surfaceIsUnavailable"
        color="warning"
        variant="subtle"
        icon="i-lucide-circle-off"
        :title="surfaceUnavailableTitle"
        data-testid="fiscal-surface-unavailable-alert"
      />

      <!-- Proveniência / frescor fiscal — antes dos KPIs; sintético persiste em filtro/paginação. -->
      <div
        v-if="counters != null || dataOrigin != null || dataOriginLabel"
        data-testid="fiscal-provenance"
        class="flex flex-col gap-2"
      >
        <UAlert
          v-if="isSyntheticOrigin"
          color="warning"
          variant="subtle"
          icon="i-lucide-flask-conical"
          :title="`Dados demonstrativos — sem validade fiscal (${originLabel})`"
          data-testid="fiscal-synthetic-alert"
        />
        <div
          v-else
          class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted"
          data-testid="fiscal-live-meta"
        >
          <span
            class="inline-flex items-center gap-1"
            data-testid="fiscal-origin-label"
          >
            <UIcon
              :name="originMeta.icon"
              class="size-3.5 shrink-0"
            />
            {{ originLabel }}
          </span>
          <span
            v-if="provenanceSource"
            data-testid="fiscal-source-label"
          >
            Fonte: {{ provenanceSource }}
          </span>
          <span data-testid="fiscal-as-of">
            {{ props.asOf ? `Observado: ${asOfFormatted}` : asOfDisplay }}
          </span>
        </div>
      </div>

      <div
        v-if="showKpis || $slots.kpis"
        data-testid="fiscal-kpi-block"
      >
        <slot name="kpis">
          <MonitoringKpiStrip
            :total="totalClients ?? total"
            :total-clients="totalClients ?? total"
            :counters="counters"
            :loading="loading || refreshing"
            :active-key="activeKpi"
            :active-situation="filters.situation"
            @select="onKpiSelect"
          />
        </slot>
      </div>

      <p
        v-if="description"
        class="text-sm text-muted"
      >
        {{ description }}
      </p>

      <div
        v-if="$slots.utilities"
        class="flex flex-wrap items-center gap-2"
        data-testid="fiscal-utilities"
      >
        <slot name="utilities" />
        <span
          v-if="lastGoodAt && (error || refreshing)"
          class="text-xs text-muted"
        >
          Última atualização válida: {{ formatDateTime(lastGoodAt) }}
        </span>
      </div>

      <UAlert
        v-if="error"
        color="error"
        icon="i-lucide-circle-x"
        :title="error"
        data-testid="fiscal-error-alert"
      >
        <template #actions>
          <UButton
            size="xs"
            color="neutral"
            variant="outline"
            label="Tentar de novo"
            @click="emit('refresh')"
          />
        </template>
      </UAlert>

      <!-- customers.vue: toolbar colada à tabela (stack). -->
      <div
        class="flex flex-col gap-1.5"
        data-testid="fiscal-table-stack"
      >
        <MonitoringModuleDataTable
          ref="dataTable"
          :columns="columns"
          :rows="rows"
          :loading="loading || refreshing"
          :error="error"
          :page="page"
          :last-page="lastPage"
          :total="total"
          :per-page="perPage"
          :sorting="sorting"
          :filters="filters"
          :selection-scope="selectionScope"
          :selection-enabled="resolvedSelectionEnabled"
          :horizontal-scroll="horizontalScroll"
          :table-class="tableClass"
          :get-row-id="getRowId"
          :get-client-id="getClientId"
          :column-labels="columnLabels"
          :initial-hidden-columns="initialHiddenColumns"
          :show-column-visibility="showColumnVisibility"
          :empty-title="emptyTitle"
          :empty-description="emptyDescription"
          :empty-kind="emptyKind"
          @update:page="emit('update:page', $event)"
          @update:sorting="emit('update:sorting', $event)"
          @selection-change="onSelectionChange"
          @refresh="emit('refresh')"
        >
          <template #toolbar="{ displayColumnItems, showColumnVisibility: canDisplayColumns }">
            <MonitoringModuleToolbar
              :filters="filters"
              :filter-config="filterConfig"
              :loading="loading || refreshing"
              :show-total="false"
              :reset-key="sessionEpoch"
              :surface="resolvedSurface"
              @quick-filter-change="emit('quick-filter-change', $event)"
              @apply-filters="emit('apply-filters', $event)"
              @reset-filters="emit('reset-filters', $event)"
              @refresh="emit('refresh')"
            >
              <template #actions>
                <slot
                  v-if="customBulkActions"
                  name="bulk-actions"
                  :selected-client-ids="selectedClientIds"
                  :selected-count="selectedCount"
                  :clear-selection="clearSelection"
                />
                <MonitoringModuleBulkActions
                  v-else-if="moduleKey"
                  :module-key="moduleKey"
                  :selected-client-ids="selectedClientIds"
                  :selected-count="selectedCount"
                  :filters="filters"
                  :submodule="submodule"
                  @availability-change="bulkAvailable = $event"
                  @clear="clearSelection"
                  @refresh="emit('refresh')"
                />
              </template>
              <template #trailing>
                <UDropdownMenu
                  v-if="canDisplayColumns"
                  :items="displayColumnItems"
                  :content="{ align: 'end' }"
                >
                  <UButton
                    label="Exibir"
                    color="neutral"
                    variant="outline"
                    trailing-icon="i-lucide-settings-2"
                    data-testid="fiscal-column-visibility"
                  />
                </UDropdownMenu>
              </template>
            </MonitoringModuleToolbar>
          </template>
        </MonitoringModuleDataTable>
      </div>

      <slot name="detail" />
    </template>
  </UDashboardPanel>
</template>

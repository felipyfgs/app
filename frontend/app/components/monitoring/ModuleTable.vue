<script setup lang="ts" generic="T extends FiscalModuleClientRow = FiscalModuleClientRow">
/**
 * Casca de tabela server-side para módulos fiscais.
 * Arquétipo customers.vue — Panel → Navbar → Toolbar → utilitários → Table → empty/error → paginação.
 * Slots: nav, actions, kpis, submodules, banner, toolbar-filters, utilities, empty, detail, row.
 */
import type { TableColumn } from '@nuxt/ui'
import type { FiscalKpiKey, FiscalModuleClientRow, FiscalModuleCounters, FiscalTableEmptyKind } from '~/types/fiscal-modules'
import { fiscalKpiSituationFilter, fiscalSituationToKpiKey, isSyntheticFiscalOrigin } from '~/types/fiscal-modules'
import { formatDateTime } from '~/utils/format'
import { DASHBOARD_TABLE_UI } from '~/utils/table-ui'
import { dataOriginMeta, resolveFiscalEmptyKind } from '~/utils/fiscal-status'

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
  /** Filtros (URL-backed via pai) */
  q?: string
  situation?: string
  competence?: string
  submodule?: string
  deliveryStatus?: string
  /** Overview KPIs */
  totalClients?: number
  counters?: FiscalModuleCounters | null
  lastGoodAt?: string | null
  /** Toolbar flags */
  showModuleNav?: boolean
  showKpis?: boolean
  showSearch?: boolean
  showSituationFilter?: boolean
  showCompetenceFilter?: boolean
  showSubmoduleFilter?: boolean
  showDeliveryStatusFilter?: boolean
  showClientPicker?: boolean
  showExport?: boolean
  canExport?: boolean
  submoduleItems?: Array<{ label: string, value: string }>
  deliveryStatusItems?: Array<{ label: string, value: string }>
  emptyTitle?: string
  emptyDescription?: string
  emptyKind?: FiscalTableEmptyKind | null
}>(), {
  showModuleNav: true,
  showKpis: true,
  showSearch: true,
  showSituationFilter: true,
  showCompetenceFilter: false,
  showSubmoduleFilter: false,
  showDeliveryStatusFilter: false,
  showClientPicker: false,
  showExport: false,
  canExport: false,
  emptyTitle: undefined,
  emptyDescription: undefined,
  perPage: 15
})

const emit = defineEmits<{
  'update:page': [value: number]
  'update:situation': [value: string]
  'update:competence': [value: string]
  'update:submodule': [value: string]
  'update:deliveryStatus': [value: string]
  'update:q': [value: string]
  'update:clientId': [value: number | null]
  'refresh': []
  'export': []
  'kpi-select': [key: FiscalKpiKey]
  'row-select': [row: T]
}>()

const pageModel = computed({
  get: () => props.page,
  set: (v: number) => emit('update:page', v)
})

const activeKpi = computed<FiscalKpiKey>(() => fiscalSituationToKpiKey(props.situation))

const hasRows = computed(() => props.rows.length > 0)
const hasPrevious = computed(() => hasRows.value || props.counters != null)

const resolvedEmptyKind = computed<FiscalTableEmptyKind>(() => {
  if (props.emptyKind) return props.emptyKind
  // hasPrevious no empty-kind só conta linhas (não counters): assim, loading inicial
  // com overview já carregado ainda mostra "Carregando…" e não "Nenhum registro".
  return resolveFiscalEmptyKind({
    loading: props.loading,
    error: props.error,
    hasRows: hasRows.value,
    hasPrevious: hasRows.value,
    situation: props.situation,
    filtered: Boolean(
      (props.q && props.q.trim())
      || (props.situation && props.situation !== 'all')
      || (props.competence && props.competence.trim())
      || (props.submodule && props.submodule !== 'all' && props.submodule.trim())
      || (props.deliveryStatus && props.deliveryStatus !== 'all')
    )
  })
})

const showTableSkeleton = computed(() =>
  Boolean(props.loading && !hasRows.value && !hasPrevious.value)
)

const showEmpty = computed(() =>
  !showTableSkeleton.value && !hasRows.value
)

const showTable = computed(() => hasRows.value)

const syntheticDataOrigin = computed(() =>
  props.rows.find(row => isSyntheticFiscalOrigin(row.data_origin))?.data_origin ?? null
)
const syntheticDataOriginMeta = computed(() =>
  syntheticDataOrigin.value ? dataOriginMeta(syntheticDataOrigin.value) : null
)

function onKpiSelect(key: FiscalKpiKey, situation: string | null = fiscalKpiSituationFilter(key)) {
  emit('kpi-select', key)
  emit('update:situation', situation || 'all')
}

const itemsPerPage = computed(() => {
  if (props.perPage && props.perPage > 0) return props.perPage
  if (props.lastPage > 0 && props.total > 0) {
    return Math.max(1, Math.ceil(props.total / props.lastPage))
  }
  return 15
})
</script>

<template>
  <UDashboardPanel :id="panelId || 'fiscal-module'">
    <template #header>
      <UDashboardNavbar :title="title" data-testid="page-navbar">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
        <template #right>
          <!-- Named slot de ações da carteira. Nome `navbar-actions` evita colisão
           com slots internos `#actions` de UAlert/UButton no mesmo SFC. -->
          <div class="flex min-w-0 flex-wrap items-center justify-end gap-2" data-testid="fiscal-module-navbar-actions">
            <slot name="navbar-actions" />
            <slot name="actions" />
          </div>
        </template>
      </UDashboardNavbar>

      <!-- Nav do módulo (Settings-like) -->
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
      <!-- Utilitários da lista no body, na posição canônica de customers.vue. -->
      <slot name="toolbar">
        <MonitoringModuleToolbar
          :q="q"
          :situation="situation"
          :competence="competence"
          :submodule="submodule"
          :delivery-status="deliveryStatus"
          :total="total"
          :loading="loading || refreshing"
          :show-search="showSearch"
          :show-situation="showSituationFilter"
          :show-competence="showCompetenceFilter"
          :show-submodule="showSubmoduleFilter"
          :show-delivery-status="showDeliveryStatusFilter"
          :show-client-picker="showClientPicker"
          :show-export="showExport"
          :can-export="canExport"
          :submodule-items="submoduleItems"
          :delivery-status-items="deliveryStatusItems"
          @update:q="emit('update:q', $event)"
          @update:situation="emit('update:situation', $event)"
          @update:competence="emit('update:competence', $event)"
          @update:submodule="emit('update:submodule', $event)"
          @update:delivery-status="emit('update:deliveryStatus', $event)"
          @update:client-id="emit('update:clientId', $event)"
          @refresh="emit('refresh')"
          @export="emit('export')"
        >
          <template
            v-if="$slots['toolbar-filters']"
            #filters
          >
            <slot name="toolbar-filters" />
          </template>
        </MonitoringModuleToolbar>
      </slot>

      <p
        v-if="description"
        class="text-sm text-muted"
      >
        {{ description }}
      </p>

      <UAlert
        v-if="syntheticDataOriginMeta"
        color="warning"
        variant="subtle"
        icon="i-lucide-flask-conical"
        :title="syntheticDataOriginMeta.label"
        :description="syntheticDataOriginMeta.description"
        class="mb-4"
        data-testid="fiscal-demo-banner"
      />

      <!-- Submódulos (UTabs etc.) -->
      <div
        v-if="$slots.submodules"
        class="mb-4"
        data-testid="fiscal-submodules"
      >
        <slot name="submodules" />
      </div>

      <!-- KPIs acionáveis -->
      <div
        v-if="showKpis || $slots.kpis"
        class="mb-4"
      >
        <slot name="kpis">
          <!-- API unificada: total|totalClients + activeKey|activeSituation -->
          <MonitoringKpiStrip
            :total="totalClients ?? total"
            :total-clients="totalClients ?? total"
            :counters="counters"
            :loading="loading || refreshing"
            :active-key="activeKpi"
            :active-situation="situation"
            @select="onKpiSelect"
          />
        </slot>
      </div>

      <!-- Utilidades acima da tabela -->
      <div
        v-if="$slots.utilities"
        class="mb-3 flex flex-wrap items-center gap-2"
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

      <!-- Erro com dados anteriores ainda visíveis -->
      <UAlert
        v-if="error"
        color="error"
        icon="i-lucide-circle-x"
        :title="error"
        class="mb-4"
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

      <!-- Skeleton de carregamento inicial -->
      <div
        v-if="showTableSkeleton"
        class="space-y-3 py-4"
        data-testid="fiscal-table-skeleton"
        aria-busy="true"
        aria-label="Carregando carteira"
      >
        <USkeleton class="h-10 w-full" />
        <USkeleton class="h-10 w-full" />
        <USkeleton class="h-10 w-full" />
        <USkeleton class="h-10 w-2/3" />
      </div>

      <!-- Empty states distintos -->
      <MonitoringTableEmptyState
        v-else-if="showEmpty"
        :kind="resolvedEmptyKind"
        :title="emptyTitle"
        :description="emptyDescription"
        :error="error"
        @retry="emit('refresh')"
      />

      <!-- Tabela + paginação server-side -->
      <template v-else-if="showTable">
        <UTable
          :data="rows"
          :columns="columns"
          :loading="loading || refreshing"
          :ui="DASHBOARD_TABLE_UI"
          class="shrink-0"
          data-testid="fiscal-table"
        />

        <div
          v-if="lastPage > 1 || total > itemsPerPage"
          class="mt-auto flex items-center justify-between gap-3 border-t border-default pt-4"
          data-testid="fiscal-pagination"
        >
          <span class="text-sm text-muted">
            Página {{ page }} de {{ Math.max(lastPage, 1) }}
          </span>
          <UPagination
            v-model="pageModel"
            :total="total"
            :items-per-page="itemsPerPage"
            :sibling-count="1"
            show-edges
          />
        </div>
      </template>

      <slot name="detail" />
    </template>
  </UDashboardPanel>
</template>

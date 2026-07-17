<script setup lang="ts" generic="T = FiscalModuleClientRow">
/**
 * Casca de tabela server-side para módulos fiscais — arquétipo customers.vue.
 *
 * Panel → Navbar → ModuleNav → Toolbar (busca/filtros/Exibir + bulk) → KPIs →
 * Table (checkbox select + sort + column visibility) → footer selected + UPagination.
 *
 * Seleção de linhas só com ações em massa reais (associar / consultar / exportar).
 */
import type { DropdownMenuItem, TableColumn } from '@nuxt/ui'
import { upperFirst } from 'scule'
import type {
  FiscalKpiKey,
  FiscalModuleFilterFormValue,
  FiscalModuleClientRow,
  FiscalModuleCounters,
  FiscalTableEmptyKind
} from '~/types/fiscal-modules'
import {
  FISCAL_MODULE_TABLE_CONTEXT,
  type FiscalModuleSortingState
} from '~/composables/useFiscalModulePortfolio'
import { fiscalKpiSituationFilter, fiscalSituationToKpiKey } from '~/types/fiscal-modules'
import { formatDateTime } from '~/utils/format'
import { resolveFiscalEmptyKind } from '~/utils/fiscal-status'

/** :ui literal de customers.vue @ 0f30c09 */
const TABLE_UI = {
  base: 'table-fixed border-separate border-spacing-0',
  thead: '[&>tr]:bg-elevated/50 [&>tr]:after:content-none',
  tbody: '[&>tr]:last:[&>td]:border-b-0',
  th: 'py-2 first:rounded-l-lg last:rounded-r-lg border-y border-default first:border-l last:border-r',
  td: 'border-b border-default',
  separator: 'h-0'
} as const

const UCheckbox = resolveComponent('UCheckbox')

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
  /** Módulo da carteira — habilita bulk actions reais. */
  moduleKey?: string | null
  q?: string
  situation?: string
  competence?: string
  submodule?: string
  deliveryStatus?: string
  clientId?: number | string | null
  sorting?: FiscalModuleSortingState
  columnLabels?: Record<string, string>
  /** Colunas secundárias disponíveis em Exibir, mas recolhidas na primeira carga. */
  initialHiddenColumns?: string[]
  totalClients?: number
  counters?: FiscalModuleCounters | null
  lastGoodAt?: string | null
  showModuleNav?: boolean
  showKpis?: boolean
  showSearch?: boolean
  showSituationFilter?: boolean
  showCompetenceFilter?: boolean
  showSubmoduleFilter?: boolean
  showDeliveryStatusFilter?: boolean
  showClientPicker?: boolean
  showColumnVisibility?: boolean
  /** Checkbox + bulk (default true). Só desenha se houver ação real disponível. */
  showRowSelection?: boolean
  showBulkAssociate?: boolean
  showBulkEnqueue?: boolean
  showBulkExport?: boolean
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
  showColumnVisibility: true,
  showRowSelection: true,
  showBulkAssociate: true,
  showBulkEnqueue: true,
  showBulkExport: true,
  showExport: false,
  canExport: false,
  emptyTitle: undefined,
  emptyDescription: undefined,
  perPage: 15,
  moduleKey: null
})

const emit = defineEmits<{
  'update:page': [value: number]
  'update:situation': [value: string]
  'update:competence': [value: string]
  'update:submodule': [value: string]
  'update:deliveryStatus': [value: string]
  'update:q': [value: string]
  'update:clientId': [value: number | null]
  'update:sorting': [value: FiscalModuleSortingState]
  'apply-filters': [filters: FiscalModuleFilterFormValue]
  'reset-filters': [filters: FiscalModuleFilterFormValue]
  'refresh': []
  'export': []
  'kpi-select': [key: FiscalKpiKey]
  'row-select': [row: T]
  'selection-change': [rows: T[]]
  'bulk-associate': [clientIds: number[]]
  'bulk-enqueue': [clientIds: number[]]
  'bulk-export': [clientIds: number[]]
}>()

const table = useTemplateRef<{ tableApi?: {
  getAllColumns: () => Array<{
    id: string
    getCanHide: () => boolean
    getIsVisible: () => boolean
    toggleVisibility: (value: boolean) => void
  }>
  getFilteredSelectedRowModel: () => { rows: Array<{ original: T, id: string }> }
  getFilteredRowModel: () => { rows: Array<{ original: T }> }
  resetRowSelection: () => void
} } | null>('table')

const columnVisibility = ref<Record<string, boolean>>(
  Object.fromEntries((props.initialHiddenColumns || []).map(id => [id, false]))
)
const rowSelection = ref<Record<string, boolean>>({})
const tableContext = inject(FISCAL_MODULE_TABLE_CONTEXT, null)

const {
  canAssociateCategories,
  canTriggerSync,
  canCreateExport,
  enqueueing,
  exporting,
  enqueueReadUpdate,
  exportPortfolio,
  moduleSupportsEnqueueRead,
  moduleSupportsPortfolioExport
} = useMonitoringActions(computed(() => props.moduleKey || 'dashboard'))

const associateOpen = ref(false)
const bulkBusy = ref(false)

const pageModel = computed({
  get: () => props.page,
  set: (v: number) => emit('update:page', v)
})

const sortingModel = computed({
  get: (): FiscalModuleSortingState => {
    if (props.sorting) return props.sorting
    return tableContext?.sorting.value ?? []
  },
  set: (value: FiscalModuleSortingState) => {
    emit('update:sorting', value)
    if (tableContext && !props.sorting) {
      tableContext.sorting.value = value
    }
  }
})

const activeKpi = computed<FiscalKpiKey>(() => fiscalSituationToKpiKey(props.situation))

const hasRows = computed(() => props.rows.length > 0)

/**
 * Empty kind para o slot #empty da UTable (customers.vue: tabela nunca some).
 * Loading com data vazia fica no estado nativo de loading da UTable.
 */
const resolvedEmptyKind = computed<FiscalTableEmptyKind>(() => {
  if (props.emptyKind) return props.emptyKind
  return resolveFiscalEmptyKind({
    loading: false,
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
      || (props.clientId != null && String(props.clientId).trim())
    )
  })
})

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

function rowKeyOf(row: T, index = 0): string {
  const r = row as { client_id?: unknown, id?: unknown }
  if (r.client_id != null && r.client_id !== '') return `c:${r.client_id}`
  if (r.id != null && r.id !== '') return `i:${r.id}`
  return `idx:${index}`
}

function clientIdOf(row: T): number | null {
  const n = Number((row as { client_id?: unknown }).client_id)
  return Number.isFinite(n) && n > 0 ? Math.floor(n) : null
}

const canBulkAssociate = computed(() =>
  props.showBulkAssociate
  && canAssociateCategories.value
  && Boolean(props.moduleKey)
)

const canBulkEnqueue = computed(() =>
  props.showBulkEnqueue
  && canTriggerSync.value
  && Boolean(props.moduleKey)
  && moduleSupportsEnqueueRead.value
)

const canBulkExport = computed(() =>
  props.showBulkExport
  && canCreateExport.value
  && Boolean(props.moduleKey)
  && moduleSupportsPortfolioExport.value
)

/** Checkbox só existe com pelo menos uma ação em massa real (checklist UI). */
const selectionEnabled = computed(() =>
  props.showRowSelection
  && (canBulkAssociate.value || canBulkEnqueue.value || canBulkExport.value)
)

const selectColumn = computed<TableColumn<T>>(() => ({
  id: 'select',
  enableHiding: false,
  enableSorting: false,
  meta: {
    class: {
      th: 'w-10 min-w-10',
      td: 'w-10 min-w-10'
    }
  },
  header: ({ table: t }) =>
    h(UCheckbox, {
      'modelValue': t.getIsSomePageRowsSelected()
        ? 'indeterminate'
        : t.getIsAllPageRowsSelected(),
      'onUpdate:modelValue': (value: boolean | 'indeterminate') =>
        t.toggleAllPageRowsSelected(!!value),
      'ariaLabel': 'Selecionar todas as linhas da página'
    }),
  cell: ({ row }) =>
    h(UCheckbox, {
      'modelValue': row.getIsSelected(),
      'onUpdate:modelValue': (value: boolean | 'indeterminate') => row.toggleSelected(!!value),
      'ariaLabel': 'Selecionar linha'
    })
}))

const tableColumns = computed<TableColumn<T>[]>(() => {
  if (!selectionEnabled.value) return props.columns
  // Evita duplicar se a página já trouxe coluna select
  if (props.columns.some(c => c.id === 'select')) return props.columns
  return [selectColumn.value, ...props.columns]
})

const selectedCount = computed(() =>
  Object.values(rowSelection.value).filter(Boolean).length
)

const selectedRows = computed((): T[] => {
  const keys = new Set(
    Object.entries(rowSelection.value)
      .filter(([, on]) => on)
      .map(([k]) => k)
  )
  if (!keys.size) return []
  return props.rows.filter((row, index) => keys.has(rowKeyOf(row, index)))
})

const selectedClientIds = computed((): number[] => {
  const ids = selectedRows.value
    .map(clientIdOf)
    .filter((id): id is number => id != null)
  return [...new Set(ids)]
})

const columnLabelMap = computed<Record<string, string>>(() => ({
  select: 'Seleção',
  client: 'Cliente',
  competence: 'Competência',
  situation: 'Situação',
  coverage: 'Cobertura',
  consulted: 'Última consulta',
  observed: 'Observado',
  synced: 'Sincronizado',
  actions: 'Ações',
  ...(props.columnLabels || {})
}))

type DisplayColumnItem = {
  label: string
  type: 'checkbox'
  checked: boolean
  onUpdateChecked: (checked: boolean) => void
  onSelect: (e?: Event) => void
}

const displayColumnItems = computed((): DisplayColumnItem[] => {
  const api = table.value?.tableApi
  if (!api) return []
  return api
    .getAllColumns()
    .filter(column => column.getCanHide())
    .map(column => ({
      label: columnLabelMap.value[column.id] || upperFirst(column.id),
      type: 'checkbox' as const,
      checked: column.getIsVisible(),
      onUpdateChecked(checked: boolean) {
        column.toggleVisibility(!!checked)
      },
      onSelect(e?: Event) {
        e?.preventDefault()
      }
    }))
})

function clearSelection() {
  rowSelection.value = {}
  table.value?.tableApi?.resetRowSelection?.()
}

watch(
  () => [
    props.page,
    props.q,
    props.situation,
    props.competence,
    props.submodule,
    props.deliveryStatus,
    props.clientId
  ],
  () => clearSelection()
)

watch(selectedRows, (rows) => {
  emit('selection-change', rows)
}, { deep: true })

async function onBulkAssociate() {
  if (!selectedClientIds.value.length) return
  emit('bulk-associate', selectedClientIds.value)
  associateOpen.value = true
}

async function onBulkEnqueue() {
  const ids = selectedClientIds.value
  if (!ids.length) return
  emit('bulk-enqueue', ids)

  if (props.moduleKey === 'fgts' && !String(props.competence || '').trim()) {
    useToast().add({
      title: 'Informe a competência (AAAA-MM) para consultar os selecionados.',
      color: 'warning'
    })
    return
  }

  bulkBusy.value = true
  let ok = 0
  let fail = 0
  try {
    for (const clientId of ids) {
      const result = await enqueueReadUpdate({
        client_id: clientId,
        competence: props.competence || undefined
      })
      if (result) ok++
      else fail++
    }
    useToast().add({
      title: 'Consultas enfileiradas',
      description: `${ok} ok${fail ? ` · ${fail} falha(s)` : ''} de ${ids.length} cliente(s)`,
      color: fail && !ok ? 'error' : fail ? 'warning' : 'success'
    })
    if (ok) {
      clearSelection()
      emit('refresh')
    }
  } finally {
    bulkBusy.value = false
  }
}

async function onBulkExport() {
  const ids = selectedClientIds.value
  if (!ids.length) return
  emit('bulk-export', ids)

  // API de export da carteira aceita um client_id por job — gera um job por cliente (teto 10).
  const batch = ids.slice(0, 10)
  bulkBusy.value = true
  let ok = 0
  try {
    for (const clientId of batch) {
      const done = await exportPortfolio(
        {
          situation: props.situation || undefined,
          competence: props.competence || undefined,
          q: props.q || undefined,
          submodule: props.submodule || undefined,
          client_id: clientId
        },
        { navigate: false, silent: true }
      )
      if (done) ok++
    }
    useToast().add({
      title: ok ? 'Exportações enfileiradas' : 'Nenhuma exportação criada',
      description: ids.length > 10
        ? `${ok} job(s) de até 10 (de ${ids.length} selecionados). Veja em Exportações.`
        : `${ok} job(s) · veja em Exportações quando READY.`,
      color: ok ? 'success' : 'warning'
    })
    if (ok) clearSelection()
  } finally {
    bulkBusy.value = false
  }
}

const bulkActionItems = computed<DropdownMenuItem[][]>(() => {
  const actions: DropdownMenuItem[] = []
  const busy = bulkBusy.value || enqueueing.value || exporting.value

  if (canBulkAssociate.value) {
    actions.push({
      label: 'Associar categorias',
      icon: 'i-lucide-tags',
      disabled: busy,
      onSelect: () => { void onBulkAssociate() }
    })
  }

  if (canBulkEnqueue.value) {
    actions.push({
      label: 'Solicitar consulta',
      icon: 'i-lucide-cloud-download',
      disabled: busy,
      onSelect: () => { void onBulkEnqueue() }
    })
  }

  if (canBulkExport.value) {
    actions.push({
      label: 'Exportar selecionados',
      icon: 'i-lucide-download',
      disabled: busy,
      onSelect: () => { void onBulkExport() }
    })
  }

  return [
    actions,
    [{
      label: 'Limpar seleção',
      icon: 'i-lucide-x',
      disabled: busy,
      onSelect: clearSelection
    }]
  ].filter(group => group.length > 0)
})
</script>

<template>
  <UDashboardPanel :id="panelId || 'fiscal-module'">
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
      <!--
        Ordem customers.vue adaptada ao monitoramento:
        1) cápsulas (submódulos / modalidade)
        2) KPIs de situação
        3) alertas/utilitários
        4) toolbar (busca + filtros + ações) colada à tabela
        5) UTable + footer
      -->
      <div
        v-if="$slots.submodules"
        class="w-full min-w-0"
        data-testid="fiscal-submodules"
      >
        <slot name="submodules" />
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
            :active-situation="situation"
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

      <!-- customers.vue: busca/filtros imediatamente acima da tabela (sem bloco no meio). -->
      <div
        class="flex flex-col gap-1.5"
        data-testid="fiscal-table-stack"
      >
        <slot name="toolbar">
          <MonitoringModuleToolbar
            :q="q"
            :situation="situation"
            :competence="competence"
            :submodule="submodule"
            :delivery-status="deliveryStatus"
            :client-id="clientId"
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
            :show-total="false"
            :submodule-items="submoduleItems"
            :delivery-status-items="deliveryStatusItems"
            @update:q="emit('update:q', $event)"
            @update:situation="emit('update:situation', $event)"
            @update:competence="emit('update:competence', $event)"
            @update:submodule="emit('update:submodule', $event)"
            @update:delivery-status="emit('update:deliveryStatus', $event)"
            @update:client-id="emit('update:clientId', $event)"
            @apply="emit('apply-filters', $event)"
            @reset="emit('reset-filters', $event)"
            @refresh="emit('refresh')"
            @export="emit('export')"
          >
            <template #actions>
              <div
                v-if="selectionEnabled && selectedCount > 0"
                data-testid="fiscal-bulk-actions"
              >
                <slot
                  name="bulk-actions"
                  :selected-rows="selectedRows"
                  :selected-client-ids="selectedClientIds"
                  :selected-count="selectedCount"
                  :clear="clearSelection"
                >
                  <UDropdownMenu
                    :items="bulkActionItems"
                    :content="{ align: 'start' }"
                  >
                    <UButton
                      color="neutral"
                      variant="subtle"
                      icon="i-lucide-list-checks"
                      label="Ações"
                      :loading="bulkBusy || enqueueing || exporting"
                      data-testid="bulk-actions-menu"
                    >
                      <template #trailing>
                        <UKbd>{{ selectedCount }}</UKbd>
                      </template>
                    </UButton>
                  </UDropdownMenu>
                </slot>
              </div>
            </template>
            <template
              v-if="$slots['toolbar-filters']"
              #filters
            >
              <slot name="toolbar-filters" />
            </template>
            <template
              v-if="showColumnVisibility"
              #trailing
            >
              <UDropdownMenu
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
        </slot>

        <UTable
          ref="table"
          v-model:column-visibility="columnVisibility"
          v-model:row-selection="rowSelection"
          v-model:sorting="sortingModel"
          :data="rows"
          :columns="tableColumns"
          :loading="loading || refreshing"
          :sorting-options="{ manualSorting: true, enableMultiSort: false }"
          :get-row-id="(row: T, index: number) => rowKeyOf(row, index)"
          :ui="TABLE_UI"
          class="shrink-0"
          data-testid="fiscal-table"
        >
          <template #empty>
            <MonitoringTableEmptyState
              :kind="resolvedEmptyKind"
              :title="emptyTitle"
              :description="emptyDescription"
              :error="error"
              class="py-10"
              @retry="emit('refresh')"
            />
          </template>
        </UTable>

        <div
          class="mt-auto flex items-center justify-between gap-3 border-t border-default pt-4"
          data-testid="fiscal-pagination"
        >
          <div class="text-sm text-muted">
            <template v-if="selectionEnabled">
              {{ selectedCount }} de {{ rows.length }} selecionado(s)
              <span class="text-dimmed"> · </span>
            </template>
            {{ total }} registro(s)
            <template v-if="lastPage > 1">
              · página {{ page }} de {{ Math.max(lastPage, 1) }}
            </template>
          </div>
          <div class="flex items-center gap-1.5">
            <UPagination
              v-model="pageModel"
              :total="total"
              :items-per-page="itemsPerPage"
              :sibling-count="1"
              show-edges
            />
          </div>
        </div>
      </div>

      <FiscalAssociateCategoriesModal
        v-if="canBulkAssociate"
        v-model:open="associateOpen"
        :module-key="moduleKey || undefined"
        :default-client-ids="selectedClientIds"
        @success="() => { clearSelection(); emit('refresh') }"
      />

      <slot name="detail" />
    </template>
  </UDashboardPanel>
</template>

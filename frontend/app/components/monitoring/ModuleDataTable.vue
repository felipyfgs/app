<script setup lang="ts" generic="T">
/**
 * Grade de carteira fiscal — arquétipo customers.vue do template.
 *
 * Desktop (md+): UTable (customers.vue) + columnVisibility «Exibir».
 * Mobile (&lt; md): lista de cards (ModuleMobileCards) com resumo + collapsible
 * + ações — sem scroll horizontal nem pin de colunas.
 *
 * @see .reference/nuxt-dashboard-template/app/pages/customers.vue
 * @see components/monitoring/ModuleMobileCards.vue
 * @see tests/unit/monitoring-mobile-layout.test.ts
 */
import type { TableColumn } from '@nuxt/ui'
import { breakpointsTailwind, useBreakpoints } from '@vueuse/core'
import { upperFirst } from 'scule'
import type {
  FiscalModuleSortingState
} from '~/composables/useFiscalModulePortfolio'
import type {
  FiscalTableEmptyKind,
  MonitoringFilterValue
} from '~/types/fiscal-modules'
import { resolveFiscalEmptyKind } from '~/utils/fiscal-status'
import { hasActiveMonitoringFilters } from '~/utils/monitoring-filters'
import {
  pruneMonitoringSelection,
  selectedMonitoringRows
} from '~/utils/monitoring-selection'

/** Largura mínima padrão quando a página pede scroll sem `tableClass` explícito. */
const DEFAULT_SCROLL_MIN_WIDTH = 'min-w-[56rem]'

/** UI canônica do template customers.vue. */
const TABLE_UI = {
  root: 'overflow-visible',
  base: 'table-fixed border-separate border-spacing-0',
  thead: 'bg-default [&>tr]:bg-elevated/50 [&>tr]:after:content-none',
  tbody: '[&>tr]:last:[&>td]:border-b-0',
  th: 'py-2 first:rounded-l-lg last:rounded-r-lg border-y border-default first:border-l last:border-r',
  td: 'border-b border-default',
  separator: 'h-0'
} as const

const UCheckbox = resolveComponent('UCheckbox')

const props = withDefaults(defineProps<{
  columns: TableColumn<T>[]
  rows: T[]
  loading?: boolean
  error?: string | null
  page: number
  lastPage: number
  total: number
  perPage?: number
  sorting: FiscalModuleSortingState
  filters: MonitoringFilterValue
  selectionScope: string
  selectionEnabled?: boolean
  getRowId: (row: T, index: number) => string
  getClientId?: (row: T) => number | null
  columnLabels?: Record<string, string>
  initialHiddenColumns?: string[]
  showColumnVisibility?: boolean
  /**
   * Scroll horizontal na grade desktop (tabela densa).
   * No mobile os cards substituem a grade — este flag não afeta o phone.
   */
  horizontalScroll?: boolean
  tableClass?: string
  /**
   * Ativa cards no viewport &lt; md (default true).
   * Passe false para forçar tabela + scroll também no mobile.
   */
  mobileCards?: boolean
  emptyTitle?: string
  emptyDescription?: string
  emptyKind?: FiscalTableEmptyKind | null
}>(), {
  loading: false,
  error: null,
  perPage: 15,
  selectionEnabled: false,
  getClientId: undefined,
  columnLabels: () => ({}),
  initialHiddenColumns: () => [],
  showColumnVisibility: true,
  horizontalScroll: false,
  tableClass: undefined,
  mobileCards: true,
  emptyTitle: undefined,
  emptyDescription: undefined,
  emptyKind: null
})

const emit = defineEmits<{
  'update:page': [value: number]
  'update:sorting': [value: FiscalModuleSortingState]
  'selection-change': [payload: { rows: T[], clientIds: number[], count: number }]
  'refresh': []
}>()

const table = useTemplateRef<{ tableApi?: {
  getAllColumns: () => Array<{
    id: string
    getCanHide: () => boolean
    getIsVisible: () => boolean
    toggleVisibility: (value: boolean) => void
  }>
  resetRowSelection: () => void
} } | null>('table')

const columnVisibility = ref<Record<string, boolean>>(
  Object.fromEntries(props.initialHiddenColumns.map(id => [id, false]))
)
const rowSelection = ref<Record<string, boolean>>({})

const pageModel = computed({
  get: () => props.page,
  set: (value: number) => emit('update:page', value)
})
const sortingModel = computed({
  get: () => props.sorting,
  set: (value: FiscalModuleSortingState) => emit('update:sorting', value)
})
const selectedRows = computed(() =>
  selectedMonitoringRows(props.rows, rowSelection.value, props.getRowId)
)
const selectedClientIds = computed(() => {
  if (!props.getClientId) return []
  return [...new Set(
    selectedRows.value
      .map(props.getClientId)
      .filter((id): id is number => id != null && id > 0)
  )]
})
const selectedCount = computed(() => selectedRows.value.length)

function clearSelection() {
  rowSelection.value = {}
  table.value?.tableApi?.resetRowSelection?.()
}

watch(() => props.selectionScope, clearSelection)
watch(() => props.selectionEnabled, (enabled) => {
  if (!enabled) clearSelection()
})
watch(() => props.rows, (rows) => {
  const pruned = pruneMonitoringSelection(rows, rowSelection.value, props.getRowId)
  if (JSON.stringify(pruned) !== JSON.stringify(rowSelection.value)) rowSelection.value = pruned
}, { deep: true })
let lastSelectionSignature = ''
watch([selectedRows, selectedClientIds], () => {
  const clientIds = selectedClientIds.value
  const count = selectedCount.value
  const signature = `${count}:${clientIds.join(',')}`
  if (signature === lastSelectionSignature) return
  lastSelectionSignature = signature
  emit('selection-change', {
    rows: selectedRows.value,
    clientIds,
    count
  })
}, { deep: true, immediate: true })

const selectColumn = computed<TableColumn<T>>(() => ({
  id: 'select',
  enableHiding: false,
  enableSorting: false,
  meta: { class: { th: 'w-10 min-w-10', td: 'w-10 min-w-10' } },
  header: ({ table: current }) => h(UCheckbox, {
    'modelValue': current.getIsSomePageRowsSelected()
      ? 'indeterminate'
      : current.getIsAllPageRowsSelected(),
    'onUpdate:modelValue': (value: boolean | 'indeterminate') =>
      current.toggleAllPageRowsSelected(!!value),
    'ariaLabel': 'Selecionar todas as linhas da página'
  }),
  cell: ({ row }) => h(UCheckbox, {
    'modelValue': row.getIsSelected(),
    'onUpdate:modelValue': (value: boolean | 'indeterminate') => row.toggleSelected(!!value),
    'ariaLabel': 'Selecionar linha'
  })
}))
const tableColumns = computed(() => {
  if (!props.selectionEnabled || props.columns.some(column => column.id === 'select')) {
    return props.columns
  }
  return [selectColumn.value, ...props.columns]
})

const labels = computed<Record<string, string>>(() => ({
  select: 'Seleção',
  client: 'Cliente',
  competence: 'Competência',
  situation: 'Situação',
  coverage: 'Cobertura',
  consulted: 'Última consulta',
  observed: 'Observado',
  synced: 'Sincronizado',
  actions: 'Ações',
  ...props.columnLabels
}))
const displayColumnItems = computed(() => table.value?.tableApi
  ?.getAllColumns()
  .filter(column => column.getCanHide())
  .map(column => ({
    label: labels.value[column.id] || upperFirst(column.id),
    type: 'checkbox' as const,
    checked: column.getIsVisible(),
    onUpdateChecked: (checked: boolean) => column.toggleVisibility(checked),
    onSelect: (event?: Event) => event?.preventDefault()
  })) || [])

const filtered = computed(() => hasActiveMonitoringFilters(props.filters))
const resolvedEmptyKind = computed(() => props.emptyKind || resolveFiscalEmptyKind({
  loading: props.loading,
  error: props.error,
  hasRows: props.rows.length > 0,
  hasPrevious: props.rows.length > 0,
  situation: props.filters.situation,
  filtered: filtered.value
}))
const itemsPerPage = computed(() => props.perPage > 0
  ? props.perPage
  : props.lastPage > 0 && props.total > 0
    ? Math.max(1, Math.ceil(props.total / props.lastPage))
    : 15)

/** Viewport estreito: cards + paginação compacta. Desktop: tabela. */
const breakpoints = useBreakpoints(breakpointsTailwind)
const isNarrow = breakpoints.smaller('sm')
const isCompact = breakpoints.smaller('md')
const useMobileCards = computed(() => props.mobileCards && isCompact.value)
const paginationSiblingCount = computed(() => (isNarrow.value ? 0 : 1))

/** «Exibir colunas» só faz sentido com a tabela desktop montada. */
const canShowColumnVisibility = computed(() =>
  props.showColumnVisibility && !useMobileCards.value
)

const resolvedTableClass = computed(() => {
  const custom = props.tableClass?.trim()
  if (custom) return custom
  if (props.horizontalScroll) return DEFAULT_SCROLL_MIN_WIDTH
  return undefined
})

defineExpose({ clearSelection })
</script>

<template>
  <div
    class="flex min-w-0 flex-col gap-1.5"
    data-testid="fiscal-data-table"
  >
    <slot
      name="toolbar"
      :display-column-items="displayColumnItems"
      :show-column-visibility="canShowColumnVisibility"
    />

    <!-- Mobile: um card por cliente (resumo + collapsible + ações). -->
    <MonitoringModuleMobileCards
      v-if="useMobileCards"
      :rows="rows"
      :columns="tableColumns"
      :get-row-id="getRowId"
      :get-client-id="getClientId"
      :selection-enabled="selectionEnabled"
      :row-selection="rowSelection"
      :column-labels="labels"
      :loading="loading"
      :empty-title="emptyTitle"
      :empty-description="emptyDescription"
      :empty-kind="resolvedEmptyKind"
      :error="error"
      @update:row-selection="rowSelection = $event"
      @refresh="emit('refresh')"
    />

    <!-- Desktop: customers.vue — UTable com scroll horizontal se denso. -->
    <div
      v-else
      class="min-w-0"
      :class="horizontalScroll
        ? 'overflow-x-auto overscroll-x-contain [-webkit-overflow-scrolling:touch]'
        : undefined"
      data-testid="fiscal-table-scroll"
    >
      <UTable
        ref="table"
        v-model:column-visibility="columnVisibility"
        v-model:row-selection="rowSelection"
        v-model:sorting="sortingModel"
        sticky="header"
        :data="rows"
        :columns="tableColumns"
        :loading="loading"
        :sorting-options="{ manualSorting: true, enableMultiSort: false }"
        :get-row-id="getRowId"
        :ui="TABLE_UI"
        class="shrink-0"
        :class="resolvedTableClass"
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
    </div>

    <div
      class="mt-auto flex flex-col gap-3 border-t border-default pt-4 sm:flex-row sm:items-center sm:justify-between sm:gap-3"
      data-testid="fiscal-pagination"
    >
      <div class="min-w-0 text-sm text-muted">
        <template v-if="selectionEnabled">
          <span class="tabular-nums">{{ selectedCount }}</span>
          <span class="max-sm:hidden"> de {{ rows.length }}</span>
          selecionado(s)
          <span class="text-dimmed"> · </span>
        </template>
        <span class="tabular-nums">{{ total }}</span> registro(s)
        <template v-if="lastPage > 1">
          <span class="max-sm:hidden">
            · página {{ page }} de {{ Math.max(lastPage, 1) }}
          </span>
          <span class="sm:hidden tabular-nums">
            · {{ page }}/{{ Math.max(lastPage, 1) }}
          </span>
        </template>
      </div>
      <div class="flex shrink-0 items-center justify-end gap-1.5">
        <UPagination
          v-model="pageModel"
          :total="total"
          :items-per-page="itemsPerPage"
          :sibling-count="paginationSiblingCount"
          :show-edges="!isNarrow"
        />
      </div>
    </div>
  </div>
</template>

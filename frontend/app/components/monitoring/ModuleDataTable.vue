<script setup lang="ts" generic="T">
import type { TableColumn } from '@nuxt/ui'
import { upperFirst } from 'scule'
import type {
  FiscalModuleSortingState
} from '~/composables/useFiscalModulePortfolio'
import type {
  FiscalTableEmptyKind,
  MonitoringFilterValue
} from '~/types/fiscal-modules'
import { resolveFiscalEmptyKind } from '~/utils/fiscal-status'
import {
  pruneMonitoringSelection,
  selectedMonitoringRows
} from '~/utils/monitoring-selection'

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
watch([selectedRows, selectedClientIds], () => {
  emit('selection-change', {
    rows: selectedRows.value,
    clientIds: selectedClientIds.value,
    count: selectedCount.value
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

const filtered = computed(() => Object.entries(props.filters).some(([key, value]) => {
  if (key === 'situation' || key.endsWith('Status') || key === 'status') {
    return value != null && value !== '' && value !== 'all'
  }
  return value != null && String(value).trim() !== ''
}))
const resolvedEmptyKind = computed(() => props.emptyKind || resolveFiscalEmptyKind({
  loading: false,
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

defineExpose({ clearSelection })
</script>

<template>
  <div
    class="flex flex-col gap-1.5"
    data-testid="fiscal-data-table"
  >
    <slot
      name="toolbar"
      :display-column-items="displayColumnItems"
      :show-column-visibility="showColumnVisibility"
    />

    <UTable
      ref="table"
      sticky="header"
      v-model:column-visibility="columnVisibility"
      v-model:row-selection="rowSelection"
      v-model:sorting="sortingModel"
      :data="rows"
      :columns="tableColumns"
      :loading="loading"
      :sorting-options="{ manualSorting: true, enableMultiSort: false }"
      :get-row-id="getRowId"
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
</template>

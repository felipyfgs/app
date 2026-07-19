<script setup lang="ts" generic="T">
/**
 * Lista mobile da carteira fiscal: um card por linha.
 *
 * - Cabeçalho: seleção + cliente + situação
 * - Corpo: campos principais
 * - UCollapsible: demais colunas (detalhes)
 * - Rodapé: ações (actions/send/history)
 *
 * Reusa os `cell` renderers das colunas da UTable (mesmo visual/ações do desktop).
 * Desktop continua em ModuleDataTable (customers.vue).
 */
import type { TableColumn } from '@nuxt/ui'
import type { PropType, VNode } from 'vue'
import { defineComponent, h, watch, computed, ref } from 'vue'
import { upperFirst } from 'scule'
import { UButton, UCard, UCheckbox, UCollapsible, USkeleton } from '#components'
import type { FiscalTableEmptyKind } from '~/types/fiscal-modules'

/** Colunas de identidade — sempre no topo do card. */
const HEADER_IDS = new Set(['client', 'situation'])
/** Colunas de ação — sempre no rodapé (não vão pro collapsible). */
const ACTION_IDS = new Set(['actions', 'send', 'history', 'select'])
/** Campos que ficam no resumo aberto (além de client/situation). */
const SUMMARY_IDS = new Set([
  'last_declaration',
  'competence',
  'period',
  'coverage',
  'consulted',
  'last_search',
  'observed',
  'synced'
])

const props = withDefaults(defineProps<{
  rows: T[]
  columns: TableColumn<T>[]
  getRowId: (row: T, index: number) => string
  getClientId?: (row: T) => number | null
  selectionEnabled?: boolean
  rowSelection: Record<string, boolean>
  columnLabels?: Record<string, string>
  loading?: boolean
  emptyTitle?: string
  emptyDescription?: string
  emptyKind?: FiscalTableEmptyKind | null
  error?: string | null
}>(), {
  selectionEnabled: false,
  getClientId: undefined,
  columnLabels: () => ({}),
  loading: false,
  emptyTitle: undefined,
  emptyDescription: undefined,
  emptyKind: null,
  error: null
})

const emit = defineEmits<{
  'update:rowSelection': [value: Record<string, boolean>]
  'refresh': []
}>()

function columnId(column: TableColumn<T>): string {
  return String(column.id || (column as { accessorKey?: string }).accessorKey || '')
}

function columnLabel(column: TableColumn<T>): string {
  const id = columnId(column)
  if (props.columnLabels[id]) return props.columnLabels[id]
  if (typeof column.header === 'string' && column.header.trim()) return column.header
  return upperFirst(id || 'campo')
}

const contentColumns = computed(() =>
  props.columns.filter((column) => {
    const id = columnId(column)
    return id && id !== 'select'
  })
)

const summaryColumns = computed(() =>
  contentColumns.value.filter((column) => {
    const id = columnId(column)
    return SUMMARY_IDS.has(id) && !HEADER_IDS.has(id) && !ACTION_IDS.has(id)
  })
)

const detailColumns = computed(() =>
  contentColumns.value.filter((column) => {
    const id = columnId(column)
    return !HEADER_IDS.has(id) && !ACTION_IDS.has(id) && !SUMMARY_IDS.has(id)
  })
)

const actionColumns = computed(() =>
  contentColumns.value.filter(column => ACTION_IDS.has(columnId(column)) && columnId(column) !== 'select')
)

const clientColumn = computed(() =>
  contentColumns.value.find(column => columnId(column) === 'client') || null
)

const situationColumn = computed(() =>
  contentColumns.value.find(column => columnId(column) === 'situation') || null
)

function isSelected(rowId: string) {
  return props.rowSelection[rowId] === true
}

function toggleSelected(rowId: string, value: boolean | 'indeterminate') {
  const next = value === true
    ? { ...props.rowSelection, [rowId]: true }
    : Object.fromEntries(
      Object.entries(props.rowSelection).filter(([key]) => key !== rowId)
    ) as typeof props.rowSelection

  emit('update:rowSelection', next)
}

/**
 * Contexto mínimo compatível com cell renderers TanStack (`{ row.original }`).
 */
function mockRow(original: T, index: number) {
  const id = props.getRowId(original, index)
  return {
    id,
    index,
    original,
    getIsSelected: () => isSelected(id),
    toggleSelected: (value: boolean) => toggleSelected(id, value),
    getValue: (key: string) => (original as Record<string, unknown>)[key]
  }
}

function renderCell(column: TableColumn<T>, original: T, index: number): VNode | string | number | null {
  const cell = column.cell
  if (typeof cell === 'function') {
    try {
      const row = mockRow(original, index)
      // CellContext parcial — renderers do produto só usam row.original / client_id.
      return cell({
        row,
        getValue: row.getValue,
        renderValue: () => row.getValue(columnId(column)),
        column,
        table: {} as never,
        cell: {} as never
      } as never) as VNode | string | number | null
    } catch {
      return '—'
    }
  }
  const key = (column as { accessorKey?: string }).accessorKey
  if (key) {
    const value = (original as Record<string, unknown>)[key]
    if (value == null || value === '') return '—'
    return String(value)
  }
  return null
}

/** Host funcional para VNodes vindos de h(). */
const CellHost = defineComponent({
  name: 'MonitoringMobileCellHost',
  props: {
    // O componente filho não pode preservar o parâmetro genérico do SFC pai.
    // O cast fica centralizado no host antes de delegar ao renderer tipado.
    column: { type: Object as PropType<unknown>, required: true },
    original: { type: Object as PropType<unknown>, required: true },
    index: { type: Number, required: true }
  },
  setup(hostProps) {
    return () => {
      const node = renderCell(
        hostProps.column as TableColumn<T>,
        hostProps.original as T,
        hostProps.index
      )
      if (node == null) return h('span', { class: 'text-muted' }, '—')
      if (typeof node === 'string' || typeof node === 'number') {
        return h('span', { class: 'text-sm text-highlighted' }, String(node))
      }
      return node
    }
  }
})

function clientFallback(original: T): string {
  const row = original as {
    legal_name?: string
    name?: string
    display_name?: string
    client_id?: number
  }
  return row.legal_name || row.display_name || row.name || (row.client_id ? `Cliente #${row.client_id}` : 'Cliente')
}

function cnpjFallback(original: T): string | null {
  const row = original as { cnpj_masked?: string, root_cnpj_masked?: string }
  return row.cnpj_masked || row.root_cnpj_masked || null
}

const openMap = ref<Record<string, boolean>>({})

watch(() => props.rows, () => {
  // Fecha collapsibles ao trocar de página/lote.
  openMap.value = {}
}, { deep: false })
</script>

<template>
  <div
    class="flex min-w-0 flex-col gap-3"
    data-testid="fiscal-mobile-cards"
  >
    <template v-if="loading && !rows.length">
      <UCard
        v-for="n in 3"
        :key="`sk-${n}`"
        :ui="{ body: 'space-y-3 p-3 sm:p-4' }"
      >
        <USkeleton class="h-4 w-2/3" />
        <USkeleton class="h-3 w-1/3" />
        <USkeleton class="h-8 w-full" />
      </UCard>
    </template>

    <MonitoringTableEmptyState
      v-else-if="!loading && !rows.length"
      :kind="emptyKind || (error ? 'error' : 'empty')"
      :title="emptyTitle"
      :description="emptyDescription"
      :error="error"
      class="py-10"
      @retry="emit('refresh')"
    />

    <UCard
      v-for="(row, rowIndex) in rows"
      :key="getRowId(row, rowIndex)"
      :ui="{
        root: 'overflow-hidden',
        body: 'p-0'
      }"
      :data-testid="`fiscal-mobile-card-${getRowId(row, rowIndex)}`"
    >
      <!-- Cabeçalho: seleção + cliente + situação -->
      <div class="flex items-start gap-2.5 border-b border-default px-3 py-3">
        <UCheckbox
          v-if="selectionEnabled"
          class="mt-0.5 shrink-0"
          :model-value="isSelected(getRowId(row, rowIndex))"
          :aria-label="`Selecionar ${clientFallback(row)}`"
          @update:model-value="toggleSelected(getRowId(row, rowIndex), $event)"
        />

        <div class="min-w-0 flex-1">
          <CellHost
            v-if="clientColumn"
            :column="clientColumn"
            :original="row"
            :index="rowIndex"
          />
          <div
            v-else
            class="min-w-0"
          >
            <p class="truncate font-medium text-highlighted">
              {{ clientFallback(row) }}
            </p>
            <p
              v-if="cnpjFallback(row)"
              class="text-xs text-muted tabular-nums"
            >
              {{ cnpjFallback(row) }}
            </p>
          </div>
        </div>

        <div
          v-if="situationColumn"
          class="shrink-0"
        >
          <CellHost
            :column="situationColumn"
            :original="row"
            :index="rowIndex"
          />
        </div>
      </div>

      <!-- Resumo (campos principais) -->
      <div
        v-if="summaryColumns.length"
        class="grid gap-2 border-b border-default px-3 py-2.5"
      >
        <div
          v-for="summaryColumn in summaryColumns"
          :key="`sum-${columnId(summaryColumn)}`"
          class="flex min-w-0 items-start justify-between gap-3"
        >
          <span class="shrink-0 text-xs text-muted">
            {{ columnLabel(summaryColumn) }}
          </span>
          <div class="min-w-0 text-right">
            <CellHost
              :column="summaryColumn"
              :original="row"
              :index="rowIndex"
            />
          </div>
        </div>
      </div>

      <!-- Detalhes colapsáveis -->
      <UCollapsible
        v-if="detailColumns.length"
        v-model:open="openMap[getRowId(row, rowIndex)]"
        class="border-b border-default"
        :unmount-on-hide="false"
      >
        <UButton
          class="group w-full justify-between rounded-none px-3 py-2"
          color="neutral"
          variant="ghost"
          size="sm"
          :label="openMap[getRowId(row, rowIndex)] ? 'Ocultar detalhes' : 'Mais detalhes'"
          :trailing-icon="openMap[getRowId(row, rowIndex)]
            ? 'i-lucide-chevron-up'
            : 'i-lucide-chevron-down'"
          :ui="{
            base: 'font-normal text-muted',
            trailingIcon: 'size-4 transition-transform'
          }"
          data-testid="fiscal-mobile-card-toggle"
        />

        <template #content>
          <div class="grid gap-2.5 px-3 pb-3">
            <div
              v-for="detailColumn in detailColumns"
              :key="`det-${columnId(detailColumn)}`"
              class="flex min-w-0 flex-col gap-1 border-t border-default pt-2 first:border-t-0 first:pt-0"
            >
              <span class="text-xs text-muted">
                {{ columnLabel(detailColumn) }}
              </span>
              <div class="min-w-0">
                <CellHost
                  :column="detailColumn"
                  :original="row"
                  :index="rowIndex"
                />
              </div>
            </div>
          </div>
        </template>
      </UCollapsible>

      <!-- Ações -->
      <div
        v-if="actionColumns.length"
        class="flex flex-wrap items-center gap-1 px-2 py-2"
        data-testid="fiscal-mobile-card-actions"
      >
        <div
          v-for="actionColumn in actionColumns"
          :key="`act-${columnId(actionColumn)}`"
          class="min-w-0"
        >
          <CellHost
            :column="actionColumn"
            :original="row"
            :index="rowIndex"
          />
        </div>
      </div>
    </UCard>
  </div>
</template>

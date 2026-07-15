<script setup lang="ts">
/**
 * Catálogo de documentos — UTable no arquétipo lista admin (customers.vue).
 * Fonte: .reference/nuxt-dashboard-template/app/pages/customers.vue
 * + adaptação produto em frontend/app/pages/clients/index.vue
 *
 * Seleção nativa TanStack (rowSelection + getRowId = access_key).
 * Ordenação: client-side na página atual (getSortedRowModel), headers com botão.
 * Navegação: lotes incrementais por cursor (`next_cursor`), sem simular offset.
 */
import type { DropdownMenuItem, TableColumn } from '@nuxt/ui'
import type { Row } from '@tanstack/table-core'
import type { NfseNote } from '~/types/api'
import { documentKindLabel } from '~/utils/documentKinds'
import { DENSE_DASHBOARD_TABLE_UI } from '~/utils/table-ui'

/** Opções de linhas por página (API aceita limit 1–100). */
const NOTES_PAGE_SIZE_OPTIONS = [10, 25, 50, 100] as const
type NotesPageSize = typeof NOTES_PAGE_SIZE_OPTIONS[number]

const props = withDefaults(defineProps<{
  notes: NfseNote[]
  loading?: boolean
  error?: string | null
  selectedAccessKey?: string | null
  pageSize?: number
  total?: number
  hasMore?: boolean
  selectable?: boolean
  selectedKeys?: string[]
}>(), {
  pageSize: 25,
  total: 0,
  hasMore: false
})

const emit = defineEmits<{
  'select': [note: NfseNote]
  'update:pageSize': [size: number]
  'loadMore': []
  'retry': []
  'update:selectedKeys': [keys: string[]]
}>()

const UCheckbox = resolveComponent('UCheckbox')
const UButton = resolveComponent('UButton')
const UDropdownMenu = resolveComponent('UDropdownMenu')

const table = useTemplateRef('table')
const toast = useToast()

/** Seleção nativa UTable (id da linha = access_key). */
const rowSelection = ref<Record<string, boolean>>({})

/** Ordenação TanStack na página carregada (UTable já injeta getSortedRowModel). */
const sorting = ref<{ id: string, desc: boolean }[]>([])

const pageSizeItems = NOTES_PAGE_SIZE_OPTIONS.map(n => ({
  label: `${n} / página`,
  value: n as NotesPageSize
}))

function isNotesPageSize(value: number): value is NotesPageSize {
  return (NOTES_PAGE_SIZE_OPTIONS as readonly number[]).includes(value)
}

const pageSizeModel = computed({
  get: (): NotesPageSize => {
    return isNotesPageSize(props.pageSize) ? props.pageSize : 25
  },
  set: (value: NotesPageSize | number | string) => {
    const next = Number(value)
    if (!Number.isFinite(next) || next === props.pageSize) return
    if (!isNotesPageSize(next)) return
    emit('update:pageSize', next)
  }
})

function sortHeader(
  label: string,
  column: { getIsSorted: () => false | 'asc' | 'desc', toggleSorting: (desc?: boolean) => void }
) {
  const isSorted = column.getIsSorted()
  return h(UButton, {
    color: 'neutral',
    variant: 'ghost',
    label,
    icon: isSorted
      ? (isSorted === 'asc' ? 'i-lucide-arrow-up-narrow-wide' : 'i-lucide-arrow-down-wide-narrow')
      : 'i-lucide-arrow-up-down',
    class: '-mx-2.5',
    onClick: () => column.toggleSorting(column.getIsSorted() === 'asc')
  })
}

watch(
  () => props.selectedKeys,
  (keys) => {
    const next: Record<string, boolean> = {}
    for (const k of keys || []) {
      next[k] = true
    }
    const prev = rowSelection.value
    const same
      = Object.keys(prev).length === Object.keys(next).length
      && Object.keys(next).every(k => prev[k] === true)
    if (!same) {
      rowSelection.value = next
    }
  },
  { immediate: true, deep: true }
)

watch(
  rowSelection,
  (sel) => {
    if (!props.selectable) return
    const keys = Object.entries(sel)
      .filter(([, on]) => !!on)
      .map(([k]) => k)
    const current = props.selectedKeys || []
    if (
      keys.length === current.length
      && keys.every(k => current.includes(k))
    ) {
      return
    }
    emit('update:selectedKeys', keys)
  },
  { deep: true }
)

function shortKey(accessKey: string) {
  if (accessKey.length <= 18) return accessKey
  return `${accessKey.slice(0, 8)}…${accessKey.slice(-6)}`
}

/** Rótulo acessível completo (tipo + número). */
function noteTitle(note: NfseNote) {
  const kind = documentKindLabel(note.kind || 'NFSE')
  if (note.number) return `${kind} nº ${note.number}`
  return shortKey(note.access_key)
}

/** Célula Documento: só número (ou chave curta). */
function documentCellLabel(note: NfseNote) {
  if (note.number) return String(note.number)
  return shortKey(note.access_key)
}

function partyCell(name?: string | null, cnpj?: string | null): { name: string, cnpj: string | null } {
  return {
    name: truncateText(name, 34) || formatCnpj(cnpj) || '—',
    cnpj: name && cnpj ? formatCnpj(cnpj) : null
  }
}

/** Situação fiscal na grade — nunca usar “XML completo” como situação. */
function documentSituationLabel(note: NfseNote): string {
  const raw = (note.status_label || '').trim()
  if (raw && !/^XML\b/i.test(raw) && !/resumo/i.test(raw) && !/completo/i.test(raw)) {
    return raw
  }
  return statusLabel(note.status)
}

function xmlCompletenessHint(note: NfseNote): string | null {
  if (note.has_full_xml === false || note.is_summary === true || note.xml_completeness === 'SUMMARY_ONLY') {
    return 'Somente resumo'
  }
  return null
}

async function copyAccessKey(note: NfseNote) {
  try {
    await navigator.clipboard.writeText(note.access_key)
    toast.add({
      title: 'Chave copiada',
      description: shortKey(note.access_key),
      color: 'success'
    })
  } catch {
    toast.add({
      title: 'Não foi possível copiar a chave',
      color: 'error'
    })
  }
}

function getRowItems(row: Row<NfseNote>): DropdownMenuItem[][] {
  const note = row.original
  return [
    [
      {
        type: 'label',
        label: noteTitle(note)
      },
      {
        label: 'Abrir detalhe',
        icon: 'i-lucide-panel-right-open',
        onSelect: () => emit('select', note)
      },
      {
        label: 'Copiar chave de acesso',
        icon: 'i-lucide-copy',
        onSelect: () => {
          void copyAccessKey(note)
        }
      }
    ]
  ]
}

const columns = computed<TableColumn<NfseNote>[]>(() => {
  const cols: TableColumn<NfseNote>[] = []

  if (props.selectable) {
    cols.push({
      id: 'select',
      enableHiding: false,
      enableSorting: false,
      header: ({ table: t }) =>
        h(UCheckbox, {
          'modelValue': t.getIsSomePageRowsSelected()
            ? 'indeterminate'
            : t.getIsAllPageRowsSelected(),
          'onUpdate:modelValue': (value: boolean | 'indeterminate') =>
            t.toggleAllPageRowsSelected(!!value),
          'ariaLabel': 'Selecionar todas as notas carregadas'
        }),
      cell: ({ row }) =>
        h(UCheckbox, {
          'modelValue': row.getIsSelected(),
          'onUpdate:modelValue': (value: boolean | 'indeterminate') =>
            row.toggleSelected(!!value),
          'ariaLabel': `Selecionar ${noteTitle(row.original)}`,
          'onClick': (e: Event) => e.stopPropagation()
        }),
      meta: {
        class: {
          th: 'w-10',
          td: 'w-10'
        }
      }
    })
  }

  cols.push(
    {
      id: 'kind',
      accessorFn: row => row.kind_label || documentKindLabel(row.kind || 'NFSE'),
      header: ({ column }) => sortHeader('Tipo', column),
      enableSorting: true,
      meta: {
        class: {
          th: 'w-24',
          td: 'w-24'
        }
      }
    },
    {
      id: 'number',
      accessorFn: row => row.number || row.access_key,
      header: ({ column }) => sortHeader('Documento', column),
      enableHiding: false,
      enableSorting: true,
      sortingFn: (a, b) => {
        const na = a.original.number
        const nb = b.original.number
        if (na != null && nb != null) {
          const diff = Number(na) - Number(nb)
          if (Number.isFinite(diff) && diff !== 0) return diff
        }
        return String(na ?? a.original.access_key).localeCompare(
          String(nb ?? b.original.access_key),
          'pt-BR',
          { numeric: true, sensitivity: 'base' }
        )
      },
      meta: {
        class: {
          th: 'min-w-28 w-[10%]',
          td: 'min-w-28 w-[10%]'
        }
      }
    },
    {
      id: 'issued_at',
      accessorFn: row => row.issued_at || '',
      header: ({ column }) => sortHeader('Emissão', column),
      enableSorting: true,
      meta: {
        class: {
          th: 'w-32',
          td: 'w-32'
        }
      }
    },
    {
      accessorKey: 'competence',
      header: ({ column }) => sortHeader('Competência', column),
      enableSorting: true,
      meta: {
        class: {
          th: 'hidden md:table-cell w-32',
          td: 'hidden md:table-cell w-32'
        }
      }
    },
    {
      id: 'issuer',
      accessorFn: row => row.issuer_name || row.issuer_cnpj || '',
      // NF-e/NFC-e: emit · NFS-e: prestador
      header: ({ column }) => sortHeader('Emitente / Prestador', column),
      enableSorting: true,
      meta: {
        class: {
          th: 'max-w-52 min-w-36 w-[18%]',
          td: 'max-w-52 min-w-36 w-[18%]'
        }
      }
    },
    {
      id: 'recipient',
      accessorFn: row => row.taker_name || row.taker_cnpj || '',
      // NF-e/NFC-e: dest · NFS-e: tomador
      header: ({ column }) => sortHeader('Destinatário / Tomador', column),
      enableSorting: true,
      meta: {
        class: {
          th: 'max-w-52 min-w-36 w-[18%] hidden sm:table-cell',
          td: 'max-w-52 min-w-36 w-[18%] hidden sm:table-cell'
        }
      }
    },
    {
      id: 'service_amount',
      accessorKey: 'service_amount',
      header: ({ column }) => sortHeader('Valor', column),
      enableSorting: true,
      sortingFn: (a, b) => {
        const va = Number(a.original.service_amount ?? 0)
        const vb = Number(b.original.service_amount ?? 0)
        return va - vb
      },
      meta: {
        class: {
          th: 'w-32',
          td: 'w-32'
        }
      }
    },
    {
      id: 'status',
      accessorKey: 'status',
      header: ({ column }) => sortHeader('Situação', column),
      enableSorting: true,
      sortingFn: (a, b) =>
        documentSituationLabel(a.original).localeCompare(
          documentSituationLabel(b.original),
          'pt-BR',
          { sensitivity: 'base' }
        ),
      meta: {
        class: {
          th: 'w-36',
          td: 'w-36'
        }
      }
    },
    {
      id: 'actions',
      header: '',
      enableHiding: false,
      enableSorting: false,
      cell: ({ row }) =>
        h(
          'div',
          { class: 'text-right' },
          h(
            UDropdownMenu,
            {
              content: { align: 'end' },
              items: getRowItems(row)
            },
            () =>
              h(UButton, {
                icon: 'i-lucide-ellipsis-vertical',
                color: 'neutral',
                variant: 'ghost',
                square: true,
                class: 'ml-auto',
                'aria-label': `Ações de ${noteTitle(row.original)}`
              })
          )
        ),
      meta: {
        class: {
          th: 'w-12',
          td: 'w-12'
        }
      }
    }
  )

  return cols
})

const selectedCount = computed(() =>
  Object.values(rowSelection.value).filter(Boolean).length
)


defineShortcuts({
  arrowdown: () => {
    if (!props.notes.length) return
    const index = props.notes.findIndex(n => n.access_key === props.selectedAccessKey)
    if (index === -1) emit('select', props.notes[0]!)
    else if (index < props.notes.length - 1) emit('select', props.notes[index + 1]!)
  },
  arrowup: () => {
    if (!props.notes.length) return
    const index = props.notes.findIndex(n => n.access_key === props.selectedAccessKey)
    if (index === -1) emit('select', props.notes[props.notes.length - 1]!)
    else if (index > 0) emit('select', props.notes[index - 1]!)
  }
})
</script>

<template>
  <div data-testid="data-table" class="flex min-h-0 w-full flex-col gap-4">
    <UAlert
      v-if="error"
      :color="notes.length ? 'warning' : 'error'"
      variant="subtle"
      icon="i-lucide-wifi-off"
      :title="notes.length ? 'Falha ao atualizar documentos' : 'Não foi possível carregar documentos'"
      :description="error"
      class="shrink-0"
      :actions="[{ label: 'Tentar novamente', color: 'neutral', variant: 'subtle', onClick: () => emit('retry') }]"
    />

    <UTable
      v-if="loading || notes.length"
      ref="table"
      v-model:row-selection="rowSelection"
      v-model:sorting="sorting"
      :data="notes"
      :columns="columns"
      :loading="!!loading"
      :get-row-id="(row: NfseNote) => row.access_key"
      class="shrink-0"
      :ui="DENSE_DASHBOARD_TABLE_UI"
    >
      <template #kind-cell="{ row }">
        <UBadge
          color="neutral"
          variant="soft"
          size="sm"
          class="font-normal"
        >
          {{ row.original.kind_label || documentKindLabel(row.original.kind || 'NFSE') }}
        </UBadge>
      </template>

      <template #number-cell="{ row }">
        <button
          type="button"
          class="block w-full truncate text-left font-medium tabular-nums text-highlighted hover:text-primary hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
          :class="selectedAccessKey === row.original.access_key ? 'text-primary' : ''"
          :title="row.original.access_key"
          :aria-label="noteTitle(row.original)"
          :aria-current="selectedAccessKey === row.original.access_key ? 'true' : undefined"
          @click="emit('select', row.original)"
        >
          {{ documentCellLabel(row.original) }}
        </button>
      </template>

      <template #issued_at-cell="{ row }">
        <span class="tabular-nums text-default">
          {{ formatDate(row.original.issued_at) }}
        </span>
      </template>

      <template #competence-cell="{ row }">
        <span class="tabular-nums text-muted">
          {{ row.original.competence || '—' }}
        </span>
      </template>

      <template #issuer-cell="{ row }">
        <div class="min-w-0 max-w-full">
          <p
            class="truncate font-medium text-highlighted"
            :title="row.original.issuer_name || formatCnpj(row.original.issuer_cnpj) || undefined"
          >
            {{ partyCell(row.original.issuer_name, row.original.issuer_cnpj).name }}
          </p>
          <p
            v-if="partyCell(row.original.issuer_name, row.original.issuer_cnpj).cnpj"
            class="truncate font-mono text-xs text-muted"
          >
            {{ partyCell(row.original.issuer_name, row.original.issuer_cnpj).cnpj }}
          </p>
          <!-- Destinatário/Tomador sob o emitente no mobile (coluna some em xs) -->
          <p
            v-if="partyCell(row.original.taker_name, row.original.taker_cnpj).name !== '—'"
            class="mt-0.5 truncate text-xs text-dimmed sm:hidden"
            :title="row.original.taker_name || formatCnpj(row.original.taker_cnpj) || undefined"
          >
            → {{ partyCell(row.original.taker_name, row.original.taker_cnpj).name }}
          </p>
        </div>
      </template>

      <template #recipient-cell="{ row }">
        <div class="min-w-0 max-w-full">
          <p
            class="truncate font-medium text-highlighted"
            :title="row.original.taker_name || formatCnpj(row.original.taker_cnpj) || undefined"
          >
            {{ partyCell(row.original.taker_name, row.original.taker_cnpj).name }}
          </p>
          <p
            v-if="partyCell(row.original.taker_name, row.original.taker_cnpj).cnpj"
            class="truncate font-mono text-xs text-muted"
          >
            {{ partyCell(row.original.taker_name, row.original.taker_cnpj).cnpj }}
          </p>
        </div>
      </template>

      <template #service_amount-cell="{ row }">
        <div class="text-right font-medium tabular-nums text-highlighted">
          {{ formatCurrency(row.original.service_amount) }}
        </div>
      </template>

      <template #status-cell="{ row }">
        <div class="min-w-0">
          <AppStatusBadge
            :status="row.original.status"
            :label="documentSituationLabel(row.original)"
          />
          <p
            v-if="xmlCompletenessHint(row.original)"
            class="mt-0.5 truncate text-xs text-muted"
            :title="xmlCompletenessHint(row.original) || undefined"
          >
            {{ xmlCompletenessHint(row.original) }}
          </p>
        </div>
      </template>
    </UTable>

    <!-- Footer: contagem carregada · tamanho do lote · próximo cursor -->
    <div class="mt-auto flex flex-wrap items-center justify-between gap-3 border-t border-default pt-4">
      <div class="text-sm text-muted">
        <template v-if="selectable && selectedCount">
          {{ selectedCount }} de {{ notes.length }} carregado(s) selecionado(s)
          <span v-if="total"> · {{ total }} no total</span>.
        </template>
        <template v-else-if="total">
          {{ notes.length }} de {{ total }} documento(s) carregados.
        </template>
        <template v-else>
          {{ notes.length }} documento(s) carregado(s).
        </template>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <USelect
          v-model="pageSizeModel"
          :items="pageSizeItems"
          value-key="value"
          :disabled="loading"
          class="w-36"
          aria-label="Linhas por página"
          :ui="{ trailingIcon: 'group-data-[state=open]:rotate-180 transition-transform duration-200' }"
        />
        <UButton
          v-if="hasMore"
          color="neutral"
          variant="subtle"
          icon="i-lucide-chevrons-down"
          label="Carregar mais"
          :disabled="loading"
          :loading="loading"
          @click="emit('loadMore')"
        />
      </div>
    </div>

    <UEmpty
      v-if="!loading && !error && !notes.length"
      icon="i-lucide-file-search"
      title="Nenhum documento encontrado"
      description="Revise os filtros ou aguarde a próxima sincronização do ADN."
    />
  </div>
</template>

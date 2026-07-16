<script setup lang="ts">
/**
 * Documentos → Por cliente — arquétipo customers.vue / lista canônica do painel.
 * Recurso: busca, filtro operacional, Exibir colunas, sort server-side,
 * refresh, loading/empty/error, paginação e ações de linha.
 * Domínio: captura/sync (sem colunas de cadastro A1/estado).
 */
import type { DropdownMenuItem, TableColumn } from '@nuxt/ui'
import type { Client } from '~/types/api'
import { upperFirst } from 'scule'
import { sortHeader } from '~/utils/table-sort'
import { DENSE_DASHBOARD_TABLE_UI } from '~/utils/table-ui'

defineProps<{
  rows: Client[]
  loading?: boolean
  error?: string | null
  total: number
  lastPage: number
}>()

const search = defineModel<string>('search', { default: '' })
const operationalFilter = defineModel<string>('operationalFilter', { default: 'total' })
const page = defineModel<number>('page', { default: 1 })
const perPage = defineModel<number>('perPage', { default: 20 })
const sorting = defineModel<{ id: string, desc: boolean }[]>('sorting', {
  default: () => [{ id: 'legal_name', desc: false }]
})

const emit = defineEmits<{
  openClient: [client: Client]
  retry: []
  apply: []
}>()

const table = useTemplateRef('table')
const columnVisibility = ref()
const toast = useToast()

const operationalItems = [
  { label: 'Todos', value: 'total' },
  { label: 'Captura com problema', value: 'capture_problem' }
]

type ChipTone = 'success' | 'warning' | 'error' | 'neutral' | 'info'

const columns: TableColumn<Client>[] = [
  {
    accessorKey: 'legal_name',
    header: ({ column }) => sortHeader('Cliente', column),
    enableHiding: false,
    meta: {
      class: {
        th: 'w-[36%] min-w-40',
        td: 'w-[36%] min-w-40'
      }
    }
  },
  {
    id: 'cnpj',
    accessorFn: row => row.cnpj || row.root_cnpj,
    header: ({ column }) => sortHeader('CNPJ/CPF', column),
    meta: {
      class: {
        th: 'hidden sm:table-cell w-[18%] min-w-36',
        td: 'hidden sm:table-cell w-[18%] min-w-36'
      }
    }
  },
  {
    id: 'capture',
    accessorFn: row => row.capture_summary?.status || '',
    header: 'Captura',
    enableSorting: false,
    meta: {
      class: {
        th: 'w-[14%] min-w-28',
        td: 'w-[14%] min-w-28'
      }
    }
  },
  {
    id: 'sync',
    accessorFn: row => row.sync_summary?.status || '',
    header: 'Sync',
    enableSorting: false,
    meta: {
      class: {
        th: 'hidden md:table-cell w-[14%]',
        td: 'hidden md:table-cell w-[14%]'
      }
    }
  },
  {
    id: 'actions',
    header: 'Ações',
    enableSorting: false,
    enableHiding: false,
    meta: {
      class: {
        th: 'w-[12%] min-w-24',
        td: 'w-[12%] min-w-24'
      }
    }
  }
]

function captureInfo(client: Client): { chipLabel: string, color: ChipTone } {
  const summary = client.capture_summary
  if (!summary || summary.status === 'NONE') {
    return { chipLabel: 'Sem est.', color: 'neutral' }
  }
  if (summary.status === 'ON') {
    return { chipLabel: 'Captura on', color: 'success' }
  }
  if (summary.status === 'PARTIAL') {
    return { chipLabel: 'Parcial', color: 'warning' }
  }
  return { chipLabel: 'Captura off', color: 'neutral' }
}

function syncInfo(client: Client): { chipLabel: string, color: ChipTone, title?: string } {
  const summary = client.sync_summary
  if (!summary || !summary.has_cursor || summary.status === 'NONE') {
    return { chipLabel: 'Sem cursor', color: 'neutral' }
  }
  const last = summary.last_success_at
    ? `Último sucesso: ${formatDateTime(summary.last_success_at)}`
    : undefined
  switch (summary.status) {
    case 'BLOCKED':
      return { chipLabel: 'Bloqueado', color: 'error', title: last }
    case 'ERROR':
      return { chipLabel: 'Erro', color: 'error', title: last }
    case 'RUNNING':
      return { chipLabel: 'Em execução', color: 'info', title: last }
    case 'WAITING':
      return { chipLabel: 'Na fila', color: 'warning', title: last }
    case 'IDLE':
      return { chipLabel: 'OK', color: 'success', title: last }
    default:
      return { chipLabel: statusLabel(summary.status), color: 'neutral', title: last }
  }
}

async function copyCnpj(value?: string | null) {
  const clean = normalizeCnpj(value)
  if (!clean) return
  try {
    await navigator.clipboard.writeText(clean)
    toast.add({ title: 'CNPJ copiado', description: clean, color: 'success' })
  } catch {
    toast.add({ title: 'Não foi possível copiar o CNPJ', color: 'error' })
  }
}

function rowActions(client: Client): DropdownMenuItem[][] {
  return [[{
    label: 'Ver documentos',
    icon: 'i-lucide-file-text',
    onSelect: () => emit('openClient', client)
  }, {
    label: 'Copiar CNPJ/CPF',
    icon: 'i-lucide-copy',
    onSelect: () => void copyCnpj(client.cnpj || client.root_cnpj)
  }]]
}

function onSearchEnter() {
  page.value = 1
  emit('apply')
}

function onOperationalChange() {
  page.value = 1
  emit('apply')
}

function onPerPageChange(value: number) {
  const next = Math.min(50, Math.max(10, Math.floor(Number(value))))
  if (next === perPage.value) return
  perPage.value = next
  page.value = 1
  emit('apply')
}

function clearSearch() {
  search.value = ''
  operationalFilter.value = 'total'
  page.value = 1
  emit('apply')
}
</script>

<template>
  <div class="flex min-h-0 w-full flex-col gap-4" data-testid="notes-by-client">
    <!-- Toolbar canônica (customers.vue): busca · filtros · Exibir · refresh -->
    <div class="flex flex-wrap items-center justify-between gap-1.5">
      <div class="flex min-w-0 flex-1 flex-wrap items-center gap-1.5">
        <UInput
          v-model="search"
          class="max-w-sm"
          icon="i-lucide-search"
          placeholder="Filtrar por nome ou CNPJ/CPF..."
          aria-label="Filtrar clientes por nome ou CNPJ/CPF"
          @keydown.enter.prevent="onSearchEnter"
        />
        <USelect
          v-model="operationalFilter"
          :items="operationalItems"
          value-key="value"
          class="min-w-44"
          aria-label="Filtro de captura"
          @update:model-value="onOperationalChange"
        />
      </div>

      <div class="flex flex-wrap items-center gap-1.5">
        <UButton
          v-if="search || operationalFilter !== 'total'"
          color="neutral"
          variant="ghost"
          label="Limpar"
          @click="clearSearch"
        />
        <UDropdownMenu
          :items="
            table?.tableApi
              ?.getAllColumns()
              .filter((column: any) => column.getCanHide())
              .map((column: any) => ({
                label: ({
                  legal_name: 'Cliente',
                  cnpj: 'CNPJ/CPF',
                  capture: 'Captura',
                  sync: 'Sync',
                  actions: 'Ações'
                } as Record<string, string>)[column.id] || upperFirst(column.id),
                type: 'checkbox' as const,
                checked: column.getIsVisible(),
                onUpdateChecked(checked: boolean) {
                  table?.tableApi?.getColumn(column.id)?.toggleVisibility(!!checked)
                },
                onSelect(e?: Event) {
                  e?.preventDefault()
                }
              }))
          "
          :content="{ align: 'end' }"
        >
          <UButton
            label="Exibir"
            color="neutral"
            variant="outline"
            trailing-icon="i-lucide-settings-2"
          />
        </UDropdownMenu>
        <UButton
          color="primary"
          variant="soft"
          label="Aplicar"
          @click="onSearchEnter"
        />
        <UButton
          icon="i-lucide-refresh-cw"
          color="neutral"
          variant="ghost"
          square
          aria-label="Atualizar lista"
          :loading="loading"
          @click="emit('retry')"
        />
      </div>
    </div>

    <UAlert
      v-if="error"
      color="warning"
      variant="subtle"
      icon="i-lucide-wifi-off"
      :title="rows.length ? 'Falha ao atualizar clientes' : 'Não foi possível carregar clientes'"
      :description="error"
      :actions="[{ label: 'Tentar novamente', color: 'neutral', variant: 'subtle', onClick: () => emit('retry') }]"
    />

    <UTable
      v-if="loading || rows.length"
      ref="table"
      v-model:column-visibility="columnVisibility"
      v-model:sorting="sorting"
      data-testid="data-table"
      class="shrink-0"
      :data="rows"
      :columns="columns"
      :loading="loading"
      :ui="DENSE_DASHBOARD_TABLE_UI"
    >
      <template #legal_name-cell="{ row }">
        <div class="min-w-0">
          <button
            type="button"
            class="block w-full truncate text-left font-medium text-highlighted hover:text-primary hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
            :title="row.original.display_name
              ? `${row.original.legal_name || row.original.name} · ${row.original.display_name}`
              : (row.original.legal_name || row.original.name)"
            @click="emit('openClient', row.original)"
          >
            {{ row.original.legal_name || row.original.name }}
          </button>
          <p
            v-if="row.original.display_name"
            class="truncate text-xs text-muted"
          >
            {{ row.original.display_name }}
          </p>
          <p class="mt-0.5 font-mono text-xs text-dimmed sm:hidden">
            {{ formatCnpj(row.original.cnpj || row.original.root_cnpj) }}
          </p>
        </div>
      </template>

      <template #cnpj-cell="{ row }">
        <button
          type="button"
          class="group inline-flex w-full max-w-full items-center gap-1.5 font-mono text-highlighted hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
          :title="`Copiar ${normalizeCnpj(row.original.cnpj || row.original.root_cnpj)}`"
          @click.stop="copyCnpj(row.original.cnpj || row.original.root_cnpj)"
        >
          <span class="min-w-0 truncate">{{ formatCnpj(row.original.cnpj || row.original.root_cnpj) }}</span>
          <UIcon
            name="i-lucide-copy"
            class="size-3.5 shrink-0 opacity-0 transition-opacity group-hover:opacity-70"
            aria-hidden="true"
          />
        </button>
      </template>

      <template #capture-cell="{ row }">
        <UBadge
          v-for="info in [captureInfo(row.original)]"
          :key="`cap-${row.original.id}-${info.chipLabel}`"
          :color="info.color"
          variant="soft"
          size="md"
          class="h-8 font-normal"
          :ui="{ base: 'h-8 rounded-md' }"
        >
          {{ info.chipLabel }}
        </UBadge>
      </template>

      <template #sync-cell="{ row }">
        <UBadge
          v-for="info in [syncInfo(row.original)]"
          :key="`sync-${row.original.id}-${info.chipLabel}`"
          :color="info.color"
          variant="soft"
          size="md"
          class="h-8 font-normal"
          :ui="{ base: 'h-8 rounded-md' }"
          :title="info.title || info.chipLabel"
        >
          {{ info.chipLabel }}
        </UBadge>
      </template>

      <template #actions-cell="{ row }">
        <div class="flex items-center justify-end gap-1.5">
          <UButton
            icon="i-lucide-file-text"
            color="primary"
            variant="soft"
            size="sm"
            square
            class="size-8"
            :aria-label="`Documentos de ${row.original.legal_name || row.original.name}`"
            @click="emit('openClient', row.original)"
          />
          <span
            class="h-5 w-px shrink-0 bg-accented"
            aria-hidden="true"
          />
          <UDropdownMenu
            :content="{ align: 'end' }"
            :items="rowActions(row.original)"
          >
            <UButton
              icon="i-lucide-ellipsis-vertical"
              color="neutral"
              variant="soft"
              size="sm"
              square
              class="size-8"
              :aria-label="`Mais ações de ${row.original.legal_name || row.original.name}`"
            />
          </UDropdownMenu>
        </div>
      </template>
    </UTable>

    <UEmpty
      v-if="!loading && !error && !rows.length"
      icon="i-lucide-building-2"
      title="Nenhum cliente encontrado"
      description="Ajuste a busca ou o filtro de captura."
    >
      <UButton
        v-if="search || operationalFilter !== 'total'"
        label="Limpar filtros"
        color="neutral"
        variant="outline"
        @click="clearSearch"
      />
    </UEmpty>

    <!-- Footer canônico -->
    <div class="mt-auto flex flex-wrap items-center justify-between gap-3 border-t border-default pt-4">
      <div class="text-sm text-muted">
        {{ total }} cliente(s) · página {{ page }} de {{ lastPage || 1 }}
      </div>
      <div class="flex flex-wrap items-center gap-1.5">
        <USelect
          :model-value="perPage"
          :items="[
            { label: '10 por página', value: 10 },
            { label: '20 por página', value: 20 },
            { label: '50 por página', value: 50 }
          ]"
          value-key="value"
          class="w-36"
          aria-label="Clientes por página"
          @update:model-value="onPerPageChange"
        />
        <UPagination
          v-model:page="page"
          :items-per-page="perPage"
          :total="total"
          :disabled="loading"
        />
      </div>
    </div>
  </div>
</template>

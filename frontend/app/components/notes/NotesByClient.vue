<script setup lang="ts">
/**
 * Aba Clientes — agregação por cliente do escritório.
 * UTable com :ui de customers.vue do template.
 */
import type { TableColumn } from '@nuxt/ui'
import type { NoteClientAggregate } from '~/types/api'
import { DENSE_DASHBOARD_TABLE_UI } from '~/utils/table-ui'

defineProps<{
  rows: NoteClientAggregate[]
  loading?: boolean
  error?: string | null
  page: number
  perPage: number
  total: number
  lastPage: number
}>()

const emit = defineEmits<{
  openClient: [row: NoteClientAggregate]
  retry: []
  'update:page': [page: number]
}>()

const columns: TableColumn<NoteClientAggregate>[] = [
  {
    accessorKey: 'legal_name',
    header: 'Cliente',
    meta: { class: { th: 'min-w-44', td: 'min-w-44' } }
  },
  {
    id: 'cnpj',
    accessorFn: row => row.cnpj || row.root_cnpj,
    header: 'CNPJ',
    meta: { class: { th: 'hidden sm:table-cell w-40', td: 'hidden sm:table-cell w-40' } }
  },
  {
    accessorKey: 'notes_count',
    header: 'NFS-e',
    meta: { class: { th: 'w-20', td: 'w-20' } }
  },
  {
    id: 'signals',
    header: 'Sinais',
    meta: { class: { th: 'hidden md:table-cell w-36', td: 'hidden md:table-cell w-36' } }
  },
  {
    accessorKey: 'last_issued_at',
    header: 'Última nota',
    meta: { class: { th: 'hidden lg:table-cell w-32', td: 'hidden lg:table-cell w-32' } }
  },
  {
    accessorKey: 'service_amount_sum',
    header: 'Valor total',
    meta: { class: { th: 'hidden md:table-cell w-28', td: 'hidden md:table-cell w-28' } }
  },
  {
    id: 'actions',
    header: '',
    meta: { class: { th: 'w-36', td: 'w-36' } }
  }
]
</script>

<template>
  <div class="flex min-h-0 w-full flex-col gap-4" data-testid="notes-by-client">
    <UAlert
      v-if="error"
      color="error"
      icon="i-lucide-wifi-off"
      title="Não foi possível carregar clientes"
      :description="error"
      :actions="[{ label: 'Tentar novamente', color: 'neutral', variant: 'subtle', onClick: () => emit('retry') }]"
    />

    <UTable
      v-if="loading || rows.length"
      :data="rows"
      :columns="columns"
      :loading="loading && !rows.length"
      class="shrink-0"
      :ui="DENSE_DASHBOARD_TABLE_UI"
    >
      <template #legal_name-cell="{ row }">
        <div class="min-w-0">
          <button
            type="button"
            class="block w-full truncate text-left font-medium text-highlighted hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
            @click="emit('openClient', row.original)"
          >
            {{ row.original.legal_name || row.original.name }}
          </button>
          <p v-if="row.original.display_name" class="truncate text-sm text-muted">
            {{ row.original.display_name }}
          </p>
          <p class="mt-0.5 font-mono text-sm text-muted sm:hidden">
            {{ formatCnpj(row.original.cnpj || row.original.root_cnpj) }}
          </p>
        </div>
      </template>

      <template #cnpj-cell="{ row }">
        <span class="font-mono text-sm">{{ formatCnpj(row.original.cnpj || row.original.root_cnpj) }}</span>
      </template>

      <template #notes_count-cell="{ row }">
        <div class="text-right font-medium tabular-nums">
          {{ row.original.notes_count }}
        </div>
      </template>

      <template #signals-cell="{ row }">
        <div class="flex flex-wrap gap-1">
          <UBadge
            v-if="row.original.review_count"
            color="warning"
            variant="subtle"
            size="sm"
          >
            {{ row.original.review_count }} revisão
          </UBadge>
          <UBadge
            v-if="row.original.cancelled_count"
            color="error"
            variant="subtle"
            size="sm"
          >
            {{ row.original.cancelled_count }} canc.
          </UBadge>
          <span
            v-if="!row.original.review_count && !row.original.cancelled_count"
            class="text-xs text-muted"
          >OK</span>
        </div>
      </template>

      <template #last_issued_at-cell="{ row }">
        <span class="text-sm text-muted tabular-nums">
          {{ row.original.last_issued_at ? formatDateTime(row.original.last_issued_at) : '—' }}
        </span>
      </template>

      <template #service_amount_sum-cell="{ row }">
        <div class="text-right tabular-nums text-highlighted">
          {{ formatCurrency(row.original.service_amount_sum) }}
        </div>
      </template>

      <template #actions-cell="{ row }">
        <div class="text-right">
          <UButton
            size="sm"
            color="neutral"
            variant="outline"
            label="Ver NFS-e"
            icon="i-lucide-file-text"
            class="ml-auto"
            @click="emit('openClient', row.original)"
          />
        </div>
      </template>
    </UTable>

    <div class="mt-auto flex items-center justify-between gap-3 border-t border-default pt-4">
      <div class="text-sm text-muted">
        {{ total }} cliente(s) com notas · página {{ page }} de {{ lastPage }}
      </div>
      <UPagination
        v-if="lastPage > 1"
        :page="page"
        :items-per-page="perPage"
        :total="total"
        :disabled="loading"
        @update:page="(value: number) => emit('update:page', value)"
      />
    </div>

    <UEmpty
      v-if="!loading && !error && !rows.length"
      icon="i-lucide-building-2"
      title="Nenhum cliente com notas neste filtro"
      description="Ajuste os filtros ou aguarde a captura de documentos."
    />
  </div>
</template>

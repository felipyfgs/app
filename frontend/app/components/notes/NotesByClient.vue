<script setup lang="ts">
/**
 * Documentos → Por cliente: lista no padrão de /clients (customers.vue),
 * com colunas operacionais de captura. Filtros de busca ficam no NotesFilters.
 */
import type { TableColumn } from '@nuxt/ui'
import type { Client } from '~/types/api'
import { DENSE_DASHBOARD_TABLE_UI } from '~/utils/table-ui'

defineProps<{
  rows: Client[]
  loading?: boolean
  error?: string | null
  page: number
  perPage: number
  total: number
  lastPage: number
}>()

const emit = defineEmits<{
  openClient: [client: Client]
  openClientDetail: [client: Client]
  retry: []
  'update:page': [page: number]
  'update:perPage': [perPage: number]
}>()

type ChipTone = 'success' | 'warning' | 'error' | 'neutral' | 'info'

const columns: TableColumn<Client>[] = [
  {
    accessorKey: 'legal_name',
    header: 'Razão social / nome',
    enableHiding: false,
    meta: {
      class: {
        th: 'w-[30%] min-w-36',
        td: 'w-[30%] min-w-36'
      }
    }
  },
  {
    id: 'cnpj',
    accessorFn: row => row.cnpj || row.root_cnpj,
    header: 'CNPJ/CPF',
    meta: {
      class: {
        th: 'hidden sm:table-cell w-[16%] min-w-36',
        td: 'hidden sm:table-cell w-[16%] min-w-36'
      }
    }
  },
  {
    id: 'credential',
    accessorFn: row => row.credential_summary?.valid_to || '',
    header: 'Certificado digital',
    enableSorting: false,
    meta: {
      class: {
        th: 'w-[18%] min-w-36',
        td: 'w-[18%] min-w-36'
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
        th: 'hidden md:table-cell w-[12%]',
        td: 'hidden md:table-cell w-[12%]'
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
        th: 'hidden lg:table-cell w-[12%]',
        td: 'hidden lg:table-cell w-[12%]'
      }
    }
  },
  {
    accessorKey: 'is_active',
    header: 'Estado',
    meta: {
      class: {
        th: 'hidden xl:table-cell w-[8%]',
        td: 'hidden xl:table-cell w-[8%]'
      }
    }
  },
  {
    id: 'actions',
    header: 'Ações',
    enableSorting: false,
    meta: {
      class: {
        th: 'w-[12%] min-w-28',
        td: 'w-[12%] min-w-28'
      }
    }
  }
]

function formatDateOnly(value?: string | null): string {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return '—'
  return new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short' }).format(date)
}

function credentialInfo(client: Client): { chipLabel: string, color: ChipTone } {
  const summary = client.credential_summary
  if (!summary) {
    return { chipLabel: 'Sem A1', color: 'neutral' }
  }

  const expired = summary.status === 'EXPIRED'
    || !!(summary.valid_to && new Date(summary.valid_to) < new Date())
  const validToLabel = formatDateOnly(summary.valid_to)

  if (expired) {
    return {
      chipLabel: validToLabel !== '—' ? `Vencido ${validToLabel}` : 'Vencido',
      color: 'error'
    }
  }
  if (summary.expires_alert_1 || summary.expires_alert_7 || summary.expires_alert_30) {
    return {
      chipLabel: validToLabel !== '—' ? `A vencer ${validToLabel}` : 'A vencer',
      color: summary.expires_alert_1 ? 'error' : 'warning'
    }
  }
  if (summary.status === 'ACTIVE' || summary.valid_to) {
    return {
      chipLabel: validToLabel !== '—' ? `Válido até ${validToLabel}` : 'Válido',
      color: 'success'
    }
  }
  return { chipLabel: statusLabel(summary.status), color: 'neutral' }
}

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
        <span class="font-mono text-sm tabular-nums">
          {{ formatCnpj(row.original.cnpj || row.original.root_cnpj) }}
        </span>
      </template>

      <template #credential-cell="{ row }">
        <UBadge
          v-for="info in [credentialInfo(row.original)]"
          :key="`cred-${row.original.id}`"
          :color="info.color"
          variant="soft"
          size="md"
          class="h-8 min-w-0 tabular-nums font-normal"
          :ui="{
            base: 'h-8 w-full min-w-0 justify-center rounded-md',
            label: 'truncate text-center'
          }"
          :title="info.chipLabel"
        >
          {{ info.chipLabel }}
        </UBadge>
      </template>

      <template #capture-cell="{ row }">
        <UBadge
          v-for="info in [captureInfo(row.original)]"
          :key="`cap-${row.original.id}`"
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
          :key="`sync-${row.original.id}`"
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

      <template #is_active-cell="{ row }">
        <UBadge
          :color="row.original.is_active ? 'success' : 'neutral'"
          variant="soft"
          size="md"
          class="h-8 font-normal"
          :ui="{ base: 'h-8 rounded-md' }"
        >
          {{ row.original.is_active ? 'Ativo' : 'Inativo' }}
        </UBadge>
      </template>

      <template #actions-cell="{ row }">
        <div class="flex items-center justify-end gap-1.5">
          <UButton
            size="sm"
            color="primary"
            variant="soft"
            label="Documentos"
            icon="i-lucide-file-text"
            class="hidden sm:inline-flex"
            @click="emit('openClient', row.original)"
          />
          <UButton
            size="sm"
            color="primary"
            variant="soft"
            icon="i-lucide-file-text"
            square
            class="sm:hidden size-8"
            :aria-label="`Documentos de ${row.original.legal_name || row.original.name}`"
            @click="emit('openClient', row.original)"
          />
          <UButton
            size="sm"
            color="neutral"
            variant="soft"
            icon="i-lucide-user-round"
            square
            class="size-8"
            :aria-label="`Cadastro de ${row.original.legal_name || row.original.name}`"
            @click="emit('openClientDetail', row.original)"
          />
        </div>
      </template>
    </UTable>

    <div class="mt-auto flex flex-wrap items-center justify-between gap-3 border-t border-default pt-4">
      <div class="text-sm text-muted">
        {{ total }} cliente(s) · página {{ page }} de {{ lastPage }}
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
          @update:model-value="(value: number) => emit('update:perPage', Number(value))"
        />
        <UPagination
          :page="page"
          :items-per-page="perPage"
          :total="total"
          :disabled="loading"
          @update:page="(value: number) => emit('update:page', value)"
        />
      </div>
    </div>

    <UEmpty
      v-if="!loading && !error && !rows.length"
      icon="i-lucide-building-2"
      title="Nenhum cliente encontrado"
      description="Ajuste a busca ou os filtros de captura. Cadastre clientes em Clientes."
    >
      <UButton
        to="/clients"
        label="Ir para Clientes"
        icon="i-lucide-users"
        color="neutral"
        variant="outline"
      />
    </UEmpty>
  </div>
</template>

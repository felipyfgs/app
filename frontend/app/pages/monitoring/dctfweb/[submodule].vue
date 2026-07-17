<script setup lang="ts">
/**
 * DCTFWeb / MIT — tabs + estados independentes (encerramento, transmissão, recibo, evidência, DARF, pagamento).
 * Mutação de alto risco só via FiscalMutationConfirmModal (catálogo de códigos).
 * Task 7.3
 */
import type { DropdownMenuItem, TableColumn } from '@nuxt/ui'
import type { DctfwebClientDetail, DctfwebClientRow, MonitoringFilterConfig } from '~/types/fiscal-modules'
import { DCTFWEB_TABS } from '~/types/fiscal-modules'
import { resolveHighRiskCodesFromRow } from '~/utils/fiscal-high-risk'
import { sortHeader } from '~/utils/table-sort'

const FiscalStatusBadge = resolveComponent('FiscalStatusBadge')
const FiscalClientCell = resolveComponent('FiscalClientCell')
const UButton = resolveComponent('UButton')
const UDropdownMenu = resolveComponent('UDropdownMenu')

const route = useRoute()
const { canAccessAdministration } = useDashboard()

const submodule = ref(normalizeMonitoringSubmodule('dctfweb', route.params.submodule))

const {
  page,
  perPage,
  total,
  lastPage,
  filters,
  loading,
  refreshing,
  loadError,
  overviewError,
  rows,
  counters,
  totalClients,
  lastValidAt,
  sorting,
  setPage,
  refresh,
  applyFilters,
  applyQuickFilters,
  resetFilters
} = useFiscalModulePortfolio('dctfweb', {
  submodule,
  submodulePath: value => monitoringSubmodulePath('dctfweb', value)
})

const filterConfig: MonitoringFilterConfig = {
  fields: [
    { key: 'situation', kind: 'option', label: 'Situação' },
    { key: 'clientId', kind: 'client', label: 'Cliente' },
    { key: 'competence', kind: 'month', label: 'Competência' }
  ]
}

function getRowId(row: DctfwebClientRow) {
  return `c:${row.client_id}`
}

const mutationOpen = ref(false)
const mutationRequest = ref<{
  client_id: number
  solution_code: string
  service_code: string
  operation_code: string
  competence_period_key?: string | null
  module?: string
} | null>(null)

const tabItems = DCTFWEB_TABS.map(t => ({ label: t.label, value: t.value }))

watch(
  () => route.params.submodule,
  (raw) => {
    const next = normalizeMonitoringSubmodule('dctfweb', raw)
    if (next !== submodule.value) submodule.value = next
  }
)

function clientHref(id: number) {
  return `/monitoring/clients/${id}`
}

function detailOf(row: DctfwebClientRow): DctfwebClientDetail {
  return row.detail || {}
}

function statusOrDash(status?: string | null) {
  const s = String(status || '').trim()
  if (!s || s === '—') return '—'
  return h(FiscalStatusBadge, { status: s, showHint: true })
}

function mutationHint(): 'mit' | 'dctfweb' {
  return submodule.value === 'MIT' ? 'mit' : 'dctfweb'
}

/** Só oferece mutação se o catálogo oficial resolver códigos (sem inventar fallback). */
function canOpenTransmit(row: DctfwebClientRow): boolean {
  if (!canAccessAdministration.value || !row.client_id) return false
  return resolveHighRiskCodesFromRow(
    { client_id: row.client_id } as Record<string, unknown>,
    mutationHint()
  ) != null
}

function openTransmit(row: DctfwebClientRow) {
  const d = detailOf(row)
  const codes = resolveHighRiskCodesFromRow(
    { client_id: row.client_id } as Record<string, unknown>,
    mutationHint()
  )
  if (!codes) return
  mutationRequest.value = {
    client_id: row.client_id,
    solution_code: codes.solution_code,
    service_code: codes.service_code,
    operation_code: codes.operation_code,
    competence_period_key: String(
      row.competence
      || d.dctfweb?.period_key
      || d.mit?.period_key
      || ''
    ) || null,
    module: codes.module || mutationHint()
  }
  mutationOpen.value = true
}

function rowActionItems(row: DctfwebClientRow): DropdownMenuItem[][] {
  const groups: DropdownMenuItem[][] = [[{
    label: 'Abrir cliente',
    icon: 'i-lucide-building-2',
    to: clientHref(row.client_id)
  }]]

  if (canOpenTransmit(row)) {
    groups.push([{
      label: submodule.value === 'MIT' ? 'Encerrar apuração' : 'Transmitir declaração',
      icon: submodule.value === 'MIT' ? 'i-lucide-circle-check-big' : 'i-lucide-send',
      color: 'warning',
      onSelect: () => openTransmit(row)
    }])
  }

  return groups
}

const columns: TableColumn<DctfwebClientRow>[] = [
  {
    id: 'client',
    header: ({ column }) => sortHeader('Cliente', column),
    enableHiding: false,
    cell: ({ row }) => h(FiscalClientCell, {
      clientId: row.original.client_id,
      name: row.original.name || row.original.display_name,
      legalName: row.original.legal_name,
      cnpjMasked: row.original.cnpj_masked,
      to: clientHref(row.original.client_id)
    })
  },
  {
    id: 'competence',
    header: ({ column }) => sortHeader('Competência', column),
    cell: ({ row }) => {
      const d = detailOf(row.original)
      return String(
        row.original.competence
        || d.dctfweb?.period_key
        || d.mit?.period_key
        || '—'
      )
    }
  },
  {
    id: 'situation',
    header: ({ column }) => sortHeader('Situação', column),
    cell: ({ row }) => h(FiscalStatusBadge, { status: row.original.situation })
  },
  {
    id: 'closure',
    header: 'Encerramento',
    enableSorting: false,
    cell: ({ row }) => {
      // Eixo MIT — independente da transmissão DCTFWeb
      const mit = detailOf(row.original).mit
      return statusOrDash(mit?.encerramento_status)
    }
  },
  {
    id: 'transmission',
    header: 'Transmissão',
    enableSorting: false,
    cell: ({ row }) => {
      const d = detailOf(row.original)
      const status = d.dctfweb?.transmission_status
        || d.mit?.dctfweb_transmission_status
        || null
      return statusOrDash(status)
    }
  },
  {
    id: 'receipt',
    header: 'Recibo',
    enableSorting: false,
    cell: ({ row }) => String(detailOf(row.original).dctfweb?.receipt_number || '—')
  },
  {
    id: 'evidence',
    header: 'Evidência',
    enableSorting: false,
    cell: ({ row }) => {
      // Portfolio não expõe evidence_version; recibo indica presença de evidência de transmissão.
      const receipt = detailOf(row.original).dctfweb?.receipt_number
      if (receipt) {
        return h(FiscalStatusBadge, { status: 'UP_TO_DATE', showHint: true })
      }
      return '—'
    }
  },
  {
    id: 'darf',
    header: 'DARF',
    enableSorting: false,
    cell: () => {
      // Contrato da carteira ainda não devolve status de DARF por linha.
      return '—'
    }
  },
  {
    id: 'payment',
    header: 'Pagamento',
    enableSorting: false,
    cell: ({ row }) => statusOrDash(detailOf(row.original).dctfweb?.payment_status)
  },
  {
    id: 'actions',
    enableHiding: false,
    enableSorting: false,
    meta: { class: { th: 'w-12', td: 'w-12' } },
    cell: ({ row }) => {
      const name = row.original.name || row.original.display_name || `cliente ${row.original.client_id}`
      return h('div', { class: 'text-right' }, h(
        UDropdownMenu,
        {
          items: rowActionItems(row.original),
          content: { align: 'end' }
        },
        () => h(UButton, {
          'icon': 'i-lucide-ellipsis-vertical',
          'color': 'neutral',
          'variant': 'ghost',
          'class': 'ml-auto',
          'aria-label': `Ações de ${name}`
        })
      ))
    }
  }
]
</script>

<template>
  <MonitoringModuleTable
    title="DCTFWeb / MIT"
    panel-id="monitoring-dctfweb"
    module-key="dctfweb"
    :columns="columns"
    :rows="rows"
    :loading="loading"
    :refreshing="refreshing"
    :error="loadError"
    :page="page"
    :last-page="lastPage"
    :total="total"
    :per-page="perPage"
    :filters="filters"
    :filter-config="filterConfig"
    :total-clients="totalClients"
    :counters="counters"
    :last-good-at="lastValidAt"
    :sorting="sorting"
    :get-row-id="getRowId"
    :get-client-id="row => row.client_id"
    :submodule="submodule"
    empty-title="Nenhum cliente"
    :column-labels="{
      closure: 'Encerramento',
      transmission: 'Transmissão',
      receipt: 'Recibo',
      evidence: 'Evidência',
      darf: 'DARF',
      payment: 'Pagamento'
    }"
    :initial-hidden-columns="['evidence', 'darf']"
    @update:page="setPage"
    @update:sorting="sorting = $event"
    @quick-filter-change="applyQuickFilters"
    @apply-filters="applyFilters"
    @reset-filters="resetFilters"
    @refresh="refresh"
  >
    <template #submodules>
      <UTabs
        v-model="submodule"
        :items="tabItems"
        :content="false"
        size="sm"
        data-testid="dctfweb-submodule-tabs"
      />
    </template>

    <template
      v-if="overviewError"
      #utilities
    >
      <UAlert
        color="warning"
        icon="i-lucide-triangle-alert"
        :title="overviewError"
        class="w-full"
      />
    </template>

    <template #detail>
      <FiscalMutationConfirmModal
        v-model:open="mutationOpen"
        :request="mutationRequest"
        :context="{
          effect: submodule === 'MIT' ? 'Encerrar apuração MIT' : 'Transmitir declaração DCTFWeb',
          competence: mutationRequest?.competence_period_key || undefined
        }"
        @success="refresh"
      />
    </template>
  </MonitoringModuleTable>
</template>

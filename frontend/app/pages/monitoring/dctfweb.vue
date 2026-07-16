<script setup lang="ts">
/**
 * DCTFWeb / MIT — tabs + estados independentes (encerramento, transmissão, recibo, evidência, DARF, pagamento).
 * Mutação de alto risco só via FiscalMutationConfirmModal (catálogo de códigos).
 * Task 7.3
 */
import type { TableColumn } from '@nuxt/ui'
import type { DctfwebClientDetail, DctfwebClientRow } from '~/types/fiscal-modules'
import { DCTFWEB_TABS } from '~/types/fiscal-modules'
import { resolveHighRiskCodesFromRow } from '~/utils/fiscal-high-risk'

const FiscalStatusBadge = resolveComponent('FiscalStatusBadge')
const FiscalClientCell = resolveComponent('FiscalClientCell')
const UButton = resolveComponent('UButton')

const route = useRoute()
const { canAccessAdministration } = useDashboard()

function normalizeSubmodule(raw: unknown): string {
  const v = String(raw || 'DCTFWEB').toUpperCase()
  if (v === 'MIT') return 'MIT'
  if (v === 'DCTF' || v === 'DCTFWEB') return 'DCTFWEB'
  return 'DCTFWEB'
}

const submodule = ref(normalizeSubmodule(route.query.submodule || route.query.tab))

const {
  page,
  perPage,
  total,
  lastPage,
  q,
  situation,
  competence,
  clientId,
  loading,
  refreshing,
  loadError,
  overviewError,
  rows,
  counters,
  totalClients,
  lastValidAt,
  refresh,
  selectKpi
} = useFiscalModulePortfolio('dctfweb', { submodule })

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

function clientHref(id: number) {
  return `/monitoring/clients/${id}?tab=overview`
}

function onClientId(id: number | null) {
  clientId.value = id != null && id > 0 ? String(id) : ''
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

const columns: TableColumn<DctfwebClientRow>[] = [
  {
    id: 'client',
    header: 'Cliente',
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
    header: 'Competência',
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
    header: 'Situação',
    cell: ({ row }) => h(FiscalStatusBadge, { status: row.original.situation })
  },
  {
    id: 'closure',
    header: 'Encerramento',
    cell: ({ row }) => {
      // Eixo MIT — independente da transmissão DCTFWeb
      const mit = detailOf(row.original).mit
      return statusOrDash(mit?.encerramento_status)
    }
  },
  {
    id: 'transmission',
    header: 'Transmissão',
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
    cell: ({ row }) => String(detailOf(row.original).dctfweb?.receipt_number || '—')
  },
  {
    id: 'evidence',
    header: 'Evidência',
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
    cell: () => {
      // Contrato da carteira ainda não devolve status de DARF por linha.
      return '—'
    }
  },
  {
    id: 'payment',
    header: 'Pagamento',
    cell: ({ row }) => statusOrDash(detailOf(row.original).dctfweb?.payment_status)
  },
  {
    id: 'actions',
    header: '',
    meta: { class: { th: 'w-40', td: 'w-40' } },
    cell: ({ row }) => {
      const children = [
        h(UButton, {
          size: 'xs',
          color: 'neutral',
          variant: 'ghost',
          label: 'Cliente',
          to: clientHref(row.original.client_id)
        })
      ]
      if (canOpenTransmit(row.original)) {
        children.push(h(UButton, {
          size: 'xs',
          color: 'warning',
          variant: 'ghost',
          label: submodule.value === 'MIT' ? 'Encerrar' : 'Transmitir',
          onClick: () => openTransmit(row.original)
        }))
      }
      return h('div', { class: 'flex justify-end gap-1' }, children)
    }
  }
]
</script>

<template>
  <MonitoringModuleTable
    title="DCTFWeb / MIT"
    panel-id="monitoring-dctfweb"
    :columns="columns"
    :rows="rows"
    :loading="loading"
    :refreshing="refreshing"
    :error="loadError"
    :page="page"
    :last-page="lastPage"
    :total="total"
    :per-page="perPage"
    :q="q"
    :situation="situation"
    :competence="competence"
    :submodule="submodule"
    :total-clients="totalClients"
    :counters="counters"
    :last-good-at="lastValidAt"
    show-competence-filter
    show-client-picker
    empty-title="Nenhum cliente DCTFWeb/MIT"
    @update:page="page = $event"
    @update:q="q = $event"
    @update:situation="situation = $event"
    @update:competence="competence = $event"
    @update:submodule="submodule = $event"
    @update:client-id="onClientId"
    @refresh="refresh"
    @kpi-select="selectKpi"
  >
    <template #navbar-actions>
      <MonitoringPortfolioActions
        module-key="dctfweb"
        :client-id="clientId"
        :competence="competence"
        :situation="situation"
        :q="q"
        :submodule="submodule"
        @refreshed="refresh"
      />
    </template>

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

<script setup lang="ts">
/**
 * Declarações — carteira operacional agregada por cliente.
 * A lista usa somente o contrato do portfolio; a projeção completa é carregada
 * sob demanda, evitando resumo paralelo e N+1 de detalhes.
 */
import type { TableColumn } from '@nuxt/ui'
import type { DeclarationsClientDetail, DeclarationsClientRow, MonitoringFilterConfig } from '~/types/fiscal-modules'
import { sortHeader } from '~/utils/table-sort'
import {
  buildMonitoringConsultedColumn,
  MONITORING_SHARED_COLUMN_LABELS
} from '~/utils/monitoring-table-columns'

const FiscalStatusBadge = resolveComponent('FiscalStatusBadge')
const FiscalClientCell = resolveComponent('FiscalClientCell')
const FiscalDocumentAction = resolveComponent('FiscalDocumentAction')
const UButton = resolveComponent('UButton')
const api = useApi()

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
  dataOrigin,
  dataOriginLabel,
  sourceLabel,
  asOf,
  surface,
  allowsDocument,
  sorting,
  setPage,
  setPerPage,
  refresh,
  applyFilters,
  applyQuickFilters,
  resetFilters
} = useFiscalModulePortfolio('declarations')

const detailOpen = ref(false)
const detailLoading = ref(false)
const detailError = ref<string | null>(null)
const detailProjection = ref<Record<string, unknown> | null>(null)
const detailEvidences = ref<Array<Record<string, unknown>>>([])

const deliveryStatusItems = [
  { label: 'Todas as entregas', value: 'all' },
  { label: 'Desconhecido', value: 'UNKNOWN' },
  { label: 'Pendente', value: 'PENDING' },
  { label: 'Processando', value: 'PROCESSING' },
  { label: 'Em atenção', value: 'ATTENTION' },
  { label: 'Em dia', value: 'UP_TO_DATE' },
  { label: 'Erro', value: 'ERROR' },
  { label: 'Bloqueado', value: 'BLOCKED' },
  { label: 'Não aplicável', value: 'NOT_APPLICABLE' },
  { label: 'Não suportado', value: 'UNSUPPORTED' }
]

const filterConfig: MonitoringFilterConfig = {
  fields: [
    { key: 'situation', kind: 'option', label: 'Situação' },
    { key: 'clientId', kind: 'client', label: 'Cliente' },
    { key: 'competence', kind: 'month', label: 'Competência' },
    { key: 'deliveryStatus', kind: 'option', label: 'Status de entrega', items: deliveryStatusItems }
  ]
}

function getRowId(row: DeclarationsClientRow) {
  return `c:${row.client_id}`
}

function clientHref(id: number) {
  return `/monitoring/clients/${id}/declarations`
}

function detailOf(row: DeclarationsClientRow): DeclarationsClientDetail {
  return row.detail || {}
}

function applicabilityLabel(code?: string | null) {
  const map: Record<string, string> = {
    APPLICABLE: 'Aplicável',
    NOT_APPLICABLE: 'Não aplicável',
    UNKNOWN: 'Desconhecida',
    UNSUPPORTED: 'Não suportada'
  }
  const k = String(code || '').toUpperCase()
  return map[k] || (k || '—')
}

async function openProjection(row: DeclarationsClientRow) {
  const id = detailOf(row).next_projection_id
  if (!id) return
  detailOpen.value = true
  detailLoading.value = true
  detailError.value = null
  detailProjection.value = null
  detailEvidences.value = []
  try {
    const res = await api.fiscal.declarations.get(id)
    const data = (res.data || {}) as Record<string, unknown>
    detailProjection.value = data
    detailEvidences.value = Array.isArray(data.evidences)
      ? (data.evidences as Array<Record<string, unknown>>)
      : []
  } catch (caught) {
    detailError.value = apiErrorMessage(caught, 'Falha ao carregar projeção de declaração.')
  } finally {
    detailLoading.value = false
  }
}

const columns: TableColumn<DeclarationsClientRow>[] = [
  {
    id: 'client',
    header: ({ column }) => sortHeader('Cliente', column),
    enableHiding: false,
    meta: { class: { th: 'min-w-48 w-full', td: 'min-w-48 w-full overflow-hidden' } },
    cell: ({ row }) => h(FiscalClientCell, {
      clientId: row.original.client_id,
      name: row.original.name || row.original.display_name,
      legalName: row.original.legal_name,
      cnpjMasked: row.original.cnpj_masked,
      to: clientHref(row.original.client_id)
    })
  },
  {
    id: 'obligation',
    header: 'Obrigação',
    enableSorting: false,
    cell: ({ row }) => {
      const d = detailOf(row.original)
      return String(d.next_obligation_code || '—')
    }
  },
  {
    id: 'competence',
    header: ({ column }) => sortHeader('Competência', column),
    cell: ({ row }) => String(
      row.original.competence
      || detailOf(row.original).next_period_key
      || '—'
    )
  },
  {
    id: 'due',
    header: 'Vencimento',
    enableSorting: false,
    cell: ({ row }) => formatDateTime(
      String(
        detailOf(row.original).next_due_at
        || row.original.next_deadline_at
        || ''
      ) || null
    )
  },
  {
    id: 'delivery',
    header: 'Entrega',
    enableSorting: false,
    cell: ({ row }) => {
      const status = String(
        detailOf(row.original).next_delivery_status
        || '—'
      )
      return status === '—' ? '—' : h(FiscalStatusBadge, { fill: true, status })
    }
  },
  {
    id: 'open',
    header: 'Abertas',
    enableSorting: false,
    cell: ({ row }) => String(detailOf(row.original).open_count ?? '—')
  },
  {
    id: 'situation',
    header: ({ column }) => sortHeader('Situação', column),
    cell: ({ row }) => h(FiscalStatusBadge, { fill: true, status: String(
      detailOf(row.original).next_situation
      || row.original.situation
    ) })
  },
  buildMonitoringConsultedColumn<DeclarationsClientRow>({
    getAt: row => row.last_consulted_at || row.last_snapshot_at,
    format: 'datetime',
    testId: 'declarations-last-consulted'
  }),
  {
    id: 'actions',
    header: 'Ações',
    enableHiding: false,
    enableSorting: false,
    meta: { class: { th: 'w-28', td: 'w-28' } },
    cell: ({ row }) => {
      const children = [
        h(FiscalDocumentAction, {
          document: row.original.document,
          disabled: !allowsDocument.value
        }),
        h(UButton, {
          'size': 'xs',
          'color': 'neutral',
          'variant': 'ghost',
          'icon': 'i-lucide-building-2',
          'aria-label': `Abrir cliente ${row.original.client_id}`,
          'to': clientHref(row.original.client_id)
        })
      ]
      if (detailOf(row.original).next_projection_id) {
        children.unshift(h(UButton, {
          'size': 'xs',
          'color': 'primary',
          'variant': 'ghost',
          'icon': 'i-lucide-panel-right-open',
          'aria-label': `Abrir projeção do cliente ${row.original.client_id}`,
          'onClick': () => openProjection(row.original)
        }))
      }
      return h('div', { class: 'flex justify-end gap-1 items-center' }, children)
    }
  }
]
</script>

<template>
  <MonitoringModuleTable
    title="Declarações"
    panel-id="monitoring-declarations"
    module-key="declarations"
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
    :data-origin="dataOrigin"
    :data-origin-label="dataOriginLabel"
    :source-label="sourceLabel"
    :as-of="asOf"
    :surface-summary="surface"
    :sorting="sorting"
    :get-row-id="getRowId"
    :get-client-id="row => row.client_id"
    :horizontal-scroll="true"
    empty-title="Nenhuma declaração"
    :column-labels="{
      obligation: 'Obrigação',
      due: 'Vencimento',
      delivery: 'Entrega',
      open: 'Abertas',
      ...MONITORING_SHARED_COLUMN_LABELS
    }"
    @update:page="setPage"
    @update:per-page="setPerPage"
    @update:sorting="sorting = $event"
    @quick-filter-change="applyQuickFilters"
    @apply-filters="applyFilters"
    @reset-filters="resetFilters"
    @refresh="refresh"
  >
    <template #utilities>
      <UAlert
        v-if="overviewError"
        color="warning"
        icon="i-lucide-triangle-alert"
        :title="overviewError"
        class="w-full"
      />
    </template>

    <template #detail>
      <USlideover
        v-model:open="detailOpen"
        title="Projeção de declaração"
      >
        <template #body>
          <div
            v-if="detailLoading"
            class="py-8 text-sm text-muted"
          >
            Carregando projeção…
          </div>
          <UAlert
            v-else-if="detailError"
            color="error"
            :title="detailError"
          />
          <div
            v-else-if="detailProjection"
            class="flex flex-col gap-4"
          >
            <dl class="grid gap-2 text-sm sm:grid-cols-2">
              <div>
                <dt class="text-muted">
                  Obrigação
                </dt>
                <dd class="font-medium">
                  {{ detailProjection.obligation_code || detailProjection.obligation_name || '—' }}
                </dd>
              </div>
              <div>
                <dt class="text-muted">
                  Aplicabilidade
                </dt>
                <dd>
                  <UBadge
                    color="neutral"
                    variant="subtle"
                    size="sm"
                  >
                    {{ applicabilityLabel(String(detailProjection.applicability || '')) }}
                  </UBadge>
                </dd>
              </div>
              <div>
                <dt class="text-muted">
                  Competência
                </dt>
                <dd class="font-medium">
                  {{ detailProjection.period_key || '—' }}
                </dd>
              </div>
              <div>
                <dt class="text-muted">
                  Vencimento
                </dt>
                <dd class="font-medium">
                  {{ formatDateTime(String(detailProjection.due_at || '') || null) }}
                </dd>
              </div>
              <div>
                <dt class="text-muted">
                  Entrega
                </dt>
                <dd>
                  <FiscalStatusBadge
                    v-if="detailProjection.delivery_status"
                    :status="String(detailProjection.delivery_status)"
                    show-hint
                  />
                  <span v-else>—</span>
                </dd>
              </div>
              <div>
                <dt class="text-muted">
                  Situação
                </dt>
                <dd>
                  <FiscalStatusBadge
                    v-if="detailProjection.situation"
                    :status="String(detailProjection.situation)"
                  />
                  <span v-else>—</span>
                </dd>
              </div>
              <div>
                <dt class="text-muted">
                  Evidência conclusiva
                </dt>
                <dd class="font-medium">
                  {{ detailProjection.conclusive_evidence_id ? `#${detailProjection.conclusive_evidence_id}` : '—' }}
                </dd>
              </div>
              <div>
                <dt class="text-muted">
                  Artefato
                </dt>
                <dd class="font-medium">
                  {{ detailProjection.evidence_artifact_id ? `#${detailProjection.evidence_artifact_id}` : '—' }}
                </dd>
              </div>
            </dl>
            <p
              v-if="detailProjection.applicability_basis"
              class="text-xs text-muted"
            >
              Base: {{ detailProjection.applicability_basis }}
            </p>
            <div>
              <h3 class="mb-2 text-sm font-medium">
                Evidências
              </h3>
              <div
                v-if="!detailEvidences.length"
                class="text-sm text-muted"
              >
                Nenhuma evidência anexada retornada.
              </div>
              <ul
                v-else
                class="divide-y divide-default text-sm"
              >
                <li
                  v-for="ev in detailEvidences"
                  :key="String(ev.id)"
                  class="flex items-center justify-between gap-2 py-2"
                >
                  <span>
                    #{{ ev.id }}
                    · {{ ev.kind || ev.source || 'evidência' }}
                    · {{ formatDateTime(String(ev.observed_at || ev.created_at || '') || null) }}
                  </span>
                  <FiscalStatusBadge
                    v-if="ev.status || ev.situation"
                    :status="String(ev.status || ev.situation)"
                  />
                </li>
              </ul>
            </div>
            <UButton
              v-if="detailProjection.client_id"
              size="sm"
              color="neutral"
              variant="outline"
              label="Painel do cliente"
              :to="clientHref(Number(detailProjection.client_id))"
            />
          </div>
        </template>
      </USlideover>
    </template>
  </MonitoringModuleTable>
</template>

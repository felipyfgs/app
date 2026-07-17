<script setup lang="ts">
/**
 * Simples Nacional / MEI — carteira via MonitoringModuleTable.
 * PGDAS-D: renderer especializado (colunas da spec); demais submódulos: tabela genérica.
 */
import type { TableColumn } from '@nuxt/ui'
import type {
  MonitoringFilterConfig,
  PgdasdCommunicationPreference,
  SimplesMeiClientRow
} from '~/types/fiscal-modules'
import { SIMPLES_MEI_TABS } from '~/types/fiscal-modules'
import { buildPgdasdColumns } from '~/utils/pgdasd-table'
import { sortHeader } from '~/utils/table-sort'

const FiscalStatusBadge = resolveComponent('FiscalStatusBadge')
const FiscalClientCell = resolveComponent('FiscalClientCell')
const FiscalCoverageBadge = resolveComponent('FiscalCoverageBadge')
const FiscalDocumentAction = resolveComponent('FiscalDocumentAction')
const UButton = resolveComponent('UButton')

const { canManageClients, canTriggerSync } = useDashboard()

// Tab local (PGDASD default). URL permanece /monitoring/simples-mei — sem query/path de tab.
const submodule = ref(normalizeMonitoringSubmodule('simples_mei', undefined))

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
  refresh,
  applyFilters,
  applyQuickFilters,
  resetFilters
} = useFiscalModulePortfolio('simples_mei', {
  submodule
})

const isPgdasd = computed(() => {
  const s = String(submodule.value || '').toLowerCase()
  return s === 'pgdasd' || s === 'pgdas-d' || s === 'simples'
})

const filterConfig: MonitoringFilterConfig = {
  fields: [
    { key: 'situation', kind: 'option', label: 'Situação' },
    { key: 'clientId', kind: 'client', label: 'Cliente' },
    { key: 'competence', kind: 'month', label: 'Competência' },
    {
      key: 'coverage',
      kind: 'option',
      label: 'Cobertura',
      items: fiscalCoverageFilterItems()
    }
  ]
}

function getRowId(row: SimplesMeiClientRow) {
  return `c:${row.client_id}`
}

const tabItems = SIMPLES_MEI_TABS.map(t => ({ label: t.label, value: t.value }))

function clientHref(clientId: number) {
  return `/monitoring/clients/${clientId}`
}

// —— Modais PGDAS-D ——
const historyOpen = ref(false)
const previewOpen = ref(false)
const trackingOpen = ref(false)
const prefsOpen = ref(false)
const modalClientId = ref<number | null>(null)
const modalClientName = ref<string | null>(null)
const modalCnpjMasked = ref<string | null>(null)
const modalPreference = ref<PgdasdCommunicationPreference | null>(null)

function openFor(row: SimplesMeiClientRow, kind: 'history' | 'preview' | 'tracking' | 'prefs') {
  modalClientId.value = row.client_id
  modalClientName.value = row.legal_name || row.name || null
  modalCnpjMasked.value = row.cnpj_masked || null
  modalPreference.value = pgdasdSummary(row)?.communication || null
  historyOpen.value = kind === 'history'
  previewOpen.value = kind === 'preview'
  trackingOpen.value = kind === 'tracking'
  prefsOpen.value = kind === 'prefs'
}

function onPreferenceSaved(
  row: SimplesMeiClientRow,
  preference: PgdasdCommunicationPreference
) {
  if (row.detail.pgdasd) row.detail.pgdasd.communication = preference
  row.detail.communication = preference
  if (modalClientId.value === row.client_id) modalPreference.value = preference
  void refresh()
}

const genericColumns: TableColumn<SimplesMeiClientRow>[] = [
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
      const d = row.original.detail
      return String(row.original.competence || d?.period_key || '—')
    }
  },
  {
    id: 'obligation',
    header: 'Obrigação / submódulo',
    enableSorting: false,
    cell: ({ row }) => {
      const d = row.original.detail
      const sub = d?.submodule || submodule.value || '—'
      const action = row.original.next_action
      return action ? `${sub} · ${action}` : String(sub)
    }
  },
  {
    id: 'situation',
    header: ({ column }) => sortHeader('Situação', column),
    cell: ({ row }) => h(FiscalStatusBadge, { status: row.original.situation })
  },
  {
    id: 'coverage',
    header: 'Cobertura',
    enableSorting: false,
    cell: ({ row }) => h(FiscalCoverageBadge, { coverage: row.original.coverage })
  },
  {
    id: 'guide',
    header: 'Guia',
    enableSorting: false,
    cell: ({ row }) => {
      const hasGuideHint = Boolean(row.original.next_action || row.original.next_deadline_at)
      return hasGuideHint
        ? h(UButton, {
            size: 'xs',
            color: 'neutral',
            variant: 'ghost',
            label: 'Ver guias',
            to: `/monitoring/clients/${row.original.client_id}/guides`
          })
        : '—'
    }
  },
  {
    id: 'next',
    header: 'Próximo prazo',
    enableSorting: false,
    cell: ({ row }) => formatDateTime(row.original.next_deadline_at)
  },
  {
    id: 'consulted',
    header: ({ column }) => sortHeader('Última consulta', column),
    cell: ({ row }) => formatDateTime(row.original.last_consulted_at)
  },
  {
    id: 'actions',
    header: 'Ações',
    enableHiding: false,
    enableSorting: false,
    meta: { class: { th: 'w-48', td: 'w-48' } },
    cell: ({ row }) => h('div', { class: 'flex justify-end gap-1 items-center' }, [
      h(FiscalDocumentAction, {
        document: row.original.document,
        disabled: !allowsDocument.value
      }),
      h(UButton, {
        size: 'xs',
        color: 'neutral',
        variant: 'ghost',
        label: 'Cliente',
        to: clientHref(row.original.client_id)
      })
    ])
  }
]

const pgdasdColumns = computed(() => buildPgdasdColumns({
  canManage: canManageClients.value,
  onHistory: row => openFor(row, 'history'),
  onPreview: row => openFor(row, 'preview'),
  onTracking: row => openFor(row, 'tracking'),
  onConfigure: row => openFor(row, 'prefs'),
  onPreferenceSaved
}))

const columns = computed(() => isPgdasd.value ? pgdasdColumns.value : genericColumns)
</script>

<template>
  <MonitoringModuleTable
    title="Simples Nacional / MEI"
    panel-id="monitoring-simples-mei"
    module-key="simples_mei"
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
    :submodule="submodule"
    :selection-enabled="isPgdasd ? canManageClients : undefined"
    :custom-bulk-actions="isPgdasd"
    :horizontal-scroll="isPgdasd"
    :table-class="isPgdasd ? 'min-w-[1120px]' : undefined"
    empty-title="Nenhum cliente"
    :column-labels="isPgdasd
      ? {
          last_declaration: 'Última declaração',
          rbt12: 'RBT12',
          send: 'Enviar',
          automatic: 'Automático',
          tracking: 'Rastreio',
          consulted: 'Última consulta',
          details: 'Detalhes'
        }
      : {
          obligation: 'Obrigação / submódulo',
          guide: 'Guia',
          next: 'Próximo prazo',
          consulted: 'Última consulta'
        }"
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
        class="w-auto max-w-full"
        data-testid="simples-mei-submodule-tabs"
      />
    </template>

    <template
      v-if="isPgdasd"
      #bulk-actions="{ selectedClientIds, selectedCount, clearSelection }"
    >
      <MonitoringPgdasdBulkAutomaticActions
        v-if="canManageClients"
        :selected-client-ids="selectedClientIds"
        :selected-count="selectedCount"
        @clear="clearSelection"
        @refresh="refresh"
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
      >
        <template #actions>
          <UButton
            size="xs"
            color="neutral"
            variant="outline"
            label="Retry overview"
            @click="refresh"
          />
        </template>
      </UAlert>
    </template>
  </MonitoringModuleTable>

  <MonitoringPgdasdHistoryModal
    v-if="isPgdasd"
    v-model:open="historyOpen"
    :client-id="modalClientId"
    :client-name="modalClientName"
    :cnpj-masked="modalCnpjMasked"
    :can-collect-documents="canTriggerSync"
  />
  <MonitoringPgdasdCommunicationModals
    v-if="isPgdasd"
    v-model:preview-open="previewOpen"
    v-model:tracking-open="trackingOpen"
    v-model:prefs-open="prefsOpen"
    :client-id="modalClientId"
    :client-name="modalClientName"
    :preference="modalPreference"
    :can-manage="canManageClients"
    @saved="(preference) => {
      const row = rows.find(item => item.client_id === modalClientId)
      if (row) onPreferenceSaved(row, preference)
    }"
  />
</template>

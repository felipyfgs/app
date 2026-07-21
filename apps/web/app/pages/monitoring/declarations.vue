<script setup lang="ts">
/**
 * Declarações — hub por obrigação (abas locais; URL fixa /monitoring/declarations).
 * Default PGDAS; DIRF unsupported honesto; FGTS cobertura parcial.
 */
import type { DeclarationsClientRow, MonitoringFilterConfig } from '~/types/fiscal-modules'
import {
  DECLARATIONS_TABS,
  declarationsSurfaceTitle,
  normalizeDeclarationsSubmodule
} from '~/types/fiscal-modules'
import {
  buildDeclarationsFgtsColumns,
  buildDeclarationsObligationColumns,
  buildDeclarationsPgdasColumns,
  DECLARATIONS_PGDAS_COLUMN_LABELS
} from '~/utils/declarations-table'
import { MONITORING_SHARED_COLUMN_LABELS } from '~/utils/monitoring-table-columns'

const submodule = ref(normalizeDeclarationsSubmodule('PGDAS'))

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
  sorting,
  setPage,
  setPerPage,
  refresh,
  applyFilters,
  applyQuickFilters,
  resetFilters
} = useFiscalModulePortfolio('declarations', {
  submodule
})

const { canManageClients } = useDashboard()
const {
  formOpen: clientFormOpen,
  formClient,
  canManageCredentials,
  openEditClient,
  onFormSaved: onClientFormSaved
} = useMonitoringClientEdit(() => refresh())

const isPgdas = computed(() => submodule.value === 'PGDAS')
const isDctfweb = computed(() => submodule.value === 'DCTFWEB')
const isDefis = computed(() => submodule.value === 'DEFIS')
const isFgts = computed(() => submodule.value === 'FGTS')
const isDirf = computed(() => submodule.value === 'DIRF')

const surfaceTitle = computed(() => declarationsSurfaceTitle(submodule.value))

const tabItems = DECLARATIONS_TABS.map(t => ({ label: t.label, value: t.value }))

const filterConfig = computed<MonitoringFilterConfig>(() => {
  if (isDirf.value) {
    return { fields: [] }
  }
  if (isFgts.value) {
    return {
      fields: [
        { key: 'situation', kind: 'option', label: 'Situação' },
        { key: 'clientId', kind: 'client', label: 'Cliente' },
        { key: 'competence', kind: 'month', label: 'Competência' }
      ]
    }
  }
  return {
    fields: [
      { key: 'situation', kind: 'option', label: 'Situação' },
      { key: 'clientId', kind: 'client', label: 'Cliente' },
      { key: 'competence', kind: 'month', label: 'Competência' }
    ]
  }
})

function getRowId(row: DeclarationsClientRow) {
  return `c:${row.client_id}`
}

// —— Modais ——
const pgdasHistoryOpen = ref(false)
const dctfwebHistoryOpen = ref(false)
const defisHistoryOpen = ref(false)
const modalClientId = ref<number | null>(null)
const modalClientName = ref<string | null>(null)
const modalCnpjMasked = ref<string | null>(null)

function closeModals() {
  pgdasHistoryOpen.value = false
  dctfwebHistoryOpen.value = false
  defisHistoryOpen.value = false
  modalClientId.value = null
  modalClientName.value = null
  modalCnpjMasked.value = null
}

function openModalClient(row: DeclarationsClientRow) {
  modalClientId.value = row.client_id
  modalClientName.value = row.legal_name || row.name || null
  modalCnpjMasked.value = row.cnpj_masked || null
}

function openPgdasHistory(row: DeclarationsClientRow) {
  openModalClient(row)
  pgdasHistoryOpen.value = true
}

function openDctfwebHistory(row: DeclarationsClientRow) {
  openModalClient(row)
  dctfwebHistoryOpen.value = true
}

function openDefisHistory(row: DeclarationsClientRow) {
  openModalClient(row)
  defisHistoryOpen.value = true
}

const columns = computed(() => {
  const onEdit = canManageClients.value
    ? (row: DeclarationsClientRow) => { void openEditClient(row.client_id) }
    : undefined
  if (isPgdas.value) {
    return buildDeclarationsPgdasColumns({ onHistory: openPgdasHistory, onEditClient: onEdit })
  }
  if (isDctfweb.value) {
    return buildDeclarationsObligationColumns({
      onHistory: openDctfwebHistory,
      onEditClient: onEdit,
      historyLabel: 'Histórico'
    })
  }
  if (isDefis.value) {
    return buildDeclarationsObligationColumns({
      onHistory: openDefisHistory,
      onEditClient: onEdit,
      historyLabel: 'Histórico DEFIS'
    })
  }
  if (isFgts.value) {
    return buildDeclarationsFgtsColumns({ onEditClient: onEdit })
  }
  // DIRF: colunas mínimas (tabela vazia + empty unsupported)
  return buildDeclarationsObligationColumns({ onEditClient: onEdit })
})

const columnLabels = computed(() => {
  if (isPgdas.value) return { ...DECLARATIONS_PGDAS_COLUMN_LABELS }
  if (isFgts.value) {
    return {
      competence: 'Competência',
      closure: 'Fechamento',
      totalization: 'Totalização',
      coverage: 'Cobertura',
      ...MONITORING_SHARED_COLUMN_LABELS
    }
  }
  return {
    obligation: 'Obrigação',
    ...MONITORING_SHARED_COLUMN_LABELS
  }
})

const emptyTitle = computed(() => {
  if (isDirf.value) return 'DIRF não suportada'
  if (isFgts.value) return 'Nenhum status FGTS na carteira'
  if (isPgdas.value) return 'Nenhuma declaração PGDAS'
  if (isDctfweb.value) return 'Nenhuma declaração DCTFWeb'
  if (isDefis.value) return 'Nenhuma declaração DEFIS'
  return 'Nenhuma declaração'
})

const emptyKind = computed(() => (isDirf.value ? 'unsupported' as const : null))

watch(submodule, (next, prev) => {
  if (next === prev) return
  closeModals()
  setPage(1)
  resetFilters()
})
</script>

<template>
  <MonitoringModuleTable
    :title="surfaceTitle"
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
    :submodule="submodule"
    :horizontal-scroll="false"
    :empty-title="emptyTitle"
    :empty-kind="emptyKind"
    :column-labels="columnLabels"
    @update:page="setPage"
    @update:per-page="setPerPage"
    @update:sorting="sorting = $event"
    @quick-filter-change="applyQuickFilters"
    @apply-filters="applyFilters"
    @reset-filters="resetFilters"
    @refresh="refresh"
  >
    <template #submodules>
      <div
        class="flex min-w-0 flex-col gap-2"
        data-testid="declarations-obligation-control"
      >
        <p class="text-xs font-medium text-muted">
          Obrigação
        </p>
        <ShellScrollableTabs
          v-model="submodule"
          :items="tabItems"
          size="sm"
          color="primary"
          variant="pill"
          class="w-full min-w-0"
          aria-label="Obrigação: PGDAS, DCTFWeb, FGTS, DEFIS ou DIRF"
          test-id="declarations-submodule-tabs"
        />
      </div>
    </template>

    <template #utilities>
      <UAlert
        v-if="overviewError"
        color="warning"
        icon="i-lucide-triangle-alert"
        :title="overviewError"
        class="w-full"
      />
      <UAlert
        v-if="isDirf"
        color="neutral"
        variant="subtle"
        icon="i-lucide-ban"
        title="DIRF não suportada"
        description="Não há catálogo nem integração SERPRO para DIRF nesta superfície. A carteira permanece vazia sem dados inventados."
        class="w-full"
        data-testid="declarations-dirf-unsupported"
      />
      <UAlert
        v-else-if="isFgts"
        color="warning"
        variant="subtle"
        icon="i-lucide-triangle-alert"
        title="Cobertura parcial FGTS"
        description="Esta aba lista status FGTS já observados. Guia e pagamento produtivos não são inventados aqui."
        class="w-full"
        data-testid="declarations-fgts-partial"
      />
    </template>
  </MonitoringModuleTable>

  <MonitoringPgdasdDasHistoryModal
    v-if="isPgdas"
    v-model:open="pgdasHistoryOpen"
    :client-id="modalClientId"
    :client-name="modalClientName"
    :cnpj-masked="modalCnpjMasked"
  />
  <MonitoringDctfwebHistoryModal
    v-if="isDctfweb"
    v-model:open="dctfwebHistoryOpen"
    :client-id="modalClientId"
    :client-name="modalClientName"
    :cnpj-masked="modalCnpjMasked"
  />
  <MonitoringDefisDeclarationsModal
    v-if="isDefis"
    v-model:open="defisHistoryOpen"
    :client-id="modalClientId"
    :client-name="modalClientName"
  />

  <ClientsClientFormModal
    v-if="canManageClients"
    v-model:open="clientFormOpen"
    :client="formClient"
    :can-manage-credentials="canManageCredentials"
    :can-manage-clients="canManageClients"
    @saved="onClientFormSaved"
  />
</template>

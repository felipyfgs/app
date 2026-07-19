<script setup lang="ts">
/**
 * DCTFWeb / MIT — cápsulas independentes na mesma rota.
 * DCTFWeb: oito colunas fixas, comunicação template, histórico local.
 * MIT: renderer próprio; sem reutilizar colunas DCTFWeb.
 * Mutações fiscais (transmitir / encerrar / DARF) ficam fora da grade DCTFWeb.
 */
import type {
  DctfwebClientRow,
  MonitoringFilterConfig,
  PgdasdCommunicationPreference
} from '~/types/fiscal-modules'
import { DCTFWEB_TABS } from '~/types/fiscal-modules'
import { buildDctfwebColumns, buildMitColumns } from '~/utils/dctfweb-table'
import { dctfwebSummary, isDctfwebCapsule, isMitCapsule } from '~/utils/dctfweb'
import { MONITORING_SHARED_COLUMN_LABELS } from '~/utils/monitoring-table-columns'

const { canManageClients, canTriggerSync } = useDashboard()

// Tab local (DCTFWEB default). URL permanece /monitoring/dctfweb.
const submodule = ref(normalizeMonitoringSubmodule('dctfweb', undefined))

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
} = useFiscalModulePortfolio('dctfweb', {
  submodule
})

const isDctfweb = computed(() => isDctfwebCapsule(submodule.value))
const isMit = computed(() => isMitCapsule(submodule.value))

const filterConfig = computed<MonitoringFilterConfig>(() => {
  if (isMit.value) {
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

function getRowId(row: DctfwebClientRow) {
  return `c:${row.client_id}`
}

const tabItems = DCTFWEB_TABS.map(t => ({ label: t.label, value: t.value }))

// —— Modais locais (histórico / prévia / rastreio / preferências) ——
const historyOpen = ref(false)
const previewOpen = ref(false)
const trackingOpen = ref(false)
const prefsOpen = ref(false)
const mitListOpen = ref(false)
const modalClientId = ref<number | null>(null)
const modalClientName = ref<string | null>(null)
const modalCnpjMasked = ref<string | null>(null)
const modalPreference = ref<PgdasdCommunicationPreference | null>(null)

function openFor(row: DctfwebClientRow, kind: 'history' | 'preview' | 'tracking' | 'prefs') {
  modalClientId.value = row.client_id
  modalClientName.value = row.legal_name || row.name || null
  modalCnpjMasked.value = row.cnpj_masked || null
  modalPreference.value = dctfwebSummary(row)?.communication || null
  historyOpen.value = kind === 'history'
  previewOpen.value = kind === 'preview'
  trackingOpen.value = kind === 'tracking'
  prefsOpen.value = kind === 'prefs'
}

function onPreferenceSaved(
  row: DctfwebClientRow,
  preference: PgdasdCommunicationPreference
) {
  if (row.detail.dctfweb) {
    row.detail.dctfweb.communication = preference
  }
  row.detail.communication = preference
  if (modalClientId.value === row.client_id) modalPreference.value = preference
  void refresh()
}

function onModalPreferenceSaved(preference: PgdasdCommunicationPreference) {
  const row = rows.value.find(item => item.client_id === modalClientId.value)
  if (row) onPreferenceSaved(row, preference)
}

const dctfwebColumns = computed(() => buildDctfwebColumns({
  canManage: canManageClients.value,
  onHistory: row => openFor(row, 'history'),
  onPreview: row => openFor(row, 'preview'),
  onTracking: row => openFor(row, 'tracking'),
  onConfigure: row => openFor(row, 'prefs'),
  onPreferenceSaved
}))

const mitColumns = computed(() => buildMitColumns({
  onOpenClient: (row) => {
    navigateTo(`/monitoring/clients/${row.client_id}`)
  },
  onListApuracoes: (row) => {
    modalClientId.value = row.client_id
    modalClientName.value = row.legal_name || row.name || null
    modalCnpjMasked.value = row.cnpj_masked || null
    mitListOpen.value = true
  }
}))

const columns = computed(() => {
  if (isMit.value) return mitColumns.value
  return dctfwebColumns.value
})

watch(submodule, (next, prev) => {
  if (next === prev) return
  historyOpen.value = false
  previewOpen.value = false
  trackingOpen.value = false
  prefsOpen.value = false
  mitListOpen.value = false
  modalClientId.value = null
  setPage(1)
  resetFilters()
})
</script>

<template>
  <MonitoringModuleTable
    title="DCTFWeb"
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
    :data-origin="dataOrigin"
    :data-origin-label="dataOriginLabel"
    :source-label="sourceLabel"
    :as-of="asOf"
    :surface-summary="surface"
    :sorting="sorting"
    :get-row-id="getRowId"
    :get-client-id="row => row.client_id"
    :submodule="submodule"
    :selection-enabled="canManageClients"
    :initial-hidden-columns="['evidence', 'darf']"
    :horizontal-scroll="true"
    empty-title="Nenhum cliente"
    :column-labels="isMit
      ? {
        period: 'Competência',
        situation: 'Situação',
        closure: 'Encerramento',
        lista_apuracoes_317: 'Apurações 317'
      }
      : {
        situation: 'Situação',
        last_declaration: 'Últ. Declaração',
        ...MONITORING_SHARED_COLUMN_LABELS,
        client: 'Cliente'
      }"
    @update:page="setPage"
    @update:per-page="setPerPage"
    @update:sorting="sorting = $event"
    @quick-filter-change="applyQuickFilters"
    @apply-filters="applyFilters"
    @reset-filters="resetFilters"
    @refresh="refresh"
  >
    <template #submodules>
      <!-- Controle segmentado local; não compete com as tabs de rota acima. -->
      <div
        class="flex min-w-0 flex-col gap-2"
        data-testid="dctfweb-capsule-control"
      >
        <p class="text-xs font-medium text-muted">
          Declaração
        </p>
        <ShellScrollableTabs
          v-model="submodule"
          :items="tabItems"
          size="sm"
          color="primary"
          variant="pill"
          class="w-full min-w-0"
          aria-label="Declaração: DCTFWeb ou MIT"
          test-id="dctfweb-submodule-tabs"
        />
      </div>
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
  </MonitoringModuleTable>

  <MonitoringDctfwebHistoryModal
    v-if="isDctfweb"
    v-model:open="historyOpen"
    :client-id="modalClientId"
    :client-name="modalClientName"
    :cnpj-masked="modalCnpjMasked"
  />
  <MonitoringPgdasdCommunicationModals
    v-if="isDctfweb"
    v-model:preview-open="previewOpen"
    v-model:tracking-open="trackingOpen"
    v-model:prefs-open="prefsOpen"
    :client-id="modalClientId"
    :client-name="modalClientName"
    :preference="modalPreference"
    :can-manage="canManageClients"
    context="DCTFWEB"
    @saved="onModalPreferenceSaved"
  />
  <MonitoringMitListaApuracoesModal
    v-if="isMit"
    v-model:open="mitListOpen"
    :client-id="modalClientId"
    :client-name="modalClientName"
    :cnpj-masked="modalCnpjMasked"
    :can-consult="canTriggerSync"
  />
</template>

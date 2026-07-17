<script setup lang="ts">
/**
 * Simples Nacional / MEI — cápsulas locais PGDAS-D e PGMEI.
 * Rota canônica única; troca de cápsula reseta paginação/filtros exclusivos.
 */
import type {
  MonitoringFilterConfig,
  PgdasdCommunicationPreference,
  SimplesMeiClientRow
} from '~/types/fiscal-modules'
import { SIMPLES_MEI_TABS } from '~/types/fiscal-modules'
import { buildPgdasdColumns } from '~/utils/pgdasd-table'
import { buildPgmeiColumns } from '~/utils/pgmei-table'
import { pgdasdSummary } from '~/utils/pgdasd'
import { pgmeiSummary } from '~/utils/pgmei'

const { canManageClients, canTriggerSync } = useDashboard()

// Tab local (PGDASD default). URL permanece /monitoring/simples-mei.
const submodule = ref(normalizeMonitoringSubmodule('simples_mei', undefined))

// Filtro anual PGMEI (ano corrente no fuso local do browser).
const pgmeiYear = ref(new Date().getFullYear())

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
  refresh,
  applyFilters,
  applyQuickFilters,
  resetFilters
} = useFiscalModulePortfolio('simples_mei', {
  submodule,
  year: computed(() => isPgmeiCapsule(submodule.value) ? pgmeiYear.value : null)
})

function isPgmeiCapsule(value: string | undefined | null): boolean {
  const s = String(value || '').toLowerCase()
  return s === 'pgmei' || s === 'mei'
}

const isPgdasd = computed(() => {
  const s = String(submodule.value || '').toLowerCase()
  return s === 'pgdasd' || s === 'pgdas-d' || s === 'simples'
})

const isPgmei = computed(() => isPgmeiCapsule(submodule.value))

const filterConfig = computed<MonitoringFilterConfig>(() => {
  if (isPgmei.value) {
    return {
      fields: [
        { key: 'situation', kind: 'option', label: 'Situação' },
        { key: 'clientId', kind: 'client', label: 'Cliente' },
        {
          key: 'coverage',
          kind: 'option',
          label: 'Cobertura',
          items: fiscalCoverageFilterItems()
        }
      ]
    }
  }
  return {
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
})

function getRowId(row: SimplesMeiClientRow) {
  return `c:${row.client_id}`
}

const tabItems = SIMPLES_MEI_TABS.map((t: { label: string, badge: string, value: string }) => ({
  label: `${t.label} · ${t.badge}`,
  value: t.value
}))

// —— Modais compartilhados por cápsula ——
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
  if (isPgmei.value) {
    modalPreference.value = pgmeiSummary(row, pgmeiYear.value)?.communication || null
  } else {
    modalPreference.value = pgdasdSummary(row)?.communication || null
  }
  historyOpen.value = kind === 'history'
  previewOpen.value = kind === 'preview'
  trackingOpen.value = kind === 'tracking'
  prefsOpen.value = kind === 'prefs'
}

function onPreferenceSaved(
  row: SimplesMeiClientRow,
  preference: PgdasdCommunicationPreference
) {
  if (isPgmei.value) {
    if (row.detail.pgmei) row.detail.pgmei.communication = preference
  } else if (row.detail.pgdasd) {
    row.detail.pgdasd.communication = preference
  }
  row.detail.communication = preference
  if (modalClientId.value === row.client_id) modalPreference.value = preference
  void refresh()
}

function onModalPreferenceSaved(preference: PgdasdCommunicationPreference) {
  const row = rows.value.find(item => item.client_id === modalClientId.value)
  if (row) onPreferenceSaved(row, preference)
}

const pgdasdColumns = computed(() => buildPgdasdColumns({
  canManage: canManageClients.value,
  onHistory: row => openFor(row, 'history'),
  onPreview: row => openFor(row, 'preview'),
  onTracking: row => openFor(row, 'tracking'),
  onConfigure: row => openFor(row, 'prefs'),
  onPreferenceSaved
}))

const pgmeiColumns = computed(() => buildPgmeiColumns({
  year: pgmeiYear.value,
  canManage: canManageClients.value,
  onHistory: row => openFor(row, 'history'),
  onPreview: row => openFor(row, 'preview'),
  onTracking: row => openFor(row, 'tracking'),
  onConfigure: row => openFor(row, 'prefs'),
  onPreferenceSaved
}))

const columns = computed(() => {
  if (isPgmei.value) return pgmeiColumns.value
  return pgdasdColumns.value
})

// Troca de cápsula: reseta paginação e filtros exclusivos; descarta resposta obsoleta via submodule reativo.
watch(submodule, (next, prev) => {
  if (next === prev) return
  historyOpen.value = false
  previewOpen.value = false
  trackingOpen.value = false
  prefsOpen.value = false
  modalClientId.value = null
  setPage(1)
  resetFilters()
  if (isPgmeiCapsule(next)) {
    pgmeiYear.value = new Date().getFullYear()
  }
})

const yearOptions = computed(() => {
  const current = new Date().getFullYear()
  return Array.from({ length: 5 }, (_, i) => {
    const y = current - i
    return { label: String(y), value: y }
  })
})
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
    :selection-enabled="(isPgdasd || isPgmei) ? canManageClients : undefined"
    :custom-bulk-actions="isPgdasd || isPgmei"
    :horizontal-scroll="true"
    table-class="min-w-[1120px]"
    empty-title="Nenhum cliente"
    :column-labels="isPgmei
      ? {
        active_debt: 'Dívida ativa',
        total_debt: 'Total inscrito',
        send: 'Enviar',
        automatic: 'Automático',
        tracking: 'Rastreio',
        consulted: 'Última consulta',
        details: 'Detalhes'
      }
      : {
        last_declaration: 'Última declaração',
        rbt12: 'RBT12',
        send: 'Enviar',
        automatic: 'Automático',
        tracking: 'Rastreio',
        consulted: 'Última consulta',
        details: 'Detalhes'
      }"
    @update:page="setPage"
    @update:sorting="sorting = $event"
    @quick-filter-change="applyQuickFilters"
    @apply-filters="applyFilters"
    @reset-filters="resetFilters"
    @refresh="refresh"
  >
    <template #submodules>
      <div class="flex flex-wrap items-center gap-3">
        <UTabs
          v-model="submodule"
          :items="tabItems"
          :content="false"
          size="sm"
          class="w-auto max-w-full"
          data-testid="simples-mei-submodule-tabs"
        />
        <USelect
          v-if="isPgmei"
          v-model="pgmeiYear"
          :items="yearOptions"
          value-key="value"
          size="sm"
          class="w-28"
          data-testid="pgmei-year-filter"
          aria-label="Ano calendário PGMEI"
        />
      </div>
    </template>

    <template
      v-if="isPgdasd || isPgmei"
      #bulk-actions="{ selectedClientIds, selectedCount, clearSelection }"
    >
      <MonitoringPgdasdBulkAutomaticActions
        v-if="isPgdasd && canManageClients"
        :selected-client-ids="selectedClientIds"
        :selected-count="selectedCount"
        @clear="clearSelection"
        @refresh="refresh"
      />
      <MonitoringPgmeiBulkAutomaticActions
        v-if="isPgmei && canManageClients"
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
    @saved="onModalPreferenceSaved"
  />

  <MonitoringPgmeiHistoryModal
    v-if="isPgmei"
    v-model:open="historyOpen"
    :client-id="modalClientId"
    :client-name="modalClientName"
    :cnpj-masked="modalCnpjMasked"
    :year="pgmeiYear"
  />
  <MonitoringPgmeiCommunicationModals
    v-if="isPgmei"
    v-model:preview-open="previewOpen"
    v-model:tracking-open="trackingOpen"
    v-model:prefs-open="prefsOpen"
    :client-id="modalClientId"
    :client-name="modalClientName"
    :preference="modalPreference"
    :can-manage="canManageClients"
    @saved="onModalPreferenceSaved"
  />
</template>

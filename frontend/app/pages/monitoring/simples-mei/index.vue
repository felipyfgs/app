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

/**
 * Ano-calendário PGMEI fixo no ano corrente (sem seletor na UI).
 * O scheduler/backend cobrem outros anos; a carteira operacional mostra o ano atual.
 */
const pgmeiYear = computed(() => new Date().getFullYear())

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

/** Sem chips de Situação genérica (Em dia/Pendências/Bloqueado) nem KPIs em tabs. */
const filterConfig = computed<MonitoringFilterConfig>(() => {
  if (isPgmei.value) {
    return {
      fields: [
        { key: 'clientId', kind: 'client', label: 'Cliente' }
      ]
    }
  }
  return {
    fields: [
      { key: 'clientId', kind: 'client', label: 'Cliente' },
      { key: 'competence', kind: 'month', label: 'Competência' }
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

// —— Seleção (bulk no cabeçalho Enviar) ——
// Seleção NÃO entra no computed de colunas: recriar colunas a cada toggle
// faz o UTable reemitir selection-change e estoura "Maximum recursive updates".
const moduleTableRef = ref<{ clearSelection: () => void } | null>(null)
const selectedClientIds = ref<number[]>([])
const selectedCount = ref(0)

function sameClientIds(a: number[], b: number[]) {
  if (a.length !== b.length) return false
  for (let i = 0; i < a.length; i += 1) {
    if (a[i] !== b[i]) return false
  }
  return true
}

function onSelectionChange(payload: { clientIds: number[], count: number }) {
  if (
    payload.count === selectedCount.value
    && sameClientIds(payload.clientIds, selectedClientIds.value)
  ) {
    return
  }
  selectedClientIds.value = payload.clientIds
  selectedCount.value = payload.count
}

function clearSelection() {
  moduleTableRef.value?.clearSelection()
  selectedClientIds.value = []
  selectedCount.value = 0
}

function getSelectedClientIds() {
  return selectedClientIds.value
}

function getSelectedCount() {
  return selectedCount.value
}

function getSelectedAutomaticRequested() {
  if (selectedClientIds.value.length === 0) return false
  const idSet = new Set(selectedClientIds.value)
  const selected = rows.value.filter(row => idSet.has(row.client_id))
  if (selected.length === 0) return false
  return selected.every((row) => {
    if (isPgmei.value) {
      return pgmeiSummary(row, pgmeiYear.value)?.communication?.automatic_requested === true
    }
    return pgdasdSummary(row)?.communication?.automatic_requested === true
  })
}

// —— Modais compartilhados por cápsula ——
const historyOpen = ref(false)
const previewOpen = ref(false)
const trackingOpen = ref(false)
const prefsOpen = ref(false)
const consultOpen = ref(false)
const consultClientId = ref<number | null>(null)
const consultClientName = ref<string | null>(null)
const consultQuerying = ref(false)
const modalClientId = ref<number | null>(null)
const modalClientName = ref<string | null>(null)
const modalCnpjMasked = ref<string | null>(null)
const modalPreference = ref<PgdasdCommunicationPreference | null>(null)

const { requestConsult } = usePgmeiMonitoring()
const toast = useToast()

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

function openConsultConfirm(row: SimplesMeiClientRow) {
  consultClientId.value = row.client_id
  consultClientName.value = row.legal_name || row.name || `Cliente #${row.client_id}`
  consultOpen.value = true
}

async function confirmPgmeiConsult() {
  if (consultQuerying.value || !consultClientId.value) return
  consultQuerying.value = true
  try {
    await requestConsult([consultClientId.value], pgmeiYear.value)
    toast.add({
      title: 'Consulta PGMEI solicitada.',
      description: `Ano-calendário ${pgmeiYear.value}. A atualização aparecerá após o processamento.`,
      color: 'success'
    })
    consultOpen.value = false
    void refresh()
  } catch (caught) {
    toast.add({
      title: apiErrorMessage(caught, 'Não foi possível solicitar a consulta.'),
      color: 'error'
    })
  } finally {
    consultQuerying.value = false
  }
}

const bulkHandlers = {
  getSelectedClientIds,
  getSelectedCount,
  getSelectedAutomaticRequested,
  onBulkClear: clearSelection,
  onBulkRefresh: () => {
    void refresh()
  }
}

// Colunas só reagem a permissões/cápsula — não à seleção.
const pgdasdColumns = computed(() => buildPgdasdColumns({
  canManage: canManageClients.value,
  ...bulkHandlers,
  onHistory: row => openFor(row, 'history'),
  onPreview: row => openFor(row, 'preview'),
  onTracking: row => openFor(row, 'tracking'),
  onConfigure: row => openFor(row, 'prefs'),
  onPreferenceSaved
}))

const pgmeiColumns = computed(() => buildPgmeiColumns({
  year: pgmeiYear.value,
  canManage: canManageClients.value,
  canQueryDebt: canTriggerSync.value,
  ...bulkHandlers,
  onHistory: row => openFor(row, 'history'),
  onConsult: openConsultConfirm,
  onPreview: row => openFor(row, 'preview'),
  onTracking: row => openFor(row, 'tracking'),
  onConfigure: row => openFor(row, 'prefs'),
  onPreferenceSaved
}))

const columns = computed(() => {
  if (isPgmei.value) return pgmeiColumns.value
  return pgdasdColumns.value
})

const columnLabels = computed(() => {
  if (isPgmei.value) {
    return {
      situation: 'Situação',
      actions: 'Ações',
      send: 'Enviar',
      client: 'Cliente',
      tracking: 'Rastreio de envio',
      consulted: 'Última Busca',
      history: 'Histórico de Busca'
    }
  }
  return {
    situation: 'Situação',
    last_declaration: 'Últ. Declaração',
    rbt12: 'Sublimite (RBT12)',
    actions: 'Ações',
    send: 'Enviar',
    client: 'Cliente',
    tracking: 'Rastreio de envio',
    consulted: 'Última Busca',
    history: 'Histórico de Busca'
  }
})

function onSortingUpdate(next: typeof sorting.value) {
  const current = sorting.value
  if (
    current.length === next.length
    && current.every((entry, i) => entry.id === next[i]?.id && entry.desc === next[i]?.desc)
  ) {
    return
  }
  sorting.value = next
}

// Troca de cápsula: reseta paginação e filtros exclusivos; descarta resposta obsoleta via submodule reativo.
watch(submodule, (next, prev) => {
  if (next === prev) return
  historyOpen.value = false
  previewOpen.value = false
  trackingOpen.value = false
  prefsOpen.value = false
  consultOpen.value = false
  modalClientId.value = null
  consultClientId.value = null
  clearSelection()
  setPage(1)
  resetFilters()
})
</script>

<template>
  <MonitoringModuleTable
    ref="moduleTableRef"
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
    :custom-bulk-actions="true"
    :show-kpis="false"
    :horizontal-scroll="true"
    table-class="min-w-[1280px]"
    empty-title="Nenhum cliente"
    :column-labels="columnLabels"
    @update:page="setPage"
    @update:sorting="onSortingUpdate"
    @quick-filter-change="applyQuickFilters"
    @apply-filters="applyFilters"
    @reset-filters="resetFilters"
    @refresh="refresh"
    @selection-change="onSelectionChange"
  >
    <template #submodules>
      <div class="flex w-full justify-start">
        <UTabs
          v-model="submodule"
          :items="tabItems"
          :content="false"
          size="sm"
          class="w-fit max-w-full"
          :ui="{
            root: 'w-fit max-w-full',
            list: 'w-fit max-w-full justify-start',
            trigger: 'shrink-0'
          }"
          data-testid="simples-mei-submodule-tabs"
        />
      </div>
    </template>

    <!-- Bulk automático fica no cabeçalho da coluna Enviar (referência visual). -->
    <template #bulk-actions />

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

  <UModal
    v-if="isPgmei"
    v-model:open="consultOpen"
    title="Confirmar consulta de dívida ativa"
    :description="`A consulta à SERPRO para ${consultClientName || 'o cliente'}, ano ${pgmeiYear}, é explícita e pode ser faturável.`"
    :ui="{ content: 'w-[calc(100vw-1rem)] sm:max-w-lg', footer: 'justify-end' }"
  >
    <template #body>
      <UAlert
        color="warning"
        variant="subtle"
        icon="i-lucide-triangle-alert"
        title="Uma chamada por cliente"
        description="Abrir esta confirmação não consulta a SERPRO. A chamada ocorrerá somente ao confirmar."
      />
    </template>
    <template #footer>
      <UButton
        color="neutral"
        variant="ghost"
        label="Cancelar"
        :disabled="consultQuerying"
        @click="consultOpen = false"
      />
      <UButton
        color="primary"
        icon="i-lucide-refresh-cw"
        label="Confirmar consulta"
        :loading="consultQuerying"
        data-testid="pgmei-consult-confirm"
        @click="confirmPgmeiConsult"
      />
    </template>
  </UModal>
</template>

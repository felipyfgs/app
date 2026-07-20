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
import { MONITORING_SHARED_COLUMN_LABELS } from '~/utils/monitoring-table-columns'

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

/**
 * Filtro de situação operacional fica nas tabs de KPI (Total / Em dia / …),
 * como no DCTFWeb — sem chip duplicado de Situação na toolbar.
 */
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
  label: t.label,
  badge: t.badge,
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

// —— Modais compartilhados por cápsula ——
const historyOpen = ref(false)
const previewOpen = ref(false)
const trackingOpen = ref(false)
const prefsOpen = ref(false)
const consultOpen = ref(false)
const publicServicesOpen = ref(false)
const publicServicesClientId = ref<number | null>(null)
const publicServicesClientName = ref<string | null>(null)
const publicServicesCnpjMasked = ref<string | null>(null)
const consultClientId = ref<number | null>(null)
const consultClientName = ref<string | null>(null)
const consultQuerying = ref(false)
const regimeHistoryOpen = ref(false)
const regimeConsultOpen = ref(false)
const regimeOptionHistoryOpen = ref(false)
const regimeOptionConsultOpen = ref(false)
const regimeResolutionHistoryOpen = ref(false)
const regimeResolutionConsultOpen = ref(false)
const defisHistoryOpen = ref(false)
const defisConsultOpen = ref(false)
const defisLatestHistoryOpen = ref(false)
const defisLatestConsultOpen = ref(false)
const defisSpecificHistoryOpen = ref(false)
const defisSpecificConsultOpen = ref(false)
const defisSpecificReferenceId = ref<number | null>(null)
const defisClientId = ref<number | null>(null)
const defisClientName = ref<string | null>(null)
const regimeClientId = ref<number | null>(null)
const regimeClientName = ref<string | null>(null)
const regimeQuerying = ref(false)
const modalClientId = ref<number | null>(null)
const modalClientName = ref<string | null>(null)
const modalCnpjMasked = ref<string | null>(null)
const modalPreference = ref<PgdasdCommunicationPreference | null>(null)

const { requestConsult } = usePgmeiMonitoring()
const { requestConsult: requestRegimeCalendar } = useRegimeCalendarMonitoring()
const { requestConsult: requestRegimeOption } = useRegimeOptionMonitoring()
const { requestConsult: requestRegimeResolution } = useRegimeResolutionMonitoring()
const { requestConsult: requestDefisConsult } = useDefisDeclarationsMonitoring()
const { requestConsult: requestDefisLatestConsult } = useDefisLatestDeclarationMonitoring()
const { requestConsult: requestDefisSpecificConsult } = useDefisSpecificDeclarationMonitoring()
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

function openPgdasdHistory(row: SimplesMeiClientRow) {
  void navigateTo(`/monitoring/clients/${row.client_id}/pgdasd`)
}

function openConsultConfirm(row: SimplesMeiClientRow) {
  consultClientId.value = row.client_id
  consultClientName.value = row.legal_name || row.name || `Cliente #${row.client_id}`
  consultOpen.value = true
}

function openMeiPublicServices(clientId: number) {
  const row = rows.value.find(item => item.client_id === clientId)
  if (!row) return
  publicServicesClientId.value = row.client_id
  publicServicesClientName.value = row.legal_name || row.name || `Cliente #${row.client_id}`
  publicServicesCnpjMasked.value = row.cnpj_masked || null
  publicServicesOpen.value = true
}

function openRegimeHistory(row: SimplesMeiClientRow) {
  regimeClientId.value = row.client_id
  regimeClientName.value = row.legal_name || row.name || `Cliente #${row.client_id}`
  regimeHistoryOpen.value = true
}

function openRegimeResolutionHistory(row: SimplesMeiClientRow) {
  regimeClientId.value = row.client_id
  regimeClientName.value = row.legal_name || row.name || `Cliente #${row.client_id}`
  regimeResolutionHistoryOpen.value = true
}

function openRegimeOptionHistory(row: SimplesMeiClientRow) {
  regimeClientId.value = row.client_id
  regimeClientName.value = row.legal_name || row.name || `Cliente #${row.client_id}`
  regimeOptionHistoryOpen.value = true
}

function openDefisHistory(row: SimplesMeiClientRow) {
  defisClientId.value = row.client_id
  defisClientName.value = row.legal_name || row.name || `Cliente #${row.client_id}`
  defisHistoryOpen.value = true
}

function openDefisLatestHistory(row: SimplesMeiClientRow) {
  defisClientId.value = row.client_id
  defisClientName.value = row.legal_name || row.name || `Cliente #${row.client_id}`
  defisLatestHistoryOpen.value = true
}

function openDefisSpecificHistory(row: SimplesMeiClientRow) {
  defisClientId.value = row.client_id
  defisClientName.value = row.legal_name || row.name || `Cliente #${row.client_id}`
  defisSpecificHistoryOpen.value = true
}

function openDefisSpecificConsultConfirm(referenceId: number) {
  defisSpecificReferenceId.value = referenceId
  defisSpecificHistoryOpen.value = false
  defisSpecificConsultOpen.value = true
}

async function confirmRegimeConsult() {
  if (regimeQuerying.value || !regimeClientId.value) return
  regimeQuerying.value = true
  try {
    await requestRegimeCalendar(regimeClientId.value)
    toast.add({
      title: 'Consulta de regimes solicitada.',
      description: 'O histórico local será atualizado após o processamento.',
      color: 'success'
    })
    regimeConsultOpen.value = false
  } catch (caught) {
    toast.add({
      title: apiErrorMessage(caught, 'Não foi possível solicitar a consulta de regimes.'),
      color: 'error'
    })
  } finally {
    regimeQuerying.value = false
  }
}

async function confirmRegimeResolutionConsult() {
  if (regimeQuerying.value || !regimeClientId.value) return
  regimeQuerying.value = true
  const year = new Date().getFullYear()
  try {
    await requestRegimeResolution(regimeClientId.value, year)
    toast.add({
      title: 'Consulta da resolução solicitada.',
      description: `Ano-calendário ${year}. O documento aparecerá após o processamento.`,
      color: 'success'
    })
    regimeResolutionConsultOpen.value = false
  } catch (caught) {
    toast.add({
      title: apiErrorMessage(caught, 'Não foi possível solicitar a resolução.'),
      color: 'error'
    })
  } finally {
    regimeQuerying.value = false
  }
}

async function confirmRegimeOptionConsult() {
  if (regimeQuerying.value || !regimeClientId.value) return
  regimeQuerying.value = true
  const year = new Date().getFullYear()
  try {
    await requestRegimeOption(regimeClientId.value, year)
    toast.add({
      title: 'Consulta da opção anual solicitada.',
      description: `Ano-calendário ${year}. O histórico local será atualizado após o processamento.`,
      color: 'success'
    })
    regimeOptionConsultOpen.value = false
  } catch (caught) {
    toast.add({
      title: apiErrorMessage(caught, 'Não foi possível solicitar a opção anual de regime.'),
      color: 'error'
    })
  } finally {
    regimeQuerying.value = false
  }
}

async function confirmDefisConsult() {
  if (regimeQuerying.value || !defisClientId.value) return
  regimeQuerying.value = true
  try {
    await requestDefisConsult(defisClientId.value)
    toast.add({ title: 'Consulta de declarações DEFIS solicitada.', description: 'O histórico local será atualizado após o processamento.', color: 'success' })
    defisConsultOpen.value = false
  } catch (caught) {
    toast.add({ title: apiErrorMessage(caught, 'Não foi possível solicitar a consulta DEFIS.'), color: 'error' })
  } finally {
    regimeQuerying.value = false
  }
}

async function confirmDefisLatestConsult() {
  if (regimeQuerying.value || !defisClientId.value) return
  regimeQuerying.value = true
  const year = new Date().getFullYear()
  try {
    await requestDefisLatestConsult(defisClientId.value, year)
    toast.add({ title: 'Consulta da última DEFIS solicitada.', description: `Ano-calendário ${year}. Os documentos aparecerão após o processamento.`, color: 'success' })
    defisLatestConsultOpen.value = false
  } catch (caught) {
    toast.add({ title: apiErrorMessage(caught, 'Não foi possível solicitar a última DEFIS.'), color: 'error' })
  } finally {
    regimeQuerying.value = false
  }
}

async function confirmDefisSpecificConsult() {
  if (regimeQuerying.value || !defisClientId.value || !defisSpecificReferenceId.value) return
  regimeQuerying.value = true
  try {
    await requestDefisSpecificConsult(defisClientId.value, defisSpecificReferenceId.value)
    toast.add({ title: 'Consulta da declaração DEFIS solicitada.', description: 'Recibo e declaração aparecerão após o processamento.', color: 'success' })
    defisSpecificConsultOpen.value = false
    defisSpecificReferenceId.value = null
  } catch (caught) {
    toast.add({ title: apiErrorMessage(caught, 'Não foi possível solicitar a declaração DEFIS.'), color: 'error' })
  } finally {
    regimeQuerying.value = false
  }
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

// Colunas só reagem a permissões/cápsula — não à seleção.
const pgdasdActionHandlers = computed(() => ({
  canQueryRegime: canTriggerSync.value,
  canQueryRegimeOption: canTriggerSync.value,
  canQueryRegimeResolution: canTriggerSync.value,
  canQueryDefis: canTriggerSync.value,
  canQueryDefisLatest: canTriggerSync.value,
  onConfigure: (row: SimplesMeiClientRow) => openFor(row, 'prefs'),
  onRegimeHistory: openRegimeHistory,
  onRegimeOptionHistory: openRegimeOptionHistory,
  onRegimeResolutionHistory: openRegimeResolutionHistory,
  onDefisHistory: openDefisHistory,
  onDefisLatestHistory: openDefisLatestHistory,
  onDefisSpecificHistory: openDefisSpecificHistory,
  onPreview: (row: SimplesMeiClientRow) => openFor(row, 'preview'),
  onTracking: (row: SimplesMeiClientRow) => openFor(row, 'tracking'),
  onHistory: openPgdasdHistory
}))

const pgdasdColumns = computed(() => buildPgdasdColumns({
  onHistory: openPgdasdHistory,
  onPreview: row => openFor(row, 'preview'),
  onTracking: row => openFor(row, 'tracking'),
  onConfigure: row => openFor(row, 'prefs'),
  onPublicServices: row => openMeiPublicServices(row.client_id)
}))

const pgmeiColumns = computed(() => buildPgmeiColumns({
  year: pgmeiYear.value,
  onHistory: row => openFor(row, 'history'),
  onConsult: openConsultConfirm,
  onPreview: row => openFor(row, 'preview'),
  onTracking: row => openFor(row, 'tracking'),
  onConfigure: row => openFor(row, 'prefs')
}))

const columns = computed(() => {
  if (isPgmei.value) return pgmeiColumns.value
  return pgdasdColumns.value
})

const columnLabels = computed<Record<string, string>>(() => {
  if (isPgmei.value) {
    return {
      situation: 'Situação',
      ...MONITORING_SHARED_COLUMN_LABELS,
      client: 'Cliente'
    }
  }
  return {
    situation: 'Situação',
    last_declaration: 'Últ. Declaração',
    rbt12: 'RBT12',
    ...MONITORING_SHARED_COLUMN_LABELS,
    client: 'Cliente'
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
  publicServicesOpen.value = false
  regimeHistoryOpen.value = false
  regimeConsultOpen.value = false
  regimeResolutionHistoryOpen.value = false
  regimeResolutionConsultOpen.value = false
  modalClientId.value = null
  consultClientId.value = null
  publicServicesClientId.value = null
  regimeClientId.value = null
  clearSelection()
  setPage(1)
  resetFilters()
})
</script>

<template>
  <MonitoringModuleTable
    ref="moduleTableRef"
    title="Simples Nacional | MEI"
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
    :show-pending-search="false"
    :show-synthetic-alert="false"
    :sorting="sorting"
    :get-row-id="getRowId"
    :get-client-id="row => row.client_id"
    :submodule="submodule"
    :selection-enabled="(isPgdasd || isPgmei) ? canManageClients : undefined"
    :custom-bulk-actions="true"
    :horizontal-scroll="true"
    :initial-hidden-columns="['history']"
    empty-title="Nenhum cliente"
    :column-labels="columnLabels"
    @update:page="setPage"
    @update:per-page="setPerPage"
    @update:sorting="onSortingUpdate"
    @quick-filter-change="applyQuickFilters"
    @apply-filters="applyFilters"
    @reset-filters="resetFilters"
    @refresh="refresh"
    @selection-change="onSelectionChange"
  >
    <template #submodules>
      <ShellScrollableTabs
        v-model="submodule"
        :items="tabItems"
        size="sm"
        color="primary"
        variant="pill"
        class="min-w-0"
        aria-label="Selecionar Simples Nacional ou MEI"
        test-id="simples-mei-submodule-tabs"
      />
    </template>

    <!-- Ações do cliente selecionado na toolbar (junto ao filtro); automático no header Enviar. -->
    <template #bulk-actions="{ selectedClientIds: ids, selectedCount: count, clearSelection: clear }">
      <MonitoringPgdasdSelectionActions
        v-if="isPgdasd"
        :selected-client-ids="ids"
        :selected-count="count"
        :rows="rows"
        :handlers="pgdasdActionHandlers"
        @clear="clear"
        @refresh="refresh"
      />
      <MonitoringPgmeiBulkActions
        v-else-if="isPgmei"
        :selected-client-ids="ids"
        :selected-count="count"
        :year="pgmeiYear"
        :can-use-public-services="canTriggerSync"
        @clear="clear"
        @refresh="refresh"
        @public-services="openMeiPublicServices"
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

  <MonitoringPgdasdCommunicationModals
    v-if="isPgdasd"
    v-model:preview-open="previewOpen"
    v-model:tracking-open="trackingOpen"
    v-model:prefs-open="prefsOpen"
    :client-id="modalClientId"
    :client-name="modalClientName"
    :preference="modalPreference"
  />
  <MonitoringRegimeCalendarModal
    v-if="isPgdasd"
    v-model:open="regimeHistoryOpen"
    :client-id="regimeClientId"
    :client-name="regimeClientName"
  />
  <MonitoringRegimeResolutionModal
    v-if="isPgdasd"
    v-model:open="regimeResolutionHistoryOpen"
    :client-id="regimeClientId"
    :client-name="regimeClientName"
  />
  <MonitoringRegimeOptionModal
    v-if="isPgdasd"
    v-model:open="regimeOptionHistoryOpen"
    :client-id="regimeClientId"
    :client-name="regimeClientName"
  />
  <MonitoringDefisDeclarationsModal
    v-if="isPgdasd"
    v-model:open="defisHistoryOpen"
    :client-id="defisClientId"
    :client-name="defisClientName"
  />
  <MonitoringDefisLatestDeclarationModal
    v-if="isPgdasd"
    v-model:open="defisLatestHistoryOpen"
    :client-id="defisClientId"
    :client-name="defisClientName"
  />
  <MonitoringDefisSpecificDeclarationModal
    v-if="isPgdasd"
    v-model:open="defisSpecificHistoryOpen"
    :client-id="defisClientId"
    :client-name="defisClientName"
    @consult="openDefisSpecificConsultConfirm"
  />

  <MonitoringPgmeiHistoryModal
    v-if="isPgmei"
    v-model:open="historyOpen"
    :client-id="modalClientId"
    :client-name="modalClientName"
    :cnpj-masked="modalCnpjMasked"
    :year="pgmeiYear"
  />
  <MonitoringPgdasdCommunicationModals
    v-if="isPgmei"
    v-model:preview-open="previewOpen"
    v-model:tracking-open="trackingOpen"
    v-model:prefs-open="prefsOpen"
    :client-id="modalClientId"
    :client-name="modalClientName"
    :preference="modalPreference"
    context="PGMEI"
    :year="pgmeiYear"
  />
  <MonitoringMeiPublicServicesModal
    v-if="isPgmei"
    v-model:open="publicServicesOpen"
    :client-id="publicServicesClientId"
    :client-name="publicServicesClientName"
    :cnpj-masked="publicServicesCnpjMasked"
    :can-refresh="canTriggerSync"
  />

  <ShellConfirmModal
    v-if="isPgmei"
    v-model:open="consultOpen"
    title="Confirmar consulta de dívida ativa"
    :description="`A consulta à SERPRO para ${consultClientName || 'o cliente'}, ano ${pgmeiYear}, é explícita e pode ser faturável.`"
    content-class="w-[calc(100vw-1rem)] sm:max-w-lg"
    confirm-label="Confirmar consulta"
    confirm-icon="i-lucide-refresh-cw"
    :loading="consultQuerying"
    confirm-test-id="pgmei-consult-confirm"
    @confirm="confirmPgmeiConsult"
  >
    <template #body>
      <UAlert
        color="warning"
        variant="subtle"
        icon="i-lucide-triangle-alert"
        title="Uma chamada por cliente"
      >
        <template #description>
          Abrir esta confirmação não consulta a SERPRO. A chamada ocorrerá somente ao confirmar.
        </template>
      </UAlert>
    </template>
  </ShellConfirmModal>

  <ShellConfirmModal
    v-if="isPgdasd"
    v-model:open="defisConsultOpen"
    title="Confirmar consulta das declarações DEFIS"
    :description="`A consulta à SERPRO para ${defisClientName || 'o cliente'} é explícita e pode ser faturável.`"
    content-class="w-[calc(100vw-1rem)] sm:max-w-lg"
    confirm-label="Confirmar consulta"
    confirm-icon="i-lucide-refresh-cw"
    :loading="regimeQuerying"
    confirm-test-id="defis-consult-confirm"
    @confirm="confirmDefisConsult"
  >
    <template #body>
      <UAlert
        color="warning"
        variant="subtle"
        icon="i-lucide-triangle-alert"
        title="Uma chamada por cliente"
      >
        <template #description>
          Abrir esta confirmação não consulta a SERPRO. A chamada ocorrerá somente ao confirmar.
        </template>
      </UAlert>
    </template>
  </ShellConfirmModal>

  <ShellConfirmModal
    v-if="isPgdasd"
    v-model:open="defisLatestConsultOpen"
    title="Confirmar consulta da última DEFIS"
    :description="`A consulta à SERPRO para ${defisClientName || 'o cliente'}, ano ${new Date().getFullYear()}, é explícita e pode ser faturável.`"
    content-class="w-[calc(100vw-1rem)] sm:max-w-lg"
    confirm-label="Confirmar consulta"
    confirm-icon="i-lucide-refresh-cw"
    :loading="regimeQuerying"
    confirm-test-id="defis-latest-consult-confirm"
    @confirm="confirmDefisLatestConsult"
  >
    <template #body>
      <UAlert
        color="warning"
        variant="subtle"
        icon="i-lucide-triangle-alert"
        title="Uma chamada por cliente"
      >
        <template #description>
          A chamada ocorrerá somente ao confirmar. Recibo e declaração serão guardados no cofre.
        </template>
      </UAlert>
    </template>
  </ShellConfirmModal>

  <ShellConfirmModal
    v-if="isPgdasd"
    v-model:open="defisSpecificConsultOpen"
    title="Confirmar consulta da declaração DEFIS"
    :description="`A consulta à SERPRO para ${defisClientName || 'o cliente'} é explícita e pode ser faturável.`"
    content-class="w-[calc(100vw-1rem)] sm:max-w-lg"
    confirm-label="Confirmar consulta"
    confirm-icon="i-lucide-file-down"
    :loading="regimeQuerying"
    confirm-test-id="defis-specific-consult-confirm"
    @confirm="confirmDefisSpecificConsult"
  >
    <template #body>
      <UAlert
        color="warning"
        variant="subtle"
        icon="i-lucide-triangle-alert"
        title="Uma chamada por declaração"
      >
        <template #description>
          A chamada ocorrerá somente ao confirmar. Recibo e declaração serão guardados no cofre.
        </template>
      </UAlert>
    </template>
  </ShellConfirmModal>

  <ShellConfirmModal
    v-if="isPgdasd"
    v-model:open="regimeOptionConsultOpen"
    title="Confirmar consulta da opção anual"
    :description="`A consulta à SERPRO para ${regimeClientName || 'o cliente'}, no ano atual, é explícita e pode ser faturável.`"
    content-class="w-[calc(100vw-1rem)] sm:max-w-lg"
    confirm-label="Confirmar consulta"
    confirm-icon="i-lucide-calendar-sync"
    :loading="regimeQuerying"
    confirm-test-id="regime-option-consult-confirm"
    @confirm="confirmRegimeOptionConsult"
  >
    <template #body>
      <UAlert
        color="warning"
        variant="subtle"
        icon="i-lucide-triangle-alert"
        title="Uma chamada por cliente e ano"
      >
        <template #description>
          Abrir esta confirmação não consulta a SERPRO. A chamada ocorrerá somente ao confirmar.
        </template>
      </UAlert>
    </template>
  </ShellConfirmModal>

  <ShellConfirmModal
    v-if="isPgdasd"
    v-model:open="regimeConsultOpen"
    title="Confirmar consulta de regimes"
    :description="`A consulta à SERPRO para ${regimeClientName || 'o cliente'} é explícita e pode ser faturável.`"
    content-class="w-[calc(100vw-1rem)] sm:max-w-lg"
    confirm-label="Confirmar consulta"
    confirm-icon="i-lucide-refresh-cw"
    :loading="regimeQuerying"
    confirm-test-id="regime-calendar-consult-confirm"
    @confirm="confirmRegimeConsult"
  >
    <template #body>
      <UAlert
        color="warning"
        variant="subtle"
        icon="i-lucide-triangle-alert"
        title="Uma chamada por cliente"
      >
        <template #description>
          Abrir esta confirmação não consulta a SERPRO. A chamada ocorrerá somente ao confirmar.
        </template>
      </UAlert>
    </template>
  </ShellConfirmModal>

  <ShellConfirmModal
    v-if="isPgdasd"
    v-model:open="regimeResolutionConsultOpen"
    title="Confirmar consulta da resolução"
    :description="`A consulta à SERPRO para ${regimeClientName || 'o cliente'}, no ano atual, é explícita e pode ser faturável.`"
    content-class="w-[calc(100vw-1rem)] sm:max-w-lg"
    confirm-label="Confirmar consulta"
    confirm-icon="i-lucide-file-down"
    :loading="regimeQuerying"
    confirm-test-id="regime-resolution-consult-confirm"
    @confirm="confirmRegimeResolutionConsult"
  >
    <template #body>
      <UAlert
        color="warning"
        variant="subtle"
        icon="i-lucide-triangle-alert"
        title="Uma chamada por cliente"
      >
        <template #description>
          Abrir esta confirmação não consulta a SERPRO. A chamada ocorrerá somente ao confirmar.
        </template>
      </UAlert>
    </template>
  </ShellConfirmModal>
</template>

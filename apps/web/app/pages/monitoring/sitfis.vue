<script setup lang="ts">
/**
 * Situação Fiscal (SITFIS) — carteira + idade/TTL + contagem de achados.
 * USlideover com findings/pendências normalizadas (sem JSON bruto).
 * Task 7.5 · deep-links /monitoring/clients/{id}/sitfis
 */
import type { FiscalFinding, FiscalPendingItem } from '~/types/api'
import type { MonitoringFilterConfig, SitfisClientRow } from '~/types/fiscal-modules'
import { commercialBlockLabel } from '~/utils/monitor-commercial'
import {
  buildSitfisColumns,
  sitfisAgeLabel as ageLabel,
  sitfisDetailOf as detailOf
} from '~/utils/sitfis-table'
import { MONITORING_SHARED_COLUMN_LABELS } from '~/utils/monitoring-table-columns'

const api = useApi()
const { canTriggerSync } = useDashboard()
const toast = useToast()

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
} = useFiscalModulePortfolio('sitfis')

const filterConfig: MonitoringFilterConfig = {
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

function getRowId(row: SitfisClientRow) {
  return `c:${row.client_id}`
}

const slideOpen = ref(false)
const selected = ref<SitfisClientRow | null>(null)
const findings = ref<FiscalFinding[]>([])
const pending = ref<FiscalPendingItem[]>([])
const sitfisMeta = ref<{
  observed_at?: string | null
  age_seconds?: number | null
  ttl_seconds?: number | null
  is_within_ttl?: boolean
  expires_at?: string | null
  next_refresh_at?: string | null
  can_refresh?: boolean
  block_reason?: string | null
  source_provenance?: string | null
  verification_state?: string | null
  disclaimer?: string | null
  coverage?: string | null
} | null>(null)

function provenanceLabel(value?: string | null) {
  if (value === 'SERPRO_REAL') return 'Fonte SERPRO real'
  if (value === 'SIMULATED') return 'Simulado (desenvolvimento)'
  if (value === 'UNVERIFIED') return 'Não verificado (legado)'
  return null
}
const detailLoading = ref(false)
const detailError = ref<string | null>(null)
const detailRefreshing = ref(false)

function clientHref(id: number) {
  return `/monitoring/clients/${id}/sitfis`
}

async function openDetail(row: SitfisClientRow) {
  selected.value = row
  slideOpen.value = true
  detailLoading.value = true
  detailError.value = null
  findings.value = []
  pending.value = []
  sitfisMeta.value = null
  try {
    const [findRes, pendRes, sitRes] = await Promise.allSettled([
      api.fiscal.findings({ client_id: row.client_id, per_page: 50, active_only: true }),
      api.fiscal.pending({ client_id: row.client_id, per_page: 50, status: 'OPEN' }),
      api.fiscal.sitfis.show(row.client_id)
    ])
    if (findRes.status === 'fulfilled') {
      findings.value = ((findRes.value as { data: FiscalFinding[] }).data) || []
    }
    if (pendRes.status === 'fulfilled') {
      pending.value = ((pendRes.value as { data: FiscalPendingItem[] }).data) || []
    }
    if (sitRes.status === 'fulfilled') {
      const view = sitRes.value.data || {}
      const d = detailOf(row)
      sitfisMeta.value = {
        observed_at: (view.observed_at as string | null)
          || (view.snapshot as { observed_at?: string } | undefined)?.observed_at
          || d.observed_at
          || null,
        age_seconds: (view.age_seconds as number | null) ?? d.age_seconds ?? null,
        ttl_seconds: (view.ttl_seconds as number | null) ?? d.ttl_seconds ?? null,
        is_within_ttl: view.is_within_ttl as boolean | undefined,
        expires_at: view.expires_at as string | null | undefined,
        next_refresh_at: view.next_refresh_at as string | null | undefined,
        can_refresh: view.can_refresh as boolean | undefined,
        block_reason: view.block_reason as string | null | undefined,
        source_provenance: (view.source_provenance as string | null | undefined)
          || (view.snapshot as { source_provenance?: string } | undefined)?.source_provenance
          || null,
        verification_state: (view.verification_state as string | null | undefined)
          || (view.snapshot as { verification_state?: string } | undefined)?.verification_state
          || null,
        disclaimer: view.disclaimer as string | null | undefined,
        coverage: String(
          (view.snapshot as { coverage?: string } | undefined)?.coverage
          || row.coverage
          || ''
        ) || null
      }
    }
    if (
      findRes.status === 'rejected'
      && pendRes.status === 'rejected'
      && sitRes.status === 'rejected'
    ) {
      detailError.value = 'Falha ao carregar detalhe SITFIS.'
    }
  } finally {
    detailLoading.value = false
  }
}

const recentConfirmOpen = ref(false)

async function doRefreshSelected(force = false) {
  if (!selected.value || !canTriggerSync.value) return
  detailRefreshing.value = true
  try {
    await api.fiscal.sitfis.refresh({
      client_id: selected.value.client_id,
      force
    })
    toast.add({ title: 'Atualização SITFIS solicitada', color: 'success' })
    recentConfirmOpen.value = false
    await openDetail(selected.value)
    await refresh()
  } catch (caught) {
    toast.add({
      title: apiErrorMessage(caught, 'Falha ao solicitar refresh SITFIS. Verifique procuração e franquia.'),
      color: 'error'
    })
  } finally {
    detailRefreshing.value = false
  }
}

async function refreshSelected() {
  if (!selected.value || !canTriggerSync.value) return
  const recent = selected.value.is_recent_snapshot
    || sitfisMeta.value?.is_within_ttl === true
  if (recent) {
    recentConfirmOpen.value = true
    return
  }
  await doRefreshSelected(false)
}

const columns = computed(() => buildSitfisColumns({
  allowsDocument: allowsDocument.value,
  onFindings: openDetail
}))
</script>

<template>
  <MonitoringModuleTable
    title="Situação Fiscal"
    panel-id="monitoring-sitfis"
    module-key="sitfis"
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
    :initial-hidden-columns="['procuracao', 'franchise', 'age']"
    empty-title="Nenhum cliente"
    :column-labels="{
      situation: 'Situação',
      findings: 'Achados',
      coverage: 'Cobertura',
      actions: 'Ações',
      client: 'Cliente',
      procuracao: 'Procuração',
      franchise: 'Franquia / agenda',
      age: 'Idade / TTL',
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
      <MonitoringManualConsultCta
        v-if="selected?.client_id"
        :client-id="selected.client_id"
        module-key="sitfis"
        surface-key="sitfis"
        preferred-action-id="sitfis:sitfis.solicitar_protocolo"
        label="Consulta SITFIS"
        @refresh="refresh"
      />
    </template>

    <template #detail>
      <USlideover
        v-model:open="slideOpen"
        :title="selected
          ? (selected.name || selected.legal_name || `Cliente #${selected.client_id}`)
          : 'Detalhe SITFIS'"
      >
        <template #body>
          <div
            v-if="detailLoading"
            class="py-8 text-sm text-muted"
          >
            Carregando achados…
          </div>
          <UAlert
            v-else-if="detailError"
            color="error"
            :title="detailError"
          />
          <div
            v-else
            class="flex flex-col gap-4"
          >
            <div class="flex flex-wrap items-center gap-2">
              <FiscalStatusBadge
                v-if="selected"
                :status="selected.situation"
                show-hint
              />
              <span class="text-sm text-muted">
                Idade: {{ ageLabel(sitfisMeta?.age_seconds ?? (selected ? detailOf(selected).age_seconds : null)) }}
                · TTL: {{ ageLabel(sitfisMeta?.ttl_seconds ?? (selected ? detailOf(selected).ttl_seconds : null)) }}
              </span>
              <UBadge
                v-if="provenanceLabel(sitfisMeta?.source_provenance)"
                :color="sitfisMeta?.source_provenance === 'SERPRO_REAL' ? 'success' : 'warning'"
                variant="subtle"
                size="sm"
              >
                {{ provenanceLabel(sitfisMeta?.source_provenance) }}
              </UBadge>
              <UBadge
                v-if="sitfisMeta?.block_reason === 'RUN_IN_PROGRESS'"
                color="info"
                variant="subtle"
                size="sm"
              >
                Atualização em processamento
              </UBadge>
              <UButton
                v-if="canTriggerSync && selected && sitfisMeta?.can_refresh !== false"
                size="xs"
                color="neutral"
                variant="outline"
                icon="i-lucide-refresh-cw"
                label="Solicitar atualização"
                :loading="detailRefreshing"
                data-testid="sitfis-request-refresh"
                @click="refreshSelected()"
              />
              <MonitoringManualConsultCta
                v-if="selected?.client_id"
                :client-id="selected.client_id"
                module-key="sitfis"
                surface-key="sitfis"
                preferred-action-id="sitfis:sitfis.solicitar_protocolo"
                label="Consulta manual"
                size="xs"
                @refresh="() => { void openDetail(selected!); void refresh() }"
              />
              <span
                v-else-if="sitfisMeta?.block_reason === 'WITHIN_TTL' && sitfisMeta?.next_refresh_at"
                class="text-xs text-muted"
              >
                Dados recentes · próxima atualização a partir de {{ formatDateTime(sitfisMeta.next_refresh_at) }}
              </span>
              <UAlert
                v-if="selected?.block_message || selected?.block_reason"
                color="warning"
                icon="i-lucide-triangle-alert"
                class="w-full"
                :title="selected.block_message || commercialBlockLabel(selected.block_reason) || 'Bloqueio operacional — revise e-CAC ou franquia'"
              />
              <FiscalDocumentAction
                v-if="selected"
                :document="selected.document"
                :disabled="!allowsDocument"
              />
              <UButton
                v-if="selected"
                size="xs"
                color="neutral"
                variant="ghost"
                label="Painel do cliente"
                :to="clientHref(selected.client_id)"
              />
            </div>

            <UPageCard
              v-if="sitfisMeta || selected"
              variant="subtle"
              title="Resumo do snapshot"
            >
              <dl class="grid gap-2 text-sm sm:grid-cols-2">
                <div>
                  <dt class="text-muted">
                    Observado em
                  </dt>
                  <dd>
                    {{ formatDateTime(String(sitfisMeta?.observed_at || (selected ? detailOf(selected).observed_at : '') || '') || null) }}
                  </dd>
                </div>
                <div>
                  <dt class="text-muted">
                    Expira em
                  </dt>
                  <dd>
                    {{ formatDateTime(String(sitfisMeta?.expires_at || '') || null) }}
                  </dd>
                </div>
                <div>
                  <dt class="text-muted">
                    Dentro do TTL
                  </dt>
                  <dd>
                    <template v-if="sitfisMeta?.is_within_ttl === true">
                      Sim
                    </template>
                    <template v-else-if="sitfisMeta?.is_within_ttl === false">
                      Não
                    </template>
                    <template v-else>
                      —
                    </template>
                  </dd>
                </div>
                <div>
                  <dt class="text-muted">
                    Cobertura
                  </dt>
                  <dd>
                    <FiscalCoverageBadge
                      :coverage="String(sitfisMeta?.coverage || selected?.coverage || '')"
                    />
                  </dd>
                </div>
              </dl>
              <p
                v-if="sitfisMeta?.disclaimer"
                class="mt-3 text-xs text-muted"
              >
                {{ sitfisMeta.disclaimer }}
              </p>
            </UPageCard>

            <div>
              <h3 class="mb-2 text-sm font-medium">
                Findings ativos
              </h3>
              <div
                v-if="!findings.length"
                class="text-sm text-muted"
              >
                Nenhum finding ativo retornado.
              </div>
              <ul
                v-else
                class="divide-y divide-default"
              >
                <li
                  v-for="f in findings"
                  :key="f.id"
                  class="flex items-start justify-between gap-3 py-3 text-sm"
                >
                  <div class="min-w-0">
                    <p class="font-medium text-highlighted">
                      {{ f.title || f.code || `Finding #${f.id}` }}
                    </p>
                    <p class="text-xs text-muted">
                      {{ f.detail || '—' }}
                    </p>
                  </div>
                  <FiscalStatusBadge :status="f.situation || f.severity" />
                </li>
              </ul>
            </div>

            <div>
              <h3 class="mb-2 text-sm font-medium">
                Pendências abertas
              </h3>
              <div
                v-if="!pending.length"
                class="text-sm text-muted"
              >
                Nenhuma pendência aberta retornada.
              </div>
              <ul
                v-else
                class="divide-y divide-default"
              >
                <li
                  v-for="p in pending"
                  :key="p.id"
                  class="flex items-start justify-between gap-3 py-3 text-sm"
                >
                  <div class="min-w-0">
                    <p class="font-medium text-highlighted">
                      {{ p.title || p.code || `Pendência #${p.id}` }}
                    </p>
                    <p class="text-xs text-muted">
                      Venc.: {{ formatDateTime(p.due_at) }} · {{ p.detail || '—' }}
                    </p>
                  </div>
                  <FiscalStatusBadge :status="p.situation || p.status" />
                </li>
              </ul>
            </div>
          </div>
        </template>
      </USlideover>
    </template>
  </MonitoringModuleTable>

  <MonitoringRecentRefreshConfirmModal
    v-model:open="recentConfirmOpen"
    :last-at="sitfisMeta?.observed_at || selected?.last_snapshot_at || selected?.last_consulted_at"
    :remaining="selected?.commercial_quota?.remaining"
    :loading="detailRefreshing"
    @confirm="doRefreshSelected(true)"
  />
</template>

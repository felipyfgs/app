<script setup lang="ts">
/**
 * FGTS / eSocial — cobertura parcial permanente + estados UNSUPPORTED honestos.
 * Fechamento, totalização, eventos e divergências no detalhe. Task 7.10
 * Sem ação de scraping / portal humano.
 */
import type { TableColumn } from '@nuxt/ui'
import type { FgtsCoverageManifest } from '~/types/api'
import type { FgtsClientDetail, FgtsClientRow, MonitoringFilterConfig } from '~/types/fiscal-modules'
import { sortHeader } from '~/utils/table-sort'

const FiscalStatusBadge = resolveComponent('FiscalStatusBadge')
const FiscalClientCell = resolveComponent('FiscalClientCell')
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
  sorting,
  setPage,
  refresh,
  applyFilters,
  applyQuickFilters,
  resetFilters
} = useFiscalModulePortfolio('fgts')

const filterConfig: MonitoringFilterConfig = {
  fields: [
    { key: 'situation', kind: 'option', label: 'Situação' },
    { key: 'clientId', kind: 'client', label: 'Cliente' },
    { key: 'competence', kind: 'month', label: 'Competência' }
  ]
}

function getRowId(row: FgtsClientRow) {
  return `c:${row.client_id}`
}

const coverage = ref<FgtsCoverageManifest | null>(null)
const coverageError = ref<string | null>(null)

const detailOpen = ref(false)
const detailLoading = ref(false)
const detailError = ref<string | null>(null)
const detailStatus = ref<Record<string, unknown> | null>(null)
const detailEvents = ref<Array<Record<string, unknown>>>([])
const detailDivergences = ref<Array<{ code?: string, title?: string, detail?: string, severity?: string, situation?: string }>>([])
const detailClient = ref<FgtsClientRow | null>(null)

function clientHref(id: number) {
  return `/monitoring/clients/${id}/fgts`
}

function detailOf(row: FgtsClientRow): FgtsClientDetail {
  return row.detail || {}
}

function parseStatusId(row: FgtsClientRow): number | null {
  const link = detailOf(row).links?.status
  if (!link) return null
  const m = String(link).match(/\/competences\/(\d+)/)
  return m ? Number(m[1]) : null
}

async function loadCoverage() {
  try {
    coverage.value = (await api.fiscal.fgts.coverage()).data
    coverageError.value = null
  } catch (caught) {
    coverage.value = null
    coverageError.value = apiErrorMessage(caught, 'Falha ao carregar manifesto de cobertura FGTS.')
  }
}

async function openDetail(row: FgtsClientRow) {
  detailClient.value = row
  detailOpen.value = true
  detailLoading.value = true
  detailError.value = null
  detailStatus.value = null
  detailEvents.value = []
  detailDivergences.value = []

  const d = detailOf(row)
  try {
    let statusId = parseStatusId(row)
    if (!statusId) {
      const listRes = await api.fiscal.fgts.competences({
        client_id: row.client_id,
        competence_period_key: d.competence_period_key || filters.value.competence || undefined,
        per_page: 5
      })
      const first = (listRes.data || [])[0] as { id?: number } | undefined
      statusId = first?.id ? Number(first.id) : null
    }

    if (!statusId) {
      detailError.value = 'Nenhuma competência eSocial retornada para este cliente.'
      return
    }

    const [statusRes, findingsRes] = await Promise.allSettled([
      api.fiscal.fgts.competence(statusId),
      api.fiscal.findings({ client_id: row.client_id, per_page: 50, active_only: true })
    ])

    if (statusRes.status === 'fulfilled') {
      detailStatus.value = (statusRes.value.data || {}) as Record<string, unknown>
      detailEvents.value = (statusRes.value.events || []) as Array<Record<string, unknown>>
      const lim = detailStatus.value.limitations
      if (Array.isArray(lim)) {
        detailDivergences.value = lim.map((item) => {
          if (item && typeof item === 'object' && !Array.isArray(item)) {
            const o = item as Record<string, unknown>
            return {
              code: o.code != null ? String(o.code) : undefined,
              title: o.title != null ? String(o.title) : (o.code != null ? String(o.code) : 'Limitação'),
              detail: o.detail != null ? String(o.detail) : (o.message != null ? String(o.message) : String(item)),
              severity: o.severity != null ? String(o.severity) : undefined,
              situation: o.situation != null ? String(o.situation) : undefined
            }
          }
          return {
            title: 'Limitação de cobertura',
            detail: String(item)
          }
        })
      }
    } else {
      detailError.value = apiErrorMessage(statusRes.reason, 'Falha ao carregar competência FGTS.')
    }

    if (findingsRes.status === 'fulfilled') {
      const finds = findingsRes.value.data || []
      const esocial = finds.filter((f) => {
        const code = String(f.code || '').toUpperCase()
        return code.startsWith('ESOCIAL') || code.includes('TOTALIZER') || code.includes('FGTS')
      })
      for (const f of esocial) {
        detailDivergences.value.push({
          code: f.code != null ? String(f.code) : undefined,
          title: String(f.title || f.code || 'Divergência'),
          detail: f.detail != null ? String(f.detail) : undefined,
          severity: f.severity != null ? String(f.severity) : undefined,
          situation: f.situation != null ? String(f.situation) : undefined
        })
      }
    }
  } catch (caught) {
    detailError.value = apiErrorMessage(caught, 'Falha ao carregar detalhe FGTS/eSocial.')
  } finally {
    detailLoading.value = false
  }
}

const columns: TableColumn<FgtsClientRow>[] = [
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
    cell: ({ row }) => String(
      row.original.competence
      || detailOf(row.original).competence_period_key
      || '—'
    )
  },
  {
    id: 'closure',
    header: 'Fechamento',
    enableSorting: false,
    cell: ({ row }) => h(FiscalStatusBadge, {
      status: String(detailOf(row.original).closure_status || row.original.situation || 'UNKNOWN'),
      showHint: true
    })
  },
  {
    id: 'totalization',
    header: 'Totalização',
    enableSorting: false,
    cell: ({ row }) => h(FiscalStatusBadge, {
      status: String(detailOf(row.original).totalization_status || 'UNKNOWN'),
      showHint: true
    })
  },
  {
    id: 'guide',
    header: 'Guia FGTS Digital',
    enableSorting: false,
    cell: ({ row }) => h(FiscalStatusBadge, {
      status: String(detailOf(row.original).guide_status || 'UNSUPPORTED'),
      showHint: true
    })
  },
  {
    id: 'payment',
    header: 'Pagamento',
    enableSorting: false,
    cell: ({ row }) => h(FiscalStatusBadge, {
      status: String(detailOf(row.original).payment_status || 'UNSUPPORTED'),
      showHint: true
    })
  },
  {
    id: 'synced',
    header: ({ column }) => sortHeader('Último sync', column),
    cell: ({ row }) => formatDateTime(
      String(detailOf(row.original).last_synced_at || row.original.last_consulted_at || '') || null
    )
  },
  {
    id: 'actions',
    header: 'Ações',
    enableHiding: false,
    enableSorting: false,
    meta: { class: { th: 'w-40', td: 'w-40' } },
    cell: ({ row }) => h('div', { class: 'flex justify-end gap-1' }, [
      h(UButton, {
        size: 'xs',
        color: 'primary',
        variant: 'ghost',
        label: 'Detalhe',
        onClick: () => openDetail(row.original)
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

onMounted(() => {
  void loadCoverage()
})
</script>

<template>
  <MonitoringModuleTable
    title="FGTS (parcial eSocial)"
    panel-id="monitoring-fgts"
    module-key="fgts"
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
    empty-title="Nenhum cliente FGTS"
    :column-labels="{
      closure: 'Fechamento',
      totalization: 'Totalização',
      guide: 'Guia FGTS Digital',
      payment: 'Pagamento',
      synced: 'Último sync'
    }"
    @update:page="setPage"
    @update:sorting="sorting = $event"
    @quick-filter-change="applyQuickFilters"
    @apply-filters="applyFilters"
    @reset-filters="resetFilters"
    @refresh="refresh"
  >
    <template #utilities>
      <UAlert
        v-if="coverageError"
        color="warning"
        icon="i-lucide-triangle-alert"
        :title="coverageError"
        class="w-full"
      />

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
        :title="detailClient
          ? (detailClient.name || detailClient.legal_name || `Cliente #${detailClient.client_id}`)
          : 'Detalhe FGTS / eSocial'"
      >
        <template #body>
          <div
            v-if="detailLoading"
            class="py-8 text-sm text-muted"
          >
            Carregando competência e eventos…
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
            <dl
              v-if="detailStatus"
              class="grid gap-2 text-sm sm:grid-cols-2"
            >
              <div>
                <dt class="text-muted">
                  Competência
                </dt>
                <dd class="font-medium">
                  {{ detailStatus.competence_period_key || '—' }}
                </dd>
              </div>
              <div>
                <dt class="text-muted">
                  Situação
                </dt>
                <dd>
                  <FiscalStatusBadge :status="String(detailStatus.situation || '')" />
                </dd>
              </div>
              <div>
                <dt class="text-muted">
                  Fechamento
                </dt>
                <dd>
                  <FiscalStatusBadge
                    :status="String(detailStatus.closure_status || 'UNKNOWN')"
                    show-hint
                  />
                </dd>
              </div>
              <div>
                <dt class="text-muted">
                  Totalização
                </dt>
                <dd>
                  <FiscalStatusBadge
                    :status="String(detailStatus.totalization_status || 'UNKNOWN')"
                    show-hint
                  />
                </dd>
              </div>
              <div>
                <dt class="text-muted">
                  Guia FGTS Digital
                </dt>
                <dd>
                  <FiscalStatusBadge
                    :status="String(detailStatus.guide_status || 'UNSUPPORTED')"
                    show-hint
                  />
                </dd>
              </div>
              <div>
                <dt class="text-muted">
                  Pagamento
                </dt>
                <dd>
                  <FiscalStatusBadge
                    :status="String(detailStatus.payment_status || 'UNSUPPORTED')"
                    show-hint
                  />
                </dd>
              </div>
              <div>
                <dt class="text-muted">
                  Último sync
                </dt>
                <dd class="font-medium">
                  {{ formatDateTime(String(detailStatus.last_synced_at || '') || null) }}
                </dd>
              </div>
              <div>
                <dt class="text-muted">
                  Cobertura
                </dt>
                <dd class="font-medium">
                  {{ detailStatus.coverage || 'PARTIAL' }}
                </dd>
              </div>
            </dl>

            <div>
              <h3 class="mb-2 text-sm font-medium">
                Eventos eSocial
              </h3>
              <div
                v-if="!detailEvents.length"
                class="text-sm text-muted"
              >
                Nenhum evento retornado para a competência.
              </div>
              <ul
                v-else
                class="divide-y divide-default text-sm"
              >
                <li
                  v-for="(ev, i) in detailEvents"
                  :key="String(ev.id || i)"
                  class="flex items-start justify-between gap-2 py-2"
                >
                  <div class="min-w-0">
                    <p class="font-medium text-highlighted">
                      {{ ev.event_label || ev.event_code || `Evento #${ev.id || i + 1}` }}
                    </p>
                    <p class="text-xs text-muted">
                      {{ formatDateTime(String(ev.observed_at || ev.created_at || '') || null) }}
                      <template v-if="ev.content_sha256">
                        · sha {{ String(ev.content_sha256).slice(0, 10) }}…
                      </template>
                    </p>
                  </div>
                  <FiscalStatusBadge
                    v-if="ev.status || ev.situation"
                    :status="String(ev.status || ev.situation)"
                  />
                </li>
              </ul>
            </div>

            <div>
              <h3 class="mb-2 text-sm font-medium">
                Divergências e limitações
              </h3>
              <div
                v-if="!detailDivergences.length"
                class="text-sm text-muted"
              >
                Nenhuma divergência eSocial retornada.
              </div>
              <ul
                v-else
                class="divide-y divide-default text-sm"
              >
                <li
                  v-for="(div, i) in detailDivergences"
                  :key="`${div.code || 'lim'}-${i}`"
                  class="flex items-start justify-between gap-2 py-2"
                >
                  <div class="min-w-0">
                    <p class="font-medium text-highlighted">
                      {{ div.title || div.code || `Item #${i + 1}` }}
                    </p>
                    <p class="text-xs text-muted">
                      {{ div.detail || '—' }}
                    </p>
                  </div>
                  <FiscalStatusBadge
                    v-if="div.situation || div.severity"
                    :status="String(div.situation || div.severity)"
                  />
                </li>
              </ul>
            </div>

            <UButton
              v-if="detailClient"
              size="sm"
              color="neutral"
              variant="outline"
              label="Painel do cliente"
              :to="clientHref(detailClient.client_id)"
            />
          </div>
        </template>
      </USlideover>
    </template>
  </MonitoringModuleTable>
</template>

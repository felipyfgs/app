<script setup lang="ts">
/**
 * FGTS / eSocial — cobertura parcial permanente + estados UNSUPPORTED honestos.
 * Fechamento, totalização, eventos e divergências no detalhe. Task 7.10
 * Sem ação de scraping / portal humano.
 */
import type { TableColumn } from '@nuxt/ui'
import type { FgtsCoverageManifest } from '~/types/api'
import type { FgtsClientDetail, FgtsClientRow } from '~/types/fiscal-modules'

const FiscalStatusBadge = resolveComponent('FiscalStatusBadge')
const FiscalClientCell = resolveComponent('FiscalClientCell')
const UButton = resolveComponent('UButton')

const api = useApi()

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
  dataOrigin,
  isSynthetic,
  lastValidAt,
  refresh,
  selectKpi
} = useFiscalModulePortfolio('fgts')

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
  return `/monitoring/clients/${id}?tab=fgts`
}

function onClientId(id: number | null) {
  clientId.value = id != null && id > 0 ? String(id) : ''
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

function formatLimitations(items: FgtsCoverageManifest['limitations'] | undefined): string {
  if (!Array.isArray(items) || !items.length) return '—'
  return items.map((l) => {
    if (typeof l === 'string') return l
    if (l && typeof l === 'object') {
      const o = l as Record<string, unknown>
      return String(o.title || o.detail || o.message || o.code || 'limitação')
    }
    return String(l)
  }).join(' · ')
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
        competence_period_key: d.competence_period_key || competence.value || undefined,
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
    cell: ({ row }) => String(
      row.original.competence
      || detailOf(row.original).competence_period_key
      || '—'
    )
  },
  {
    id: 'closure',
    header: 'Fechamento',
    cell: ({ row }) => h(FiscalStatusBadge, {
      status: String(detailOf(row.original).closure_status || row.original.situation || 'UNKNOWN'),
      showHint: true
    })
  },
  {
    id: 'totalization',
    header: 'Totalização',
    cell: ({ row }) => h(FiscalStatusBadge, {
      status: String(detailOf(row.original).totalization_status || 'UNKNOWN'),
      showHint: true
    })
  },
  {
    id: 'guide',
    header: 'Guia FGTS Digital',
    cell: ({ row }) => h(FiscalStatusBadge, {
      status: String(detailOf(row.original).guide_status || 'UNSUPPORTED'),
      showHint: true
    })
  },
  {
    id: 'payment',
    header: 'Pagamento',
    cell: ({ row }) => h(FiscalStatusBadge, {
      status: String(detailOf(row.original).payment_status || 'UNSUPPORTED'),
      showHint: true
    })
  },
  {
    id: 'synced',
    header: 'Último sync',
    cell: ({ row }) => formatDateTime(
      String(detailOf(row.original).last_synced_at || row.original.last_consulted_at || '') || null
    )
  },
  {
    id: 'actions',
    header: '',
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
  <FiscalModuleTable
    title="FGTS (parcial eSocial)"
    panel-id="monitoring-fgts"
    description="Monitoramento parcial via eventos eSocial. Guia e pagamento do FGTS Digital permanecem Não suportado."
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
    :total-clients="totalClients"
    :counters="counters"
    :data-origin="dataOrigin"
    :is-synthetic="isSynthetic"
    :last-good-at="lastValidAt"
    show-competence-filter
    show-client-picker
    empty-title="Nenhum cliente FGTS/eSocial na carteira"
    empty-description="A API do read model não retornou linhas. Nada foi inventado."
    @update:page="page = $event"
    @update:q="q = $event"
    @update:situation="situation = $event"
    @update:competence="competence = $event"
    @update:client-id="onClientId"
    @refresh="refresh"
    @kpi-select="selectKpi"
  >
    <template #navbar-actions>
      <FiscalMonitoringPortfolioActions
        module-key="fgts"
        :client-id="clientId"
        :competence="competence"
        :situation="situation"
        :q="q"
        @refreshed="refresh"
      />
    </template>

    <template #utilities>
      <!-- Banner permanente de cobertura parcial — task 7.10 -->
      <UAlert
        color="warning"
        icon="i-lucide-ban"
        title="Cobertura parcial — FGTS Digital não integrado"
        :description="coverage?.coverage_label
          || (rows[0] ? detailOf(rows[0]).partial_coverage_notice : null)
          || 'Monitoramento via eventos eSocial. Guia e pagamento do FGTS Digital são Não suportado (sem API pública M2M). Não há automação de portal humano.'"
        class="w-full"
        data-testid="fgts-partial-banner"
      />

      <UAlert
        v-if="coverageError"
        color="warning"
        icon="i-lucide-triangle-alert"
        :title="coverageError"
        class="w-full"
      />

      <UPageCard
        v-if="coverage"
        variant="subtle"
        class="w-full"
        title="Estados independentes"
        description="Cada eixo tem situação própria — nunca inferimos «em dia» para guia/pagamento."
      >
        <ul class="grid gap-2 text-sm sm:grid-cols-2">
          <li
            v-for="(label, key) in coverage.independent_states || {}"
            :key="key"
            class="rounded-md bg-elevated/50 px-3 py-2"
          >
            <span class="font-medium text-highlighted">{{ key }}</span>
            <span class="mt-0.5 block text-muted">{{ label }}</span>
          </li>
        </ul>
        <p
          v-if="coverage.limitations?.length"
          class="mt-3 text-xs text-muted"
        >
          Limitações:
          {{ formatLimitations(coverage.limitations) }}
        </p>
        <p class="mt-2 text-xs text-muted">
          Portal fallback: {{ coverage.portal_fallback ? 'sim' : 'não' }}
          · Scraping: {{ coverage.scraping_allowed ? 'sim' : 'não' }}
          · Declara débito FGTS Digital: {{ coverage.declares_fgts_digital_debt ? 'sim' : 'não' }}
        </p>
      </UPageCard>

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
            <UAlert
              color="warning"
              icon="i-lucide-ban"
              title="Guia e pagamento: Não suportado"
              description="Sem API pública M2M do FGTS Digital. Não há scraping, CAPTCHA ou portal Gov.br."
            />

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
  </FiscalModuleTable>
</template>

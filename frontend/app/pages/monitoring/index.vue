<script setup lang="ts">
/**
 * Dashboard Fiscal — arquétipo Home.
 * KPIs gerais, cobertura por módulo, carteira em atenção, últimas execuções e atalhos.
 * Sem arrays sintéticos: erro/vazio honestos.
 */
import type { FiscalFinding, FiscalMonitoringRun, FiscalPendingItem } from '~/types/api'
import type { FiscalModuleOverview, FiscalPortfolioModuleKey } from '~/types/fiscal-modules'
import {
  FISCAL_MODULE_LABELS,
  FISCAL_MODULE_PATHS,
  FISCAL_PORTFOLIO_MODULE_KEYS,
  isSyntheticFiscalOrigin
} from '~/types/fiscal-modules'

const api = useApi()
const toast = useToast()
const { sessionEpoch } = useDashboard()

const loading = ref(false)
const loadError = ref<string | null>(null)
const partialErrors = ref<string[]>([])
const lastValidAt = ref<string | null>(null)

const pendingCount = ref<number | null>(null)
const findingsCount = ref<number | null>(null)
const runsCount = ref<number | null>(null)
const pendingPreview = ref<FiscalPendingItem[]>([])
const findingsPreview = ref<FiscalFinding[]>([])
const recentRuns = ref<FiscalMonitoringRun[]>([])
const moduleOverviews = ref<Array<{ key: FiscalPortfolioModuleKey, overview: FiscalModuleOverview | null, error?: string }>>([])

/** Overviews LIVE (não sintéticos) — indicadores produtivos excluem DEMO/SIMULATED. */
const productiveModuleOverviews = computed(() =>
  moduleOverviews.value.filter(m =>
    m.overview
    && !isSyntheticFiscalOrigin(m.overview.data_origin)
  )
)

const hasSyntheticModules = computed(() =>
  moduleOverviews.value.some(m => isSyntheticFiscalOrigin(m.overview?.data_origin))
)

const kpis = computed(() => {
  const loadingPlaceholder = loading.value
  const display = (v: number | null) => {
    if (loadingPlaceholder && v === null) return '…'
    return v ?? '—'
  }
  // Contagem de módulos com erro só em overviews produtivos.
  const modulesWithError = productiveModuleOverviews.value.filter(
    m => (m.overview?.counters?.error ?? 0) > 0
  ).length
  return [
    {
      key: 'pending',
      title: 'Pendências',
      icon: 'i-lucide-circle-dashed',
      value: display(pendingCount.value),
      to: '/monitoring/declarations',
      critical: (pendingCount.value ?? 0) > 0,
      tone: 'warning' as const
    },
    {
      key: 'findings',
      title: 'Findings',
      icon: 'i-lucide-triangle-alert',
      value: display(findingsCount.value),
      to: '/monitoring/sitfis',
      critical: (findingsCount.value ?? 0) > 0,
      tone: 'warning' as const
    },
    {
      key: 'runs',
      title: 'Execuções',
      icon: 'i-lucide-activity',
      value: display(runsCount.value),
      to: '/monitoring'
    },
    {
      key: 'module_errors',
      title: 'Com erro',
      icon: 'i-lucide-circle-x',
      value: display(loadingPlaceholder && !moduleOverviews.value.length ? null : modulesWithError),
      to: '/monitoring/sitfis',
      critical: modulesWithError > 0,
      tone: 'error' as const
    }
  ]
})

/** Painéis secundários em acordeão (não competem com KPIs). */
const panelItems = [
  { label: 'Cobertura', icon: 'i-lucide-layers', value: 'coverage', slot: 'coverage' as const },
  { label: 'Atenção', icon: 'i-lucide-bell', value: 'attention', slot: 'attention' as const },
  { label: 'Execuções', icon: 'i-lucide-play', value: 'runs', slot: 'runs' as const }
]

const coverageRows = computed(() =>
  moduleOverviews.value.map((m) => {
    const o = m.overview
    return {
      key: m.key,
      label: FISCAL_MODULE_LABELS[m.key],
      to: FISCAL_MODULE_PATHS[m.key],
      coverage: o?.coverage ?? null,
      total: o?.total_clients ?? null,
      attention: o?.counters?.attention ?? null,
      pending: o?.counters?.pending ?? null,
      error: o?.counters?.error ?? null,
      origin: o?.data_origin ?? null,
      synthetic: isSyntheticFiscalOrigin(o?.data_origin),
      loadError: m.error || null
    }
  })
)

const attentionItems = computed(() => {
  const fromFindings = findingsPreview.value.map(f => ({
    id: `f-${f.id}`,
    title: f.title || f.code || `Finding #${f.id}`,
    detail: f.detail || '—',
    clientId: f.client_id,
    situation: f.situation || f.severity,
    to: f.client_id ? `/monitoring/clients/${f.client_id}/findings` : '/monitoring/sitfis'
  }))
  const fromPending = pendingPreview.value.map(p => ({
    id: `p-${p.id}`,
    title: p.title || p.code || `Pendência #${p.id}`,
    detail: p.detail || '—',
    clientId: p.client_id,
    situation: p.situation || p.status,
    to: p.client_id ? `/monitoring/clients/${p.client_id}/pending` : '/monitoring/declarations'
  }))
  return [...fromFindings, ...fromPending].slice(0, 10)
})

function totalFrom(body: Record<string, unknown>, dataLen: number): number {
  const t = body.total as number | undefined
  const mt = (body.meta as { total?: number } | undefined)?.total
  if (typeof t === 'number') return t
  if (typeof mt === 'number') return mt
  return dataLen
}

async function load() {
  const epochAtStart = sessionEpoch.value
  loading.value = true
  partialErrors.value = []
  try {
    const [pendingRes, findingsRes, runsRes, ...moduleRes] = await Promise.allSettled([
      api.fiscal.pending({ per_page: 8, status: 'OPEN' }),
      api.fiscal.findings({ per_page: 8, active_only: true }),
      api.fiscal.runs.list({ per_page: 8 }),
      ...FISCAL_PORTFOLIO_MODULE_KEYS.map(key =>
        api.fiscal.modules.overview(key).then(r => ({ key, data: r.data }))
      )
    ])

    // Troca de office durante o request: descarta resposta (não mistura tenants).
    if (epochAtStart !== sessionEpoch.value) return

    let anyOk = false

    if (pendingRes.status === 'fulfilled') {
      anyOk = true
      const body = pendingRes.value as Record<string, unknown>
      const data = (body.data as FiscalPendingItem[]) || []
      pendingPreview.value = data
      pendingCount.value = totalFrom(body, data.length)
    } else {
      pendingCount.value = null
      pendingPreview.value = []
      partialErrors.value.push('Pendências')
    }

    if (findingsRes.status === 'fulfilled') {
      anyOk = true
      const body = findingsRes.value as Record<string, unknown>
      const data = (body.data as FiscalFinding[]) || []
      findingsPreview.value = data
      findingsCount.value = totalFrom(body, data.length)
    } else {
      findingsCount.value = null
      findingsPreview.value = []
      partialErrors.value.push('Findings')
    }

    if (runsRes.status === 'fulfilled') {
      anyOk = true
      const body = runsRes.value as Record<string, unknown>
      const data = (body.data as FiscalMonitoringRun[]) || []
      recentRuns.value = data
      runsCount.value = totalFrom(body, data.length)
    } else {
      recentRuns.value = []
      runsCount.value = null
      partialErrors.value.push('Execuções')
    }

    const modules: typeof moduleOverviews.value = []
    FISCAL_PORTFOLIO_MODULE_KEYS.forEach((key, i) => {
      const res = moduleRes[i]
      if (res && res.status === 'fulfilled') {
        anyOk = true
        const val = res.value as { key: FiscalPortfolioModuleKey, data: FiscalModuleOverview }
        modules.push({ key: val.key, overview: val.data })
      } else {
        modules.push({
          key,
          overview: null,
          error: 'Falha ao carregar overview'
        })
        partialErrors.value.push(FISCAL_MODULE_LABELS[key])
      }
    })
    moduleOverviews.value = modules

    if (anyOk) {
      lastValidAt.value = new Date().toISOString()
      loadError.value = null
    } else {
      loadError.value = 'Não foi possível carregar o dashboard fiscal. Nenhum KPI inventado.'
      toast.add({ title: loadError.value, color: 'error' })
    }
  } finally {
    if (epochAtStart === sessionEpoch.value) {
      loading.value = false
    }
  }
}

watch(sessionEpoch, () => {
  void load()
})

onMounted(load)
</script>

<template>
  <UDashboardPanel id="monitoring-dashboard">
    <template #header>
      <UDashboardNavbar title="Dashboard Fiscal" data-testid="page-navbar">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
        <template #right>
          <span
            v-if="lastValidAt"
            class="hidden text-xs text-muted sm:inline"
          >
            Atualizado: {{ formatDateTime(lastValidAt) }}
          </span>
          <UButton
            color="neutral"
            variant="ghost"
            icon="i-lucide-refresh-cw"
            square
            aria-label="Atualizar dashboard fiscal"
            :loading="loading"
            @click="load"
          />
        </template>
      </UDashboardNavbar>

      <UDashboardToolbar data-testid="page-toolbar">
        <template #left>
          <MonitoringModuleNav active="dashboard" />
        </template>
      </UDashboardToolbar>
    </template>

    <template #body>
      <UAlert
        v-if="loadError"
        color="error"
        icon="i-lucide-circle-x"
        :title="loadError"
        class="mb-4"
      >
        <template #actions>
          <UButton
            size="xs"
            color="neutral"
            variant="outline"
            label="Tentar de novo"
            @click="load"
          />
        </template>
      </UAlert>

      <UAlert
        v-else-if="partialErrors.length"
        color="warning"
        icon="i-lucide-triangle-alert"
        :title="`Falha parcial: ${partialErrors.join(', ')}`"
        class="mb-4"
      >
        <template #actions>
          <UButton
            size="xs"
            color="neutral"
            variant="outline"
            label="Retry"
            @click="load"
          />
        </template>
      </UAlert>

      <UAlert
        v-if="hasSyntheticModules"
        color="warning"
        variant="subtle"
        icon="i-lucide-flask-conical"
        title="Conteúdo sintético — sem validade fiscal (excluído dos KPIs produtivos)"
        class="mb-4"
        data-testid="dashboard-synthetic-alert"
      />

      <ShellKpiStrip
        class="mb-6"
        test-id="fiscal-kpis"
        :items="kpis"
        :loading="loading"
        :columns="4"
      />

      <ShellPanelAccordion
        class="mb-4 lg:mb-6"
        :items="panelItems"
        type="multiple"
        :default-value="['coverage']"
        test-id="monitoring-panels"
      >
        <template #coverage>
          <div
            v-if="loading && !coverageRows.length"
            class="py-4 text-center text-sm text-muted"
            data-testid="hub-coverage-loading"
          >
            Carregando…
          </div>
          <MonitoringTableEmptyState
            v-else-if="!coverageRows.length"
            kind="empty"
            title="Sem cobertura"
            description="Nenhum módulo com overview disponível."
          />
          <ul
            v-if="coverageRows.length"
            class="divide-y divide-default"
          >
            <li
              v-for="row in coverageRows"
              :key="row.key"
              class="flex flex-wrap items-center justify-between gap-3 py-3"
            >
              <div class="min-w-0">
                <NuxtLink
                  :to="row.to"
                  class="font-medium text-highlighted hover:text-primary"
                >
                  {{ row.label }}
                </NuxtLink>
                <p
                  v-if="row.loadError"
                  class="truncate text-xs text-error"
                >
                  {{ row.loadError }}
                </p>
                <p
                  v-else
                  class="truncate text-xs text-muted"
                >
                  {{ row.total ?? '—' }} · p.{{ row.pending ?? '—' }}
                  · a.{{ row.attention ?? '—' }} · e.{{ row.error ?? '—' }}
                </p>
              </div>
              <div class="flex flex-wrap items-center gap-2">
                <FiscalDataOriginBadge
                  v-if="row.origin"
                  :origin="row.origin"
                  :hide-live="false"
                  data-testid="dashboard-module-origin"
                />
                <FiscalCoverageBadge
                  v-if="row.coverage"
                  :coverage="row.coverage"
                />
                <UButton
                  size="xs"
                  color="neutral"
                  variant="ghost"
                  label="Abrir"
                  :to="row.to"
                />
              </div>
            </li>
          </ul>
        </template>

        <template #attention>
          <!-- Casca de lista sempre presente (padrão ModuleTable empty interno) -->
          <div
            v-if="loading && !attentionItems.length"
            class="py-4 text-center text-sm text-muted"
            data-testid="hub-attention-loading"
          >
            Carregando…
          </div>
          <MonitoringTableEmptyState
            v-else-if="!attentionItems.length"
            kind="empty"
            title="Nada em atenção"
            description="Sem itens de atenção no momento."
          />
          <ul
            v-if="attentionItems.length"
            class="divide-y divide-default"
          >
            <li
              v-for="item in attentionItems"
              :key="item.id"
              class="flex items-start justify-between gap-3 py-3"
            >
              <div class="min-w-0">
                <NuxtLink
                  :to="item.to"
                  class="truncate font-medium text-highlighted hover:text-primary"
                >
                  {{ item.title }}
                </NuxtLink>
                <p class="truncate text-xs text-muted">
                  #{{ item.clientId ?? '—' }} · {{ item.detail }}
                </p>
              </div>
              <FiscalStatusBadge :status="item.situation" />
            </li>
          </ul>
        </template>

        <template #runs>
          <div
            v-if="loading && !recentRuns.length"
            class="py-4 text-center text-sm text-muted"
            data-testid="hub-runs-loading"
          >
            Carregando…
          </div>
          <MonitoringTableEmptyState
            v-else-if="!recentRuns.length"
            kind="empty"
            title="Nenhuma execução"
            description="Sem execuções recentes."
          />
          <ul
            v-if="recentRuns.length"
            class="divide-y divide-default"
          >
            <li
              v-for="run in recentRuns"
              :key="run.id"
              class="flex items-start justify-between gap-3 py-3"
            >
              <div class="min-w-0">
                <p class="truncate font-medium text-highlighted">
                  #{{ run.id }} · {{ run.system_code }}/{{ run.service_code }}
                </p>
                <p class="truncate text-xs text-muted">
                  #{{ run.client_id ?? '—' }}
                  · {{ formatDateTime(run.started_at || run.created_at) }}
                </p>
              </div>
              <div class="flex flex-col items-end gap-1">
                <FiscalStatusBadge :status="run.situation || run.status" />
                <UButton
                  v-if="run.client_id"
                  size="xs"
                  color="neutral"
                  variant="ghost"
                  label="Cliente"
                  :to="`/monitoring/clients/${run.client_id}/runs`"
                />
              </div>
            </li>
          </ul>
        </template>
      </ShellPanelAccordion>

      <div class="mt-4 flex flex-wrap gap-2 lg:mt-6">
        <UButton
          v-for="key in FISCAL_PORTFOLIO_MODULE_KEYS"
          :key="key"
          :to="FISCAL_MODULE_PATHS[key]"
          color="neutral"
          variant="soft"
          size="sm"
          :label="FISCAL_MODULE_LABELS[key]"
        />
      </div>
    </template>
  </UDashboardPanel>
</template>

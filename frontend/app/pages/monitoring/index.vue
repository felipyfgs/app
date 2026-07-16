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
  FISCAL_PORTFOLIO_MODULE_KEYS
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

const kpis = computed(() => [
  {
    title: 'Pendências abertas',
    icon: 'i-lucide-circle-dashed',
    value: pendingCount.value,
    to: '/monitoring/declarations?situation=PENDING',
    critical: (pendingCount.value ?? 0) > 0
  },
  {
    title: 'Findings ativos',
    icon: 'i-lucide-triangle-alert',
    value: findingsCount.value,
    to: '/monitoring/sitfis?situation=ATTENTION',
    critical: (findingsCount.value ?? 0) > 0
  },
  {
    title: 'Execuções recentes',
    icon: 'i-lucide-activity',
    value: runsCount.value,
    to: '/monitoring',
    critical: false
  },
  {
    title: 'Módulos com erro',
    icon: 'i-lucide-circle-x',
    value: moduleOverviews.value.filter(m => (m.overview?.counters?.error ?? 0) > 0).length || null,
    to: '/monitoring/sitfis?situation=ERROR',
    critical: true
  }
])

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
      isSynthetic: o?.is_synthetic ?? false,
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
    to: f.client_id ? `/monitoring/clients/${f.client_id}?tab=findings` : '/monitoring/sitfis'
  }))
  const fromPending = pendingPreview.value.map(p => ({
    id: `p-${p.id}`,
    title: p.title || p.code || `Pendência #${p.id}`,
    detail: p.detail || '—',
    clientId: p.client_id,
    situation: p.situation || p.status,
    to: p.client_id ? `/monitoring/clients/${p.client_id}?tab=pending` : '/monitoring/declarations'
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
  <DashboardListShell
    panel-id="monitoring-dashboard"
    title="Dashboard Fiscal"
  >
    <template #navbar-right>
      <span
            v-if="lastValidAt"
            class="hidden text-xs text-muted sm:inline"
          >
            Atualizado: {{ formatDateTime(lastValidAt) }}
          </span>
    </template>
    <template #toolbar>
      <UDashboardToolbar data-testid="page-toolbar">
        <template #left>
          <MonitoringModuleNav active="dashboard" />
        </template>
        <template #right>
          <UButton
            color="neutral"
            variant="ghost"
            icon="i-lucide-refresh-cw"
            label="Atualizar"
            :loading="loading"
            @click="load"
          />
        </template>
      </UDashboardToolbar>
    </template>
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
        title="Falha parcial ao carregar o dashboard"
        :description="`Seções com erro: ${partialErrors.join(', ')}. Os demais dados da API permanecem visíveis.`"
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

      <FiscalDemoBanner
        v-if="moduleOverviews.some(m => m.overview?.is_synthetic)"
        origin="DEMO"
        :is-synthetic="true"
      />

      <UPageGrid
        data-testid="fiscal-kpis"
        class="mb-6 gap-4 sm:gap-6 lg:grid-cols-4 lg:gap-px"
      >
        <UPageCard
          v-for="(stat, index) in kpis"
          :key="index"
          :icon="stat.icon"
          :title="stat.title"
          :to="stat.to"
          variant="subtle"
          :ui="{
            container: 'gap-y-1.5',
            wrapper: 'items-start',
            leading: 'p-2.5 rounded-full bg-primary/10 ring ring-inset ring-primary/25 flex-col',
            title: 'font-normal text-muted text-xs uppercase'
          }"
          class="lg:rounded-none first:rounded-l-lg last:rounded-r-lg hover:z-1"
        >
          <div class="flex items-center gap-2">
            <span class="text-2xl font-semibold text-highlighted">
              {{ loading && stat.value === null ? '…' : (stat.value ?? '—') }}
            </span>
            <UIcon
              v-if="stat.critical && stat.value !== null && stat.value !== 0"
              name="i-lucide-triangle-alert"
              class="size-4 shrink-0 text-error"
              aria-label="Requer atenção"
            />
          </div>
        </UPageCard>
      </UPageGrid>

      <UPageCard
        title="Cobertura por módulo"
        description="Overview do read model — contadores no escopo completo da carteira."
        variant="subtle"
        class="mb-4 lg:mb-6"
      >
        <div
          v-if="loading && !coverageRows.length"
          class="py-6 text-sm text-muted"
        >
          Carregando…
        </div>
        <ul
          v-else
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
                class="text-xs text-error"
              >
                {{ row.loadError }}
              </p>
              <p
                v-else
                class="text-xs text-muted"
              >
                {{ row.total ?? '—' }} cliente(s)
                · pend. {{ row.pending ?? '—' }}
                · aten. {{ row.attention ?? '—' }}
                · erro {{ row.error ?? '—' }}
              </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <FiscalCoverageBadge
                v-if="row.coverage"
                :coverage="row.coverage"
              />
              <FiscalDataOriginBadge
                v-if="row.origin"
                :origin="row.origin"
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
      </UPageCard>

      <div class="grid gap-4 lg:grid-cols-2 lg:gap-6">
        <UPageCard
          title="Carteira em atenção"
          description="Findings e pendências retornados pela API."
          variant="subtle"
        >
          <div
            v-if="loading && !attentionItems.length"
            class="py-6 text-sm text-muted"
          >
            Carregando…
          </div>
          <div
            v-else-if="!attentionItems.length"
            class="py-6 text-sm text-muted"
          >
            <UEmpty icon="i-lucide-bell-off" title="Nenhum item de atenção" size="sm" />
          </div>
          <ul
            v-else
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
                  Cliente #{{ item.clientId ?? '—' }} · {{ item.detail }}
                </p>
              </div>
              <FiscalStatusBadge :status="item.situation" />
            </li>
          </ul>
        </UPageCard>

        <UPageCard
          title="Últimas execuções"
          description="Runs do núcleo de monitoramento."
          variant="subtle"
        >
          <div
            v-if="loading && !recentRuns.length"
            class="py-6 text-sm text-muted"
          >
            Carregando…
          </div>
          <div
            v-else-if="!recentRuns.length"
            class="py-6 text-sm text-muted"
          >
            <UEmpty icon="i-lucide-play" title="Nenhuma execução retornada" size="sm" />
          </div>
          <ul
            v-else
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
                  Cliente #{{ run.client_id ?? '—' }}
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
                  :to="`/monitoring/clients/${run.client_id}?tab=runs`"
                />
              </div>
            </li>
          </ul>
        </UPageCard>
      </div>

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
  </DashboardListShell>
</template>

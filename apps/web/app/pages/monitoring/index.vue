<script setup lang="ts">
/**
 * Dashboard Fiscal — insights densos (padrão MonitorHub, UX própria).
 * Fonte: GET /api/v1/fiscal/monitoring/insights — fail-closed, sem KPI inventado.
 */
import type { MonitoringInsightsPayload } from '~/types/monitoring-insights'
import { formatDateTime } from '~/utils/format'

const api = useApi()
const toast = useToast()
const { sessionEpoch } = useDashboard()

const loading = ref(false)
const loadError = ref<string | null>(null)
const insights = ref<MonitoringInsightsPayload | null>(null)

const partialErrors = computed(() => insights.value?.partial_errors ?? [])
const lastValidAt = computed(() => insights.value?.as_of ?? null)

function sectionError(key: string): string | null {
  if (!partialErrors.value.includes(key)) return null
  return 'Falha ao carregar esta seção.'
}

const kpis = computed(() => {
  const data = insights.value
  const loadingPlaceholder = loading.value && !data
  const display = (v: number | null | undefined) => {
    if (loadingPlaceholder && (v === null || v === undefined)) return '…'
    return v ?? '—'
  }
  const pending = data?.kpis.pending_open ?? null
  const findings = data?.kpis.findings_active ?? null
  const modulesWithError = data?.kpis.modules_with_error ?? null
  return [
    {
      key: 'pending',
      title: 'Pendências',
      icon: 'i-lucide-circle-dashed',
      value: display(pending),
      to: '/monitoring/sitfis',
      critical: (pending ?? 0) > 0,
      tone: 'warning' as const
    },
    {
      key: 'findings',
      title: 'Findings',
      icon: 'i-lucide-triangle-alert',
      value: display(findings),
      to: '/monitoring/sitfis',
      critical: (findings ?? 0) > 0,
      tone: 'warning' as const
    },
    {
      key: 'mailbox',
      title: 'e-CAC (outros)',
      icon: 'i-lucide-mail',
      value: display(data?.mailbox?.buckets.other ?? null),
      to: '/monitoring/mailbox'
    },
    {
      key: 'module_errors',
      title: 'Com erro',
      icon: 'i-lucide-circle-x',
      value: display(modulesWithError),
      to: '/monitoring/sitfis',
      critical: (modulesWithError ?? 0) > 0,
      tone: 'error' as const
    }
  ]
})

async function load() {
  const epochAtStart = sessionEpoch.value
  loading.value = true
  loadError.value = null
  try {
    const res = await api.fiscal.monitoringInsights()
    if (epochAtStart !== sessionEpoch.value) return
    insights.value = res.data
  } catch {
    if (epochAtStart !== sessionEpoch.value) return
    insights.value = null
    loadError.value = 'Não foi possível carregar o dashboard fiscal. Nenhum KPI inventado.'
    toast.add({ title: loadError.value, color: 'error' })
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
      <UDashboardNavbar
        title="Dashboard"
        data-testid="page-navbar"
      >
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
    </template>

    <template #body>
      <UAlert
        v-if="loadError"
        color="error"
        icon="i-lucide-circle-x"
        :title="loadError"
        class="mb-4"
        data-testid="insights-load-error"
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
        data-testid="insights-partial-error"
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

      <ShellKpiStrip
        class="mb-6"
        test-id="fiscal-kpis"
        :items="kpis"
        :loading="loading"
        :columns="4"
      />

      <div
        v-if="!loadError"
        class="grid grid-cols-1 gap-4 lg:grid-cols-12 lg:gap-6"
        data-testid="monitoring-insights-grid"
      >
        <div class="flex flex-col gap-4 lg:col-span-8 lg:gap-6">
          <MonitoringInsightsPendingCard
            :data="insights?.pending ?? null"
            :loading="loading"
            :error="sectionError('pending')"
          />
          <MonitoringInsightsRbt12ChartCard
            :data="insights?.rbt12 ?? null"
            :loading="loading"
            :error="sectionError('rbt12')"
          />
          <MonitoringInsightsMailboxBucketsCard
            :data="insights?.mailbox ?? null"
            :loading="loading"
            :error="sectionError('mailbox')"
          />
        </div>

        <div class="flex flex-col gap-4 lg:col-span-4 lg:gap-6">
          <MonitoringInsightsNotificationsFeed
            :items="insights?.notifications?.items ?? null"
            :loading="loading"
            :error="sectionError('notifications')"
          />
          <MonitoringInsightsDeclarationsAbsenceCard
            :data="insights?.declarations_absence ?? null"
            :loading="loading"
            :error="sectionError('declarations_absence')"
          />
          <MonitoringInsightsSitfisDonutCard
            :data="insights?.sitfis ?? null"
            :loading="loading"
            :error="sectionError('sitfis')"
          />
          <MonitoringInsightsObligationsProgressCard
            :items="insights?.obligations_progress ?? null"
            :loading="loading"
            :error="sectionError('obligations_progress')"
          />
        </div>
      </div>

      <ShellPanelAccordion
        class="mt-6"
        :items="[{ label: 'Consulta manual', icon: 'i-lucide-search', value: 'manual', slot: 'manual' as const }]"
        type="single"
        test-id="monitoring-manual-section"
      >
        <template #manual-body>
          <MonitoringManualConsultExplorer data-testid="monitoring-manual-consult-explorer" />
        </template>
      </ShellPanelAccordion>
    </template>
  </UDashboardPanel>
</template>

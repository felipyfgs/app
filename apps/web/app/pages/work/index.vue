<script setup lang="ts">
import type { LocationQueryRaw } from 'vue-router'
import type { WorkDepartment, WorkKpis, WorkRisk } from '~/types/work'
import { apiErrorMessage } from '~/utils/api-error'
import {
  buildWorkDashboardKpis,
  buildWorkDepartmentRows,
  workCompletionPercent,
  workOperationalLevel,
  workQueueLegacyTarget
} from '~/utils/work-strategic-dashboard'
import type { DashboardKpiItem } from '~/utils/kpi-ui'
import {
  formatCompetence,
  formatDueDate,
  workRiskColor,
  workRiskIcon,
  workRiskLabel,
  type SemanticColor
} from '~/utils/work-labels'

const route = useRoute()
const legacyTarget = workQueueLegacyTarget(route.query as Record<string, unknown>)

if (legacyTarget) {
  await navigateTo({
    path: legacyTarget.path,
    query: legacyTarget.query as LocationQueryRaw
  }, { replace: true })
}

const api = useApi()
const toast = useToast()
const { sessionEpoch } = useDashboard()

const data = ref<WorkKpis | null>(null)
const lastGood = ref<WorkKpis | null>(null)
const departments = ref<WorkDepartment[]>([])
const loading = ref(false)
const error = ref<string | null>(null)
const stale = ref(false)

async function load() {
  const epoch = sessionEpoch.value
  const hadSnapshot = lastGood.value !== null
  loading.value = true

  if (!hadSnapshot) error.value = null

  try {
    const [kpisResult, departmentsResult] = await Promise.allSettled([
      api.work.kpis(),
      api.work.departments.list({ per_page: 100, is_active: true })
    ])

    if (epoch !== sessionEpoch.value) return

    if (kpisResult.status === 'fulfilled') {
      data.value = kpisResult.value.data
      lastGood.value = kpisResult.value.data
      error.value = null
      stale.value = false
    } else {
      const message = apiErrorMessage(kpisResult.reason, 'Não foi possível carregar a visão estratégica.')
      error.value = message

      if (lastGood.value) {
        data.value = lastGood.value
        stale.value = true
      } else {
        data.value = null
        toast.add({ title: message, color: 'error' })
      }
    }

    if (departmentsResult.status === 'fulfilled') {
      departments.value = departmentsResult.value.data || []
    }
  } finally {
    if (epoch === sessionEpoch.value) loading.value = false
  }
}

onMounted(() => {
  if (!legacyTarget) void load()
})

watch(sessionEpoch, () => {
  data.value = null
  lastGood.value = null
  departments.value = []
  error.value = null
  stale.value = false
  if (!legacyTarget) void load()
})

const kpiCards = computed(() => data.value ? buildWorkDashboardKpis(data.value) : [])
const completionPercent = computed(() => data.value ? workCompletionPercent(data.value) : 0)
const departmentRows = computed(() => data.value
  ? buildWorkDepartmentRows(data.value, departments.value)
  : [])
const operationalLevel = computed(() => data.value ? workOperationalLevel(data.value) : null)
const relevantTaskTotal = computed(() => data.value
  ? data.value.kpis.total_open + data.value.kpis.concluidas
  : 0)
const performanceKpis = computed<DashboardKpiItem[]>(() => {
  if (!data.value) return []

  const cards = new Map(kpiCards.value.map(card => [card.key, card]))
  const completed: DashboardKpiItem = {
    key: 'completed',
    title: 'Concluídas',
    value: data.value.kpis.concluidas,
    to: '/work/tasks?tab=concluidas',
    icon: 'i-lucide-circle-check-big',
    tone: 'success'
  }

  return [
    cards.get('open'),
    cards.get('progress'),
    completed,
    cards.get('overdue'),
    cards.get('today'),
    cards.get('fine')
  ].filter((card): card is DashboardKpiItem => Boolean(card))
})
const operationalSummary = computed(() => {
  if (!data.value) return []

  return [
    {
      key: 'open',
      label: 'Abertas',
      value: data.value.kpis.total_open,
      to: '/work/tasks',
      color: 'neutral' as const
    },
    {
      key: 'progress',
      label: 'Em progresso',
      value: data.value.kpis.em_progresso,
      to: '/work/tasks',
      color: 'info' as const
    },
    {
      key: 'completed',
      label: 'Concluídas',
      value: data.value.kpis.concluidas,
      to: '/work/tasks?tab=concluidas',
      color: 'success' as const
    },
    {
      key: 'unassigned',
      label: 'Sem responsável',
      value: data.value.kpis.sem_responsavel,
      to: '/work/tasks',
      color: data.value.kpis.sem_responsavel > 0 ? 'warning' as const : 'neutral' as const
    }
  ]
})
const completionDonutStyle = computed(() => ({
  background: `conic-gradient(var(--ui-primary) 0 ${completionPercent.value}%, var(--ui-bg-accented) ${completionPercent.value}% 100%)`
}))
const levelColorStyle = computed(() => ({
  color: `var(--ui-${operationalLevel.value?.tone || 'primary'})`
}))

const quickLinks = [
  {
    label: 'Tarefas',
    description: 'Priorizar e executar a fila',
    icon: 'i-lucide-list-checks',
    to: '/work/tasks'
  },
  {
    label: 'Processos',
    description: 'Acompanhar entregas e competências',
    icon: 'i-lucide-folder-kanban',
    to: '/work/processes'
  },
  {
    label: 'Calendário',
    description: 'Antecipar prazos operacionais',
    icon: 'i-lucide-calendar-days',
    to: '/work/calendar'
  }
]

const lastUpdated = computed(() => {
  if (!data.value?.generated_at) return null
  try {
    return new Intl.DateTimeFormat('pt-BR', {
      day: '2-digit',
      month: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      timeZone: data.value.office_timezone
    }).format(new Date(data.value.generated_at))
  } catch {
    return null
  }
})

function departmentProgressColor(row: { fine: number, overdue: number }): SemanticColor {
  if (row.fine > 0) return 'error'
  if (row.overdue > 0) return 'warning'
  return 'primary'
}

function primaryRisk(risks: WorkRisk[]): WorkRisk | undefined {
  return risks.find(risk => risk === 'EM_MULTA')
    || risks.find(risk => risk === 'ATRASADA')
    || risks[0]
}
</script>

<template>
  <ShellPagePanel
    id="work-overview"
    test-id="work-strategic-dashboard"
    body-class="gap-5 sm:gap-6"
  >
    <template #header>
      <ShellPageNavbar title="Trabalho">
        <template #right>
          <UButton
            to="/work/tasks"
            label="Abrir tarefas"
            icon="i-lucide-list-checks"
            class="hidden sm:inline-flex"
          />
          <ShellNavbarRefresh
            :loading="loading"
            aria-label="Atualizar visão estratégica"
            test-id="work-dashboard-refresh"
            @click="load"
          />
        </template>
      </ShellPageNavbar>
    </template>

    <template #body>
      <template v-if="!legacyTarget">
        <section
          class="flex min-w-0 flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
          aria-labelledby="work-strategic-heading"
        >
          <div class="min-w-0">
            <p class="mb-1 text-xs font-medium uppercase tracking-wide text-primary">
              Operação do escritório
            </p>
            <h1
              id="work-strategic-heading"
              class="text-2xl font-semibold tracking-tight text-highlighted sm:text-3xl"
            >
              Visão estratégica
            </h1>
            <p class="mt-1 max-w-2xl text-sm text-muted">
              Antecipe riscos, equilibre a carga dos departamentos e direcione a equipe para o que exige atenção agora.
            </p>
          </div>

          <div class="flex shrink-0 flex-wrap items-center gap-2 text-xs text-muted">
            <UBadge
              v-if="data?.today"
              color="neutral"
              variant="subtle"
              icon="i-lucide-calendar-check"
              :label="`Posição em ${formatDueDate(data.today)}`"
            />
            <span v-if="lastUpdated">Atualizado {{ lastUpdated }}</span>
          </div>
        </section>

        <UAlert
          v-if="stale && error"
          color="warning"
          variant="subtle"
          icon="i-lucide-wifi-off"
          title="Mostrando o último snapshot disponível"
          :description="error"
          :actions="[{
            label: 'Tentar novamente',
            color: 'neutral',
            variant: 'subtle',
            onClick: () => load()
          }]"
          data-testid="work-dashboard-stale"
        />

        <template v-if="loading && !data">
          <div
            class="grid grid-cols-2 gap-3 lg:grid-cols-6"
            data-testid="work-dashboard-loading"
            aria-label="Carregando indicadores estratégicos"
          >
            <USkeleton
              v-for="index in 6"
              :key="index"
              class="h-28 rounded-lg"
            />
          </div>
          <div class="grid min-w-0 gap-4 lg:grid-cols-[minmax(0,1.45fr)_minmax(18rem,0.75fr)]">
            <USkeleton class="h-96 rounded-lg" />
            <div class="space-y-4">
              <USkeleton class="h-48 rounded-lg" />
              <USkeleton class="h-40 rounded-lg" />
            </div>
          </div>
        </template>

        <UAlert
          v-else-if="error && !data"
          color="error"
          variant="subtle"
          icon="i-lucide-circle-x"
          title="Visão estratégica indisponível"
          :description="error"
          :actions="[{
            label: 'Tentar novamente',
            color: 'neutral',
            variant: 'subtle',
            onClick: () => load()
          }]"
          data-testid="work-dashboard-error"
        />

        <template v-else-if="data">
          <div class="grid min-w-0 gap-4 xl:grid-cols-[minmax(0,2fr)_minmax(19rem,0.86fr)]">
            <section class="min-w-0" data-testid="work-dashboard-performance">
              <ShellSectionCard
                title="Desempenho geral"
                description="Posição consolidada dos processos e tarefas neste snapshot."
                icon="i-lucide-gauge"
              >
                <div class="grid min-w-0 gap-5 md:grid-cols-[minmax(12rem,0.62fr)_minmax(0,2fr)] md:items-center">
                  <div class="flex min-w-0 flex-col items-center justify-center rounded-lg bg-elevated/60 p-4 text-center ring ring-default">
                    <div
                      class="flex size-28 items-center justify-center rounded-full p-3 sm:size-32"
                      :style="completionDonutStyle"
                      role="img"
                      :aria-label="`${completionPercent}% da carga consolidada concluída`"
                    >
                      <div class="flex size-full items-center justify-center rounded-full bg-default">
                        <span class="text-2xl font-semibold tabular-nums text-highlighted sm:text-3xl">
                          {{ completionPercent }}%
                        </span>
                      </div>
                    </div>
                    <p class="mt-3 text-sm font-medium text-highlighted">
                      Tarefas concluídas
                    </p>
                    <p class="mt-0.5 text-sm text-muted">
                      <strong class="font-semibold tabular-nums text-success">{{ data.kpis.concluidas }}</strong>
                      de {{ relevantTaskTotal }} no consolidado
                    </p>
                  </div>

                  <ShellKpiStrip
                    :items="performanceKpis"
                    :loading="loading"
                    :columns="3"
                    legend="Situação das tarefas"
                    test-id="work-dashboard-kpis"
                  />
                </div>
              </ShellSectionCard>
            </section>

            <section class="min-w-0" data-testid="work-dashboard-level">
              <ShellSectionCard
                title="Nível de desempenho geral"
                description="Conclusão sobre a carga consolidada, com risco atual considerado."
                icon="i-lucide-chart-no-axes-column-increasing"
                class="h-full"
              >
                <div v-if="operationalLevel" class="flex h-full flex-col items-center text-center">
                  <div class="relative mt-1 h-28 w-56 max-w-full" :style="levelColorStyle">
                    <svg
                      viewBox="0 0 200 112"
                      class="size-full"
                      role="img"
                      :aria-label="`Nível ${operationalLevel.label}: ${operationalLevel.percent}% concluído`"
                    >
                      <title>Nível operacional: {{ operationalLevel.label }}</title>
                      <path
                        d="M 20 100 A 80 80 0 0 1 180 100"
                        pathLength="100"
                        fill="none"
                        stroke="var(--ui-bg-accented)"
                        stroke-width="14"
                        stroke-linecap="round"
                      />
                      <path
                        d="M 20 100 A 80 80 0 0 1 180 100"
                        pathLength="100"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="14"
                        stroke-linecap="round"
                        :stroke-dasharray="`${operationalLevel.percent} 100`"
                      />
                    </svg>
                    <div class="absolute inset-x-0 bottom-0">
                      <p class="text-2xl font-semibold tabular-nums text-highlighted">
                        {{ operationalLevel.percent }}%
                      </p>
                      <p class="text-xs font-medium" :style="levelColorStyle">
                        {{ operationalLevel.label }}
                      </p>
                    </div>
                  </div>

                  <p class="mt-3 max-w-xs text-xs leading-5 text-muted">
                    {{ operationalLevel.description }}
                  </p>
                  <UBadge
                    v-if="relevantTaskTotal === 0"
                    class="mt-3"
                    color="neutral"
                    variant="subtle"
                    icon="i-lucide-circle-dashed"
                    label="Sem carga no snapshot"
                  />
                  <UBadge
                    v-else-if="operationalLevel.nextLabel"
                    class="mt-3"
                    :color="operationalLevel.tone"
                    variant="subtle"
                    icon="i-lucide-move-up-right"
                    :label="`Faltam ${operationalLevel.remainingToNext} conclusões para ${operationalLevel.nextLabel}`"
                  />
                  <UBadge
                    v-else
                    class="mt-3"
                    color="success"
                    variant="subtle"
                    icon="i-lucide-trophy"
                    label="Faixa mais alta alcançada"
                  />
                </div>
              </ShellSectionCard>
            </section>
          </div>

          <div class="grid min-w-0 gap-4 xl:grid-cols-[minmax(0,2fr)_minmax(19rem,0.86fr)]">
            <section class="min-w-0" data-testid="work-dashboard-departments">
              <ShellSectionCard
                title="Desempenho da equipe"
                description="Carga, risco e avanço da operação agrupados por departamento."
                icon="i-lucide-users-round"
              >
                <div class="mb-4 flex justify-end">
                  <UBadge color="neutral" variant="subtle" label="Por departamento" />
                </div>

                <template v-if="departmentRows.length">
                  <div class="hidden md:block" data-testid="work-dashboard-departments-table">
                    <table class="w-full table-fixed border-separate border-spacing-0 text-left text-sm">
                      <caption class="sr-only">
                        Desempenho operacional dos departamentos no snapshot atual
                      </caption>
                      <thead>
                        <tr class="bg-elevated/60 text-xs text-muted">
                          <th scope="col" class="w-[27%] rounded-s-lg border-y border-s border-default px-3 py-2 font-medium">
                            Departamento
                          </th>
                          <th scope="col" class="w-[36%] border-y border-default px-3 py-2 font-medium">
                            Situação das tarefas
                          </th>
                          <th scope="col" class="w-[27%] border-y border-default px-3 py-2 font-medium">
                            Desempenho
                          </th>
                          <th scope="col" class="w-[10%] rounded-e-lg border-y border-e border-default px-3 py-2 text-right font-medium">
                            Fila
                          </th>
                        </tr>
                      </thead>
                      <tbody class="divide-y divide-default">
                        <tr v-for="row in departmentRows" :key="String(row.id)">
                          <th scope="row" class="min-w-0 px-3 py-3 font-normal">
                            <NuxtLink :to="row.to" class="block truncate font-medium text-highlighted hover:text-primary hover:underline">
                              {{ row.name }}
                            </NuxtLink>
                            <span class="mt-0.5 block text-xs text-muted">
                              {{ row.open }} abertas · {{ row.completed }} concluídas
                            </span>
                          </th>
                          <td class="px-3 py-3">
                            <div class="flex flex-wrap gap-1">
                              <UBadge
                                color="neutral"
                                variant="subtle"
                                size="sm"
                                :label="`${row.open} abertas`"
                              />
                              <UBadge
                                v-if="row.overdue"
                                color="warning"
                                variant="subtle"
                                size="sm"
                                :label="`${row.overdue} atrasadas`"
                              />
                              <UBadge
                                v-if="row.fine"
                                color="error"
                                variant="subtle"
                                size="sm"
                                :label="`${row.fine} em multa`"
                              />
                              <UBadge
                                v-if="row.unassigned"
                                color="info"
                                variant="subtle"
                                size="sm"
                                :label="`${row.unassigned} sem responsável`"
                              />
                            </div>
                          </td>
                          <td class="px-3 py-3">
                            <div class="flex items-center gap-2">
                              <UProgress
                                :model-value="row.completedPercent"
                                :color="departmentProgressColor(row)"
                                size="sm"
                                :aria-label="`${row.name}: ${row.completedPercent}% concluído`"
                              />
                              <span class="w-10 shrink-0 text-right text-xs font-semibold tabular-nums text-highlighted">
                                {{ row.completedPercent }}%
                              </span>
                            </div>
                          </td>
                          <td class="px-3 py-3 text-right">
                            <UButton
                              :to="row.to"
                              color="neutral"
                              variant="soft"
                              icon="i-lucide-arrow-up-right"
                              square
                              :aria-label="`Abrir fila de ${row.name}`"
                            />
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>

                  <ul
                    class="space-y-3 md:hidden"
                    aria-label="Desempenho dos departamentos"
                    data-testid="work-dashboard-departments-mobile"
                  >
                    <li v-for="row in departmentRows" :key="String(row.id)" class="rounded-lg bg-elevated/60 p-3 ring ring-default">
                      <div class="flex min-w-0 items-start justify-between gap-3">
                        <div class="min-w-0">
                          <NuxtLink :to="row.to" class="block truncate font-medium text-highlighted hover:text-primary hover:underline">
                            {{ row.name }}
                          </NuxtLink>
                          <p class="mt-0.5 text-xs text-muted">
                            {{ row.open }} abertas · {{ row.completed }} concluídas
                          </p>
                        </div>
                        <span class="shrink-0 text-sm font-semibold tabular-nums text-highlighted">
                          {{ row.completedPercent }}%
                        </span>
                      </div>
                      <UProgress
                        class="mt-3"
                        :model-value="row.completedPercent"
                        :color="departmentProgressColor(row)"
                        size="sm"
                        :aria-label="`${row.name}: ${row.completedPercent}% concluído`"
                      />
                      <div class="mt-3 flex flex-wrap items-center gap-1.5">
                        <UBadge
                          v-if="row.overdue"
                          color="warning"
                          variant="subtle"
                          size="sm"
                          :label="`${row.overdue} atrasadas`"
                        />
                        <UBadge
                          v-if="row.fine"
                          color="error"
                          variant="subtle"
                          size="sm"
                          :label="`${row.fine} em multa`"
                        />
                        <UBadge
                          v-if="row.unassigned"
                          color="info"
                          variant="subtle"
                          size="sm"
                          :label="`${row.unassigned} sem responsável`"
                        />
                        <UButton
                          :to="row.to"
                          color="neutral"
                          variant="link"
                          size="xs"
                          label="Abrir fila"
                          trailing-icon="i-lucide-arrow-right"
                          class="ms-auto"
                        />
                      </div>
                    </li>
                  </ul>
                </template>

                <ShellListEmpty
                  v-else
                  title="Sem atividade por departamento"
                  description="As áreas aparecerão aqui quando houver tarefas operacionais no escritório."
                  test-id="work-dashboard-departments-empty"
                >
                  <template #actions>
                    <UButton
                      to="/work/tasks"
                      color="neutral"
                      variant="outline"
                      size="sm"
                      label="Abrir tarefas"
                    />
                  </template>
                </ShellListEmpty>

                <div class="mt-4 border-t border-default pt-4">
                  <UButton
                    to="/work/tasks"
                    color="neutral"
                    variant="ghost"
                    size="sm"
                    label="Ver desempenho na fila"
                    trailing-icon="i-lucide-arrow-right"
                    block
                  />
                </div>
              </ShellSectionCard>
            </section>

            <aside class="min-w-0 space-y-4" aria-label="Atalhos e situação operacional">
              <ShellSectionCard
                title="Acessos rápidos"
                description="Transforme a leitura estratégica em ação."
                icon="i-lucide-route"
                test-id="work-dashboard-quick-links"
              >
                <div class="space-y-2">
                  <UButton
                    v-for="link in quickLinks"
                    :key="link.to"
                    :to="link.to"
                    color="neutral"
                    variant="outline"
                    trailing-icon="i-lucide-chevron-right"
                    class="h-auto min-w-0 justify-start px-3 py-3 text-left"
                    block
                  >
                    <UIcon :name="link.icon" class="size-5 shrink-0 text-primary" />
                    <span class="min-w-0 flex-1">
                      <span class="block font-medium text-highlighted">{{ link.label }}</span>
                      <span class="block truncate text-xs font-normal text-muted">{{ link.description }}</span>
                    </span>
                  </UButton>
                </div>
              </ShellSectionCard>

              <ShellSectionCard
                title="Situação operacional"
                description="Resumo acionável do estoque de tarefas."
                icon="i-lucide-clipboard-list"
                test-id="work-dashboard-operational-summary"
              >
                <div class="space-y-2">
                  <NuxtLink
                    v-for="item in operationalSummary"
                    :key="item.key"
                    :to="item.to"
                    class="flex items-center justify-between gap-3 rounded-lg border border-default px-3 py-2.5 transition hover:bg-elevated/60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                  >
                    <span class="text-sm font-medium text-highlighted">{{ item.label }}</span>
                    <UBadge :color="item.color" variant="subtle" :label="String(item.value)" />
                  </NuxtLink>
                </div>
              </ShellSectionCard>
            </aside>
          </div>

          <div class="grid min-w-0 gap-4 lg:grid-cols-2" aria-label="Exceções operacionais">
            <section data-testid="work-dashboard-risks">
              <ShellSectionCard
                title="Prioridades"
                description="Tarefas com os sinais de risco mais relevantes."
                icon="i-lucide-triangle-alert"
              >
                <ul v-if="data.top_risks.length" class="grid gap-2 sm:grid-cols-2">
                  <li v-for="risk in data.top_risks.slice(0, 6)" :key="risk.task_id">
                    <NuxtLink
                      :to="`/work/tasks/${risk.task_id}`"
                      class="group flex h-full min-w-0 items-start gap-3 rounded-lg border border-default p-3 transition hover:bg-elevated/60 hover:ring-accented"
                    >
                      <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-md bg-elevated">
                        <UIcon :name="workRiskIcon(primaryRisk(risk.risks) || '')" class="size-4 text-toned" aria-hidden="true" />
                      </span>
                      <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-medium text-highlighted group-hover:text-primary">{{ risk.title }}</span>
                        <span class="mt-1 flex flex-wrap items-center gap-1">
                          <UBadge
                            v-for="item in risk.risks"
                            :key="item"
                            :color="workRiskColor(item)"
                            variant="subtle"
                            size="xs"
                            :label="workRiskLabel(item)"
                          />
                        </span>
                        <span class="mt-1.5 block text-xs text-muted">Prazo {{ formatDueDate(risk.effective_due_date) }}</span>
                      </span>
                      <UIcon name="i-lucide-chevron-right" class="mt-1 size-4 shrink-0 text-dimmed" aria-hidden="true" />
                    </NuxtLink>
                  </li>
                </ul>

                <ShellListEmpty
                  v-else
                  title="Nenhum risco ativo"
                  description="Não há tarefas sinalizadas com risco neste snapshot."
                  test-id="work-dashboard-risks-empty"
                />

                <div class="mt-4 border-t border-default pt-4">
                  <UButton
                    to="/work/tasks?tab=atrasadas"
                    color="neutral"
                    variant="ghost"
                    size="sm"
                    label="Ver fila de atenção"
                    trailing-icon="i-lucide-arrow-right"
                    block
                  />
                </div>
              </ShellSectionCard>
            </section>

            <section data-testid="work-dashboard-unassigned-processes">
              <ShellSectionCard
                title="Processos sem responsável"
                description="Pendências de governança que precisam de atribuição."
                icon="i-lucide-user-round-search"
              >
                <ul v-if="data.processes_without_owner.length" class="divide-y divide-default">
                  <li v-for="process in data.processes_without_owner.slice(0, 5)" :key="process.id" class="py-3 first:pt-0 last:pb-0">
                    <NuxtLink :to="`/work/processes/${process.id}`" class="group flex min-w-0 items-start justify-between gap-3">
                      <span class="min-w-0">
                        <span class="block truncate text-sm font-medium text-highlighted group-hover:text-primary group-hover:underline">{{ process.title }}</span>
                        <span class="mt-1 block text-xs text-muted">{{ formatCompetence(process.competence) }} · {{ formatDueDate(process.due_date) }}</span>
                      </span>
                      <UIcon name="i-lucide-arrow-up-right" class="mt-0.5 size-4 shrink-0 text-dimmed" aria-hidden="true" />
                    </NuxtLink>
                  </li>
                </ul>

                <ShellListEmpty
                  v-else
                  title="Responsabilidade definida"
                  description="Não há processos abertos sem responsável."
                  test-id="work-dashboard-processes-empty"
                />

                <div class="mt-4 border-t border-default pt-4">
                  <UButton
                    to="/work/processes"
                    color="neutral"
                    variant="ghost"
                    size="sm"
                    label="Ver todos os processos"
                    trailing-icon="i-lucide-arrow-right"
                    block
                  />
                </div>
              </ShellSectionCard>
            </section>
          </div>
        </template>
      </template>
    </template>
  </ShellPagePanel>
</template>

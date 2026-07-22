<script setup lang="ts">
import type { LocationQueryRaw } from 'vue-router'
import type { WorkDepartment, WorkKpis, WorkRisk } from '~/types/work'
import { apiErrorMessage } from '~/utils/api-error'
import {
  buildWorkDashboardKpis,
  buildWorkDepartmentRows,
  workCompletionPercent,
  workQueueLegacyTarget
} from '~/utils/work-strategic-dashboard'
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

function overallProgressColor(): SemanticColor {
  if (!data.value) return 'primary'
  if (data.value.kpis.em_multa > 0) return 'error'
  if (data.value.kpis.atrasadas > 0) return 'warning'
  return 'success'
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
          <div class="grid grid-cols-2 gap-3 lg:grid-cols-6">
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
          <ShellKpiStrip
            :items="kpiCards"
            :loading="loading"
            :columns="6"
            legend="Pulso operacional"
            test-id="work-dashboard-kpis"
          />

          <div class="grid min-w-0 gap-4 lg:grid-cols-[minmax(0,1.45fr)_minmax(18rem,0.75fr)]">
            <div class="min-w-0 space-y-4">
              <section data-testid="work-dashboard-departments">
                <ShellSectionCard
                  title="Execução por departamento"
                  description="Carga aberta, avanço consolidado e sinais de risco por área."
                  icon="i-lucide-chart-no-axes-column-increasing"
                >
                  <div class="mb-5 rounded-lg bg-elevated/60 p-4 ring ring-default">
                    <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
                      <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-muted">
                          Conclusão consolidada
                        </p>
                        <p class="mt-1 text-3xl font-semibold tabular-nums text-highlighted">
                          {{ completionPercent }}%
                        </p>
                      </div>
                      <div class="text-right text-xs text-muted">
                        <p>{{ data.kpis.concluidas }} concluídas</p>
                        <p>{{ data.kpis.total_open }} ainda abertas</p>
                      </div>
                    </div>
                    <UProgress
                      :model-value="completionPercent"
                      :color="overallProgressColor()"
                      size="md"
                      :aria-label="`Conclusão consolidada: ${completionPercent}%`"
                    />
                  </div>

                  <ul
                    v-if="departmentRows.length"
                    class="divide-y divide-default"
                    aria-label="Progresso dos departamentos"
                  >
                    <li
                      v-for="row in departmentRows"
                      :key="String(row.id)"
                      class="py-4 first:pt-0 last:pb-0"
                    >
                      <div class="mb-2 flex min-w-0 items-start justify-between gap-3">
                        <div class="min-w-0">
                          <NuxtLink
                            :to="row.to"
                            class="font-medium text-highlighted hover:text-primary hover:underline"
                          >
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
                        :model-value="row.completedPercent"
                        :color="departmentProgressColor(row)"
                        size="sm"
                        :aria-label="`${row.name}: ${row.completedPercent}% concluído`"
                      />
                      <div class="mt-2 flex flex-wrap gap-1.5">
                        <UBadge
                          color="neutral"
                          variant="subtle"
                          size="sm"
                          :label="`${row.open} abertas`"
                        />
                        <UBadge
                          v-if="row.overdue > 0"
                          color="warning"
                          variant="subtle"
                          size="sm"
                          icon="i-lucide-clock-alert"
                          :label="`${row.overdue} atrasadas`"
                        />
                        <UBadge
                          v-if="row.fine > 0"
                          color="error"
                          variant="subtle"
                          size="sm"
                          icon="i-lucide-siren"
                          :label="`${row.fine} em multa`"
                        />
                        <UBadge
                          v-if="row.unassigned > 0"
                          color="info"
                          variant="subtle"
                          size="sm"
                          icon="i-lucide-user-x"
                          :label="`${row.unassigned} sem responsável`"
                        />
                        <UButton
                          v-if="row.overdue > 0"
                          :to="row.overdueTo"
                          color="neutral"
                          variant="link"
                          size="xs"
                          label="Ver atrasadas"
                          trailing-icon="i-lucide-arrow-right"
                          class="ms-auto"
                        />
                      </div>
                    </li>
                  </ul>

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
                </ShellSectionCard>
              </section>

              <ShellSectionCard
                title="Acessos rápidos"
                description="Transforme a leitura estratégica em ação."
                icon="i-lucide-route"
                test-id="work-dashboard-quick-links"
              >
                <div class="grid gap-2 sm:grid-cols-3">
                  <UButton
                    v-for="link in quickLinks"
                    :key="link.to"
                    :to="link.to"
                    color="neutral"
                    variant="outline"
                    class="h-auto min-w-0 justify-start px-3 py-3 text-left"
                  >
                    <UIcon :name="link.icon" class="size-5 shrink-0 text-primary" />
                    <span class="min-w-0">
                      <span class="block font-medium text-highlighted">{{ link.label }}</span>
                      <span class="block truncate text-xs font-normal text-muted">{{ link.description }}</span>
                    </span>
                  </UButton>
                </div>
              </ShellSectionCard>
            </div>

            <aside class="min-w-0 space-y-4" aria-label="Exceções operacionais">
              <section data-testid="work-dashboard-risks">
                <ShellSectionCard
                  title="Prioridades"
                  description="Tarefas com os sinais de risco mais relevantes."
                  icon="i-lucide-triangle-alert"
                >
                  <ul v-if="data.top_risks.length" class="space-y-2">
                    <li
                      v-for="risk in data.top_risks.slice(0, 6)"
                      :key="risk.task_id"
                    >
                      <NuxtLink
                        :to="`/work/tasks/${risk.task_id}`"
                        class="group flex min-w-0 items-start gap-3 rounded-lg border border-default p-3 transition hover:bg-elevated/60 hover:ring-accented"
                      >
                        <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-md bg-elevated">
                          <UIcon
                            :name="workRiskIcon(primaryRisk(risk.risks) || '')"
                            class="size-4 text-toned"
                            aria-hidden="true"
                          />
                        </span>
                        <span class="min-w-0 flex-1">
                          <span class="block truncate text-sm font-medium text-highlighted group-hover:text-primary">
                            {{ risk.title }}
                          </span>
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
                          <span class="mt-1.5 block text-xs text-muted">
                            Prazo {{ formatDueDate(risk.effective_due_date) }}
                          </span>
                        </span>
                        <UIcon
                          name="i-lucide-chevron-right"
                          class="mt-1 size-4 shrink-0 text-dimmed"
                          aria-hidden="true"
                        />
                      </NuxtLink>
                    </li>
                  </ul>

                  <ShellListEmpty
                    v-else
                    title="Nenhum risco ativo"
                    description="Não há tarefas sinalizadas com risco neste snapshot."
                    test-id="work-dashboard-risks-empty"
                  />

                  <template #footer>
                    <UButton
                      to="/work/tasks?tab=atrasadas"
                      color="neutral"
                      variant="ghost"
                      size="sm"
                      label="Ver fila de atenção"
                      trailing-icon="i-lucide-arrow-right"
                      block
                    />
                  </template>
                </ShellSectionCard>
              </section>

              <section data-testid="work-dashboard-unassigned-processes">
                <ShellSectionCard
                  title="Processos sem responsável"
                  description="Pendências de governança que precisam de atribuição."
                  icon="i-lucide-user-round-search"
                >
                  <ul v-if="data.processes_without_owner.length" class="divide-y divide-default">
                    <li
                      v-for="process in data.processes_without_owner.slice(0, 5)"
                      :key="process.id"
                      class="py-3 first:pt-0 last:pb-0"
                    >
                      <NuxtLink
                        :to="`/work/processes/${process.id}`"
                        class="group flex min-w-0 items-start justify-between gap-3"
                      >
                        <span class="min-w-0">
                          <span class="block truncate text-sm font-medium text-highlighted group-hover:text-primary group-hover:underline">
                            {{ process.title }}
                          </span>
                          <span class="mt-1 block text-xs text-muted">
                            {{ formatCompetence(process.competence) }} · {{ formatDueDate(process.due_date) }}
                          </span>
                        </span>
                        <UIcon
                          name="i-lucide-arrow-up-right"
                          class="mt-0.5 size-4 shrink-0 text-dimmed"
                          aria-hidden="true"
                        />
                      </NuxtLink>
                    </li>
                  </ul>

                  <ShellListEmpty
                    v-else
                    title="Responsabilidade definida"
                    description="Não há processos abertos sem responsável."
                    test-id="work-dashboard-processes-empty"
                  />

                  <template #footer>
                    <UButton
                      to="/work/processes"
                      color="neutral"
                      variant="ghost"
                      size="sm"
                      label="Ver todos os processos"
                      trailing-icon="i-lucide-arrow-right"
                      block
                    />
                  </template>
                </ShellSectionCard>
              </section>
            </aside>
          </div>
        </template>
      </template>
    </template>
  </ShellPagePanel>
</template>

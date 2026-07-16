<script setup lang="ts">
/**
 * Detalhe do processo — shell Settings com seções reproduzíveis na URL.
 */
import type { NavigationMenuItem } from '@nuxt/ui'
import type { OperationalProcess } from '~/types/work'
import { apiErrorMessage } from '~/utils/api-error'
import {
  formatCompetence,
  formatDueDate,
  highestRiskColor,
  processStatusColor,
  processStatusLabel,
  taskStatusColor,
  taskStatusLabel,
  workRiskLabel
} from '~/utils/work-labels'

const api = useApi()
const route = useRoute()
const toast = useToast()
const { sessionEpoch } = useDashboard()

const process = ref<OperationalProcess | null>(null)
const timeline = ref<Array<Record<string, unknown>>>([])
const loading = ref(true)
const loadError = ref<string | null>(null)
const notFound = ref(false)

const id = computed(() => Number(route.params.id))
const section = computed(() => {
  const s = String(route.query.section || 'resumo')
  return ['resumo', 'tarefas', 'comentarios', 'historico'].includes(s) ? s : 'resumo'
})

const links = computed(() => [[
  { label: 'Resumo', icon: 'i-lucide-layout-dashboard', to: `/work/processes/${id.value}?section=resumo`, active: section.value === 'resumo' },
  { label: 'Tarefas', icon: 'i-lucide-list-checks', to: `/work/processes/${id.value}?section=tarefas`, active: section.value === 'tarefas' },
  { label: 'Comentários', icon: 'i-lucide-message-square', to: `/work/processes/${id.value}?section=comentarios`, active: section.value === 'comentarios' },
  { label: 'Histórico', icon: 'i-lucide-history', to: `/work/processes/${id.value}?section=historico`, active: section.value === 'historico' }
] satisfies NavigationMenuItem[]])

async function load() {
  const epoch = sessionEpoch.value
  loading.value = true
  loadError.value = null
  notFound.value = false
  try {
    const res = await api.work.processes.get(id.value)
    if (epoch !== sessionEpoch.value) return
    process.value = res.data
    try {
      const tl = await api.work.processes.timeline(id.value)
      if (epoch === sessionEpoch.value) {
        timeline.value = tl.data || []
      }
    } catch {
      timeline.value = []
    }
  } catch (e: unknown) {
    if (epoch !== sessionEpoch.value) return
    const status = (e as { statusCode?: number, status?: number })?.statusCode
      ?? (e as { status?: number })?.status
    if (status === 404) {
      notFound.value = true
      loadError.value = 'Processo não encontrado neste escritório.'
    } else if (status === 403) {
      loadError.value = 'Sem permissão para ver este processo.'
    } else {
      loadError.value = apiErrorMessage(e, 'Processo indisponível.')
    }
    process.value = null
    toast.add({ title: loadError.value, color: 'error' })
  } finally {
    if (epoch === sessionEpoch.value) loading.value = false
  }
}

onMounted(load)
watch([id, sessionEpoch], load)
</script>

<template>
  <DashboardListShell
    panel-id="work-process-detail"
    :title="process?.title || 'Processo'"
    panel-test-id="work-process-detail"
    :panel-ui="{ body: 'lg:py-8' }"
  >
    <template #navbar-right>
      <UButton
            to="/work/processes"
            variant="ghost"
            icon="i-lucide-arrow-left"
            label="Voltar"
          />
    </template>
    <template #toolbar>
      <UDashboardToolbar v-if="process">
        <UNavigationMenu :items="links" highlight class="-mx-1 flex-1" />
      </UDashboardToolbar>
    </template>
      <div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
        <div v-if="loading" class="space-y-3">
          <USkeleton class="h-8 w-1/2" />
          <USkeleton class="h-32 w-full" />
        </div>

        <UAlert
          v-else-if="loadError"
          data-testid="work-process-error"
          :color="notFound ? 'neutral' : 'error'"
          :title="loadError"
        />

        <template v-else-if="process">
          <!-- Resumo -->
          <section v-if="section === 'resumo'" class="space-y-4" data-testid="process-section-resumo">
            <div class="grid gap-3 sm:grid-cols-2">
              <UCard>
                <p class="text-xs text-muted">
                  Cliente
                </p>
                <p class="font-medium">
                  {{ process.client?.name || '—' }}
                </p>
              </UCard>
              <UCard>
                <p class="text-xs text-muted">
                  Competência
                </p>
                <p class="font-medium">
                  {{ formatCompetence(process.competence) }}
                </p>
              </UCard>
              <UCard>
                <p class="text-xs text-muted">
                  Status
                </p>
                <UBadge
                  class="mt-1"
                  variant="subtle"
                  :color="processStatusColor(process.status)"
                  :label="processStatusLabel(process.status)"
                />
              </UCard>
              <UCard>
                <p class="text-xs text-muted">
                  Prazo
                </p>
                <p class="font-medium">
                  {{ formatDueDate(process.due_date) }}
                </p>
              </UCard>
              <UCard>
                <p class="text-xs text-muted">
                  Responsável
                </p>
                <p class="font-medium">
                  {{ process.assignee?.name || 'Sem responsável' }}
                </p>
              </UCard>
              <UCard>
                <p class="text-xs text-muted">
                  Departamento
                </p>
                <p class="font-medium">
                  {{ process.department?.name || '—' }}
                </p>
              </UCard>
            </div>

            <UCard>
              <p class="mb-2 text-sm font-medium">
                Progresso das tarefas
              </p>
              <UProgress
                :model-value="process.progress_percent ?? 0"
                size="md"
                :aria-label="`${process.progress_percent ?? 0}% concluído`"
              />
              <p class="mt-2 text-xs text-muted">
                {{ process.completed_task_count ?? 0 }} de {{ process.task_count ?? 0 }} tarefas encerradas
              </p>
              <div v-if="process.risks?.length" class="mt-3 flex flex-wrap gap-1">
                <UBadge
                  v-for="r in process.risks"
                  :key="r"
                  size="sm"
                  variant="subtle"
                  :color="highestRiskColor([r])"
                  :label="workRiskLabel(r)"
                />
              </div>
            </UCard>

            <p v-if="process.description" class="text-sm text-toned whitespace-pre-wrap">
              {{ process.description }}
            </p>
          </section>

          <!-- Tarefas -->
          <section v-else-if="section === 'tarefas'" data-testid="process-section-tarefas">
            <ul class="divide-y divide-default rounded-md border border-default">
              <li
                v-for="task in process.tasks || []"
                :key="task.id"
                class="flex flex-col gap-2 p-3 sm:flex-row sm:items-center sm:justify-between"
              >
                <div class="min-w-0">
                  <p class="font-medium">
                    {{ task.sort_order }}. {{ task.title }}
                  </p>
                  <div class="mt-1 flex flex-wrap gap-2 text-xs text-muted">
                    <UBadge
                      size="sm"
                      variant="subtle"
                      :color="taskStatusColor(task.status)"
                      :label="taskStatusLabel(task.status)"
                    />
                    <span v-if="task.due_date">{{ formatDueDate(task.due_date) }}</span>
                    <span v-if="task.is_critical">Crítica</span>
                    <span v-if="task.requires_evidence">Exige evidência</span>
                    <span v-if="task.assignee">{{ task.assignee.name }}</span>
                  </div>
                </div>
                <UButton
                  size="sm"
                  variant="soft"
                  :to="`/work?task=${task.id}`"
                  label="Abrir na fila"
                />
              </li>
              <li v-if="!process.tasks?.length" class="p-4 text-sm text-muted">
                <UEmpty icon="i-lucide-list-todo" title="Nenhuma tarefa neste processo" size="sm" />
              </li>
            </ul>
          </section>

          <!-- Comentários -->
          <section v-else-if="section === 'comentarios'" data-testid="process-section-comentarios">
            <ul class="space-y-2">
              <li
                v-for="c in process.comments || []"
                :key="c.id"
                class="rounded-md border border-default p-3 text-sm"
              >
                <p class="whitespace-pre-wrap">
                  {{ c.body }}
                </p>
                <p class="mt-1 text-xs text-muted">
                  {{ c.created_at }}
                </p>
              </li>
              <li v-if="!process.comments?.length" class="text-sm text-muted">
                <UEmpty icon="i-lucide-message-square" title="Nenhum comentário ainda" size="sm" />
              </li>
            </ul>
          </section>

          <!-- Histórico -->
          <section v-else data-testid="process-section-historico">
            <ul class="space-y-2">
              <li
                v-for="(ev, idx) in timeline"
                :key="idx"
                class="rounded-md border border-default p-3 text-sm"
              >
                <p class="font-medium">
                  {{ ev.kind }} · {{ ev.action || ev.body || ev.original_filename }}
                </p>
                <p class="text-xs text-muted">
                  {{ ev.created_at }}
                </p>
              </li>
              <li v-if="!timeline.length" class="text-sm text-muted">
                Sem eventos de histórico allowlisted.
              </li>
            </ul>
          </section>
        </template>
      </div>
  </DashboardListShell>
</template>

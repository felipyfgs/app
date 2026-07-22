<script setup lang="ts">
/** Lista operacional de processos via ShellDataTable, com paginação server-side. */
import { h } from 'vue'
import type { TableColumn } from '@nuxt/ui'
import UCheckbox from '@nuxt/ui/components/Checkbox.vue'
import type { OperationalProcess, OperationalProcessTask, WorkDepartment } from '~/types/work'
import { canAdministerWork, canCreateWorkProcesses, canExecuteWorkTasks, canManageWorkCatalog } from '~/utils/permissions'
import { apiErrorMessage } from '~/utils/api-error'
import type { DataTableFilterDefinition, DataTableFilterModel } from '~/types/data-table-filter'
import type { SavedListFilterPayload } from '~/types/saved-list-filters'
import {
  createFilterModel,
  findDefinition
} from '~/utils/data-table-filters'
import {
  hasActiveWorkProcessesFiltersForSave,
  workProcessesFiltersToPayload,
  workProcessesPayloadToFilters
} from '~/utils/saved-list-filters'
import ShellListFilterToolbar from '~/components/shell/ListFilterToolbar.vue'
import ShellDataTable from '~/components/shell/DataTable.vue'
import WorkBulkActionsModal from '~/components/work/WorkBulkActionsModal.vue'
import type { WorkBulkItem } from '~/components/work/WorkBulkActionsModal.vue'
import WorkTaskStatusSelect from '~/components/work/WorkTaskStatusSelect.vue'
import { sortHeader } from '~/utils/table-sort'
import {
  formatCompetence,
  formatDueDate,
  processStatusColor,
  processStatusLabel
} from '~/utils/work-labels'
import {
  TABLE_CELL_BADGE_CLASS,
  TABLE_CELL_BADGE_UI
} from '~/utils/table-ui'
import { nextExpandedProcessId } from '~/utils/work-orchestration'
import {
  cascadeProcessTaskSelection,
  cascadeSelectAllProcessesOnPage,
  sortedProcessTasks
} from '~/utils/work-process-selection'
import { COMPACT_BUTTON_LABEL_UI } from '~/utils/list-filter-layout'

const api = useApi()
const router = useRouter()
const route = useRoute()
const toast = useToast()
const { me, sessionEpoch } = useDashboard()

const items = ref<OperationalProcess[]>([])
const loading = ref(false)
const loadError = ref<string | null>(null)
const page = ref(Math.max(1, Number(route.query.page) || 1))
const perPage = ref(20)
const total = ref(0)
const q = ref(String(route.query.q || ''))
const competence = ref(String(route.query.competence || ''))
/** 'all' = sem filtro. */
const status = ref(String(route.query.status || 'all'))
const clientId = ref<number | null>(positiveId(route.query.client_id))
const departmentId = ref<number | null>(positiveId(route.query.department_id))
const departments = ref<WorkDepartment[]>([])
const sort = ref<string | null>(String(route.query.sort || '') || null)
const direction = ref<'asc' | 'desc' | null>(
  String(route.query.direction || '') === 'asc' || String(route.query.direction || '') === 'desc'
    ? String(route.query.direction) as 'asc' | 'desc'
    : null
)

const rowSelection = ref<Record<string, boolean>>({})
const selectedTaskIds = ref<Record<string, boolean>>({})
const bulkOpen = ref(false)

const canExecute = computed(() => canExecuteWorkTasks(me.value))
const canAdmin = computed(() => canAdministerWork(me.value))
const canUpdateProcesses = computed(() => canCreateWorkProcesses(me.value))

function positiveId(value: unknown): number | null {
  const raw = Array.isArray(value) ? value[0] : value
  const parsed = Number(raw)
  return Number.isInteger(parsed) && parsed > 0 ? parsed : null
}

const processFilterDefinitions = computed<DataTableFilterDefinition[]>(() => [
  {
    key: 'competence',
    kind: 'month',
    label: 'Competência',
    emptyValue: ''
  },
  {
    key: 'status',
    kind: 'option',
    label: 'Status',
    emptyValue: 'all',
    items: [
      { label: 'A fazer', value: 'A_FAZER' },
      { label: 'Em progresso', value: 'EM_PROGRESSO' },
      { label: 'Impedido', value: 'IMPEDIDO' },
      { label: 'Concluído', value: 'CONCLUIDO' },
      { label: 'Arquivado', value: 'ARQUIVADO' }
    ]
  },
  {
    key: 'client_id',
    kind: 'client',
    label: 'Cliente',
    emptyValue: null
  },
  {
    key: 'department_id',
    kind: 'option',
    label: 'Departamento',
    emptyValue: '',
    items: departments.value.map(department => ({
      label: department.name,
      value: String(department.id)
    }))
  }
])

function modelsFromProcessState(): DataTableFilterModel[] {
  const models: DataTableFilterModel[] = []
  const definitions = processFilterDefinitions.value
  const competenceDef = findDefinition(definitions, 'competence')
  const statusDef = findDefinition(definitions, 'status')
  const clientDef = findDefinition(definitions, 'client_id')
  const departmentDef = findDefinition(definitions, 'department_id')
  if (competenceDef) {
    const model = createFilterModel(competenceDef, competence.value)
    if (model) models.push(model)
  }
  if (statusDef) {
    const model = createFilterModel(statusDef, status.value)
    if (model) models.push(model)
  }
  if (clientDef && clientId.value) {
    const model = createFilterModel(clientDef, clientId.value)
    if (model) models.push(model)
  }
  if (departmentDef && departmentId.value) {
    const model = createFilterModel(departmentDef, String(departmentId.value))
    if (model) models.push(model)
  }
  return models
}

const chipModels = computed(() => modelsFromProcessState())

function onStructuredFilters(models: DataTableFilterModel[]) {
  const competenceModel = models.find(m => m.key === 'competence')
  const statusModel = models.find(m => m.key === 'status')
  const clientModel = models.find(m => m.key === 'client_id')
  const departmentModel = models.find(m => m.key === 'department_id')
  competence.value = competenceModel ? String(competenceModel.value) : ''
  status.value = statusModel ? String(statusModel.value) : 'all'
  clientId.value = clientModel ? positiveId(clientModel.value) : null
  departmentId.value = departmentModel ? positiveId(departmentModel.value) : null
  page.value = 1
}

function onClearStructuredFilters() {
  competence.value = ''
  status.value = 'all'
  clientId.value = null
  departmentId.value = null
  page.value = 1
}

function onProcessSearch(value: string) {
  q.value = value
  page.value = 1
}

function onProcessPreset(payload: SavedListFilterPayload) {
  const next = workProcessesPayloadToFilters(payload)
  q.value = next.q
  competence.value = next.competence
  status.value = next.status
  clientId.value = next.client_id
  departmentId.value = next.department_id
  page.value = 1
}

// CTA leva a /work/templates (catálogo/lote) — só ADMIN com 2FA.
const canOpenTemplates = computed(() => canManageWorkCatalog(me.value))

async function load() {
  const epoch = sessionEpoch.value
  loading.value = true
  loadError.value = null
  try {
    const res = await api.work.processes.list({
      page: page.value,
      per_page: perPage.value,
      q: q.value || undefined,
      competence: competence.value || undefined,
      status: status.value && status.value !== 'all' ? status.value : undefined,
      client_id: clientId.value || undefined,
      department_id: departmentId.value || undefined,
      sort: sort.value || undefined,
      direction: sort.value ? (direction.value || 'asc') : undefined
    })
    if (epoch !== sessionEpoch.value) return
    items.value = res.data
    total.value = res.meta.total
    rowSelection.value = {}
    selectedTaskIds.value = {}
  } catch (e) {
    if (epoch !== sessionEpoch.value) return
    loadError.value = apiErrorMessage(e, 'Falha ao listar processos.')
    toast.add({ title: loadError.value, color: 'error' })
  } finally {
    if (epoch === sessionEpoch.value) loading.value = false
  }
}

async function loadDepartments() {
  try {
    const response = await api.work.departments.list({ per_page: 100, is_active: true })
    departments.value = response.data
  } catch {
    departments.value = []
  }
}

function setPerPage(next: number) {
  const allowed = [10, 20, 50]
  const target = allowed.includes(Number(next)) ? Number(next) : 20
  if (perPage.value === target) return
  perPage.value = target
  if (page.value !== 1) {
    page.value = 1
    return
  }
  void load()
}

const pageSelectionState = computed<'none' | 'some' | 'all'>(() => {
  if (!items.value.length) return 'none'
  const selected = items.value.filter(process => rowSelection.value[String(process.id)] === true).length
  if (selected === 0) return 'none'
  if (selected === items.value.length) return 'all'
  return 'some'
})

const processColumns = computed<TableColumn<OperationalProcess>[]>(() => {
  // Dependências explícitas para o h() dos checkboxes re-renderizar na cascata.
  const selectionSnapshot = rowSelection.value
  const headerState = pageSelectionState.value

  const selectColumn: TableColumn<OperationalProcess> = {
    id: 'select',
    enableHiding: false,
    enableSorting: false,
    meta: { class: { th: 'w-10 min-w-10', td: 'w-10 min-w-10' } },
    header: () => h(UCheckbox, {
      'modelValue': headerState === 'some'
        ? 'indeterminate'
        : headerState === 'all',
      'onUpdate:modelValue': (value: unknown) => {
        setAllPageSelected(!!value)
      },
      'ariaLabel': 'Selecionar todos os processos e tarefas desta página'
    }),
    cell: ({ row }) => {
      const processId = String(row.original.id)
      return h(UCheckbox, {
        'modelValue': selectionSnapshot[processId] === true,
        'onUpdate:modelValue': (value: unknown) => {
          setProcessSelected(row.original, !!value)
        },
        'ariaLabel': `Selecionar ${row.original.title} e suas tarefas`
      })
    }
  }

  const columns: TableColumn<OperationalProcess>[] = [
    {
      id: 'expand',
      header: '',
      enableSorting: false,
      meta: { class: { th: 'w-10 min-w-10', td: 'w-10 min-w-10' } }
    },
    {
      accessorKey: 'title',
      header: ({ column }) => sortHeader('Processo', column),
      meta: { class: { th: 'w-full max-w-0 min-w-48', td: 'w-full max-w-0 min-w-48' } }
    },
    {
      accessorKey: 'client',
      header: 'Cliente',
      enableSorting: false,
      meta: { class: { th: 'w-48 min-w-40', td: 'w-48 min-w-40' } }
    },
    {
      accessorKey: 'status',
      header: ({ column }) => sortHeader('Status', column),
      meta: { class: { th: 'w-32 min-w-28', td: 'w-32 min-w-28' } }
    },
    {
      accessorKey: 'progress',
      header: 'Progresso',
      enableSorting: false,
      meta: { class: { th: 'w-40 min-w-36', td: 'w-40 min-w-36' } }
    },
    {
      accessorKey: 'due_date',
      header: ({ column }) => sortHeader('Prazo', column),
      meta: { class: { th: 'w-28 min-w-24', td: 'w-28 min-w-24' } }
    },
    {
      accessorKey: 'assignee',
      header: 'Responsável',
      enableSorting: false,
      meta: { class: { th: 'w-36 min-w-28', td: 'w-36 min-w-28' } }
    },
    {
      accessorKey: 'actions',
      header: '',
      enableSorting: false,
      meta: { class: { th: 'w-24 min-w-20', td: 'w-24 min-w-20' } }
    }
  ]

  return canExecute.value || canAdmin.value ? [selectColumn, ...columns] : columns
})

const sortingState = computed(() => {
  if (!sort.value) return []
  return [{ id: sort.value, desc: direction.value === 'desc' }]
})

function onSortingUpdate(next: Array<{ id: string, desc: boolean }>) {
  const first = next[0]
  if (!first) {
    sort.value = null
    direction.value = null
    return
  }
  const allowed = new Set(['title', 'status', 'due_date', 'competence'])
  if (!allowed.has(first.id)) return
  sort.value = first.id
  direction.value = first.desc ? 'desc' : 'asc'
  page.value = 1
}

/** Uma linha expandida por vez (padrão UTable Expanding). */
const expanded = ref<Record<string, boolean>>({})

const selectedProcesses = computed(() =>
  items.value.filter(process => rowSelection.value[String(process.id)] === true)
)

const selectedProcessBulkItems = computed<WorkBulkItem[]>(() =>
  selectedProcesses.value.map(process => ({
    id: process.id,
    lock_version: process.lock_version,
    label: process.title
  }))
)

const selectedTaskBulkItems = computed<WorkBulkItem[]>(() => {
  const tasks: WorkBulkItem[] = []
  for (const process of items.value) {
    for (const task of processTasks(process)) {
      if (selectedTaskIds.value[String(task.id)]) {
        tasks.push({
          id: task.id,
          lock_version: task.lock_version,
          label: `${task.title} · ${process.title}`
        })
      }
    }
  }
  return tasks
})

const selectedCount = computed(
  () => selectedProcessBulkItems.value.length + selectedTaskBulkItems.value.length
)

const canBulk = computed(() => canExecute.value || canAdmin.value)

function openBulkActions() {
  if (!selectedCount.value || !canBulk.value) return
  bulkOpen.value = true
}

function clearSelection() {
  rowSelection.value = {}
  selectedTaskIds.value = {}
}

function setProcessSelected(process: OperationalProcess, selected: boolean) {
  const next = cascadeProcessTaskSelection({
    processes: items.value,
    processSelection: rowSelection.value,
    taskSelection: selectedTaskIds.value,
    changedProcessIds: [process.id],
    selected
  })
  rowSelection.value = next.processSelection
  selectedTaskIds.value = next.taskSelection
}

function setAllPageSelected(selected: boolean) {
  const next = cascadeSelectAllProcessesOnPage({
    processes: items.value,
    selected
  })
  rowSelection.value = next.processSelection
  selectedTaskIds.value = next.taskSelection
}

function toggleTaskSelected(taskId: number, value: boolean | 'indeterminate') {
  const key = String(taskId)
  const nextTasks = { ...selectedTaskIds.value }
  if (value) {
    nextTasks[key] = true
  } else {
    Reflect.deleteProperty(nextTasks, key)
  }
  selectedTaskIds.value = nextTasks

  const parent = items.value.find(process =>
    sortedProcessTasks(process).some(task => task.id === taskId)
  )
  if (!parent) return

  const parentKey = String(parent.id)
  const allSelected = sortedProcessTasks(parent).every(task => nextTasks[String(task.id)])
  const nextRows = { ...rowSelection.value }
  if (allSelected) {
    nextRows[parentKey] = true
  } else {
    Reflect.deleteProperty(nextRows, parentKey)
  }
  rowSelection.value = nextRows
}

function openProcess(process: OperationalProcess) {
  void navigateTo(`/work/processes/${process.id}`)
}

function processTasks(process: OperationalProcess): OperationalProcessTask[] {
  return sortedProcessTasks(process)
}

function toggleProcessExpanded(row: { id: string }) {
  const current = Object.keys(expanded.value).find(key => expanded.value[key]) ?? null
  const selected = Number(row.id)
  const nextId = nextExpandedProcessId(
    current && Number.isFinite(Number(current)) ? Number(current) : null,
    selected
  )
  expanded.value = nextId ? { [String(nextId)]: true } : {}
}

watch([page, q, competence, status, clientId, departmentId, sort, direction], () => {
  router.replace({
    query: {
      page: page.value > 1 ? String(page.value) : undefined,
      q: q.value || undefined,
      competence: competence.value || undefined,
      status: status.value && status.value !== 'all' ? status.value : undefined,
      client_id: clientId.value ? String(clientId.value) : undefined,
      department_id: departmentId.value ? String(departmentId.value) : undefined,
      sort: sort.value || undefined,
      direction: sort.value ? (direction.value || 'asc') : undefined
    }
  })
  load()
})

watch(sessionEpoch, () => {
  items.value = []
  page.value = 1
  q.value = ''
  competence.value = ''
  status.value = 'all'
  clientId.value = null
  departmentId.value = null
  sort.value = null
  direction.value = null
  total.value = 0
  clearSelection()
  void load()
})

onMounted(() => {
  void loadDepartments()
  void load()
})
</script>

<template>
  <ShellPagePanel
    id="work-processes"
    data-testid="work-processes-panel"
  >
    <template #header>
      <ShellPageNavbar title="Processos">
        <template #right>
          <UButton
            v-if="canOpenTemplates"
            icon="i-lucide-plus"
            label="Modelos / lote"
            to="/work/templates"
          />
        </template>
      </ShellPageNavbar>

      <UDashboardToolbar data-testid="work-processes-toolbar">
        <ShellListFilterToolbar
          :q="q"
          search-placeholder="Buscar…"
          search-aria-label="Buscar processos"
          :definitions="processFilterDefinitions"
          :models="chipModels"
          :loading="loading"
          :reset-key="sessionEpoch"
          surface="work.processes"
          :get-payload="() => workProcessesFiltersToPayload({
            q: q,
            competence,
            status,
            client_id: clientId,
            department_id: departmentId
          })"
          :can-save="() => hasActiveWorkProcessesFiltersForSave({
            q: q,
            competence,
            status,
            client_id: clientId,
            department_id: departmentId
          })"
          test-id-prefix="work-processes"
          @update:q="onProcessSearch"
          @update:models="onStructuredFilters"
          @clear="onClearStructuredFilters"
          @refresh="load"
          @apply-preset="onProcessPreset"
        >
          <template #actions>
            <div
              v-if="canBulk && selectedCount > 0"
              data-testid="work-processes-bulk-actions"
            >
              <UButton
                color="neutral"
                variant="subtle"
                icon="i-lucide-list-checks"
                label="Ações"
                aria-label="Ações em massa"
                :ui="COMPACT_BUTTON_LABEL_UI"
                data-testid="work-processes-bulk-actions-menu"
                @click="openBulkActions"
              >
                <template #trailing>
                  <UKbd>{{ selectedCount }}</UKbd>
                </template>
              </UButton>
            </div>
          </template>
          <template #client="{ modelValue, update, select: selectClient }">
            <FiscalClientPicker
              :model-value="modelValue"
              search-mode="select"
              placeholder="Cliente"
              class="w-full min-w-0"
              @update:model-value="(value) => update?.(value as number | null)"
              @select="(client) => selectClient?.(client)"
            />
          </template>
        </ShellListFilterToolbar>
      </UDashboardToolbar>
    </template>

    <template #body>
      <h1 data-testid="page-title" class="sr-only">
        Processos
      </h1>

      <ShellDataTable
        v-model:expanded="expanded"
        v-model:row-selection="rowSelection"
        :sorting="sortingState"
        :get-row-id="(row) => String(row.id)"
        test-id="work-processes-table"
        ui-preset="monitoring-compact"
        primary-column-id="title"
        status-column-id="status"
        :summary-column-ids="['client', 'progress', 'due_date', 'assignee']"
        :columns="processColumns"
        :data="items"
        :loading="loading"
        :error="loadError"
        :page="page"
        :total="total"
        :items-per-page="perPage"
        :selected-count="selectedCount"
        :selection-enabled="canExecute || canAdmin"
        :manual-sorting="true"
        empty-title="Nenhum processo encontrado"
        empty-description="Ajuste os filtros ou gere processos a partir de um modelo."
        per-page-aria-label="Processos por página"
        footer-test-id="work-processes-footer"
        @update:page="page = $event"
        @update:items-per-page="setPerPage"
        @update:sorting="onSortingUpdate"
        @retry="load"
      >
        <template #expand-cell="{ row }">
          <UButton
            color="neutral"
            variant="ghost"
            icon="i-lucide-chevron-down"
            square
            size="xs"
            :aria-label="row.getIsExpanded() ? 'Recolher tarefas' : 'Expandir tarefas'"
            :aria-expanded="row.getIsExpanded()"
            :ui="{
              leadingIcon: [
                'transition-transform duration-200',
                row.getIsExpanded() ? 'rotate-180' : ''
              ].join(' ')
            }"
            data-testid="work-process-expand"
            @click.stop="toggleProcessExpanded(row)"
          />
        </template>
        <template #title-cell="{ row }">
          <div class="min-w-0">
            <p class="truncate font-medium text-highlighted">
              {{ row.original.title }}
            </p>
            <p class="truncate text-xs text-muted">
              {{ formatCompetence(row.original.competence) }}
              <span v-if="row.original.department?.name">
                · {{ row.original.department.name }}
              </span>
            </p>
          </div>
        </template>
        <template #client-cell="{ row }">
          <div class="min-w-0">
            <NuxtLink
              :to="row.original.links?.client || `/clients/${row.original.client_id}/cadastro`"
              class="block truncate text-sm font-medium text-highlighted hover:text-primary"
              @click.stop
            >
              {{ row.original.client?.name || `Cliente #${row.original.client_id}` }}
            </NuxtLink>
            <p
              v-if="row.original.client?.cnpj_masked"
              class="truncate text-xs text-muted"
            >
              {{ row.original.client.cnpj_masked }}
            </p>
          </div>
        </template>
        <template #status-cell="{ row }">
          <UBadge
            size="md"
            variant="subtle"
            :color="processStatusColor(row.original.status)"
            :label="processStatusLabel(row.original.status)"
            :class="TABLE_CELL_BADGE_CLASS"
            :ui="TABLE_CELL_BADGE_UI"
          />
        </template>
        <template #progress-cell="{ row }">
          <div class="flex min-w-0 items-center gap-2">
            <UProgress
              class="min-w-16 flex-1"
              size="sm"
              :model-value="row.original.progress_percent ?? 0"
              :aria-label="`Progresso ${row.original.progress_percent ?? 0}%`"
            />
            <span class="shrink-0 text-xs tabular-nums text-muted">
              {{ row.original.completed_task_count ?? 0 }}/{{ row.original.task_count ?? row.original.tasks?.length ?? 0 }}
            </span>
          </div>
        </template>
        <template #due_date-cell="{ row }">
          <span class="inline-flex items-center gap-1.5 text-sm tabular-nums whitespace-nowrap">
            <UIcon name="i-lucide-calendar-clock" class="size-4 shrink-0 text-muted" />
            {{ formatDueDate(row.original.due_date) }}
          </span>
        </template>
        <template #assignee-cell="{ row }">
          <span class="block truncate text-sm">
            {{ row.original.assignee?.name || 'Sem responsável' }}
          </span>
        </template>
        <template #actions-cell="{ row }">
          <div class="flex justify-end gap-1">
            <UButton
              :to="row.original.links?.monitoring || `/monitoring/clients/${row.original.client_id}`"
              icon="i-lucide-activity"
              color="neutral"
              variant="ghost"
              size="xs"
              aria-label="Abrir monitoramento"
              @click.stop
            />
            <UButton
              icon="i-lucide-arrow-up-right"
              color="neutral"
              variant="ghost"
              size="xs"
              aria-label="Abrir detalhes do processo"
              @click.stop="openProcess(row.original)"
            />
          </div>
        </template>
        <template #expanded="{ row }">
          <div
            class="space-y-2 bg-elevated/30 px-4 py-3"
            :data-testid="`work-process-tasks-${row.original.id}`"
          >
            <div class="flex flex-wrap items-center justify-between gap-2">
              <p class="text-sm font-semibold text-highlighted">
                Tarefas do processo
              </p>
              <UButton
                v-if="row.original.monitoring_context"
                :to="row.original.monitoring_context.to"
                icon="i-lucide-chart-no-axes-combined"
                color="neutral"
                variant="soft"
                size="xs"
                :label="row.original.monitoring_context.label"
              />
            </div>
            <ol
              v-if="processTasks(row.original).length"
              class="divide-y divide-default rounded-md border border-default bg-default"
            >
              <li
                v-for="task in processTasks(row.original)"
                :key="task.id"
                class="flex min-w-0 flex-wrap items-center justify-between gap-3 px-3 py-2.5 sm:flex-nowrap"
              >
                <div class="flex min-w-0 items-start gap-3">
                  <UCheckbox
                    v-if="canExecute || canAdmin"
                    :model-value="!!selectedTaskIds[String(task.id)]"
                    :aria-label="`Selecionar tarefa ${task.title}`"
                    data-testid="work-process-task-select"
                    @update:model-value="toggleTaskSelected(task.id, $event)"
                  />
                  <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-elevated text-xs font-medium tabular-nums text-muted">
                    {{ task.sort_order }}
                  </span>
                  <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-highlighted">
                      {{ task.title }}
                    </p>
                    <p class="mt-0.5 text-xs text-muted">
                      {{ formatDueDate(task.effective_due_date || task.due_date) }}
                      <span v-if="task.assignee?.name"> · {{ task.assignee.name }}</span>
                    </p>
                  </div>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                  <WorkTaskStatusSelect
                    :task-id="task.id"
                    :status="task.status"
                    :lock-version="task.lock_version"
                    :can-claim="!task.assignee?.membership_id && !task.assignee_membership_id"
                    :disabled="!(canExecute || canAdmin)"
                    @updated="load"
                  />
                  <UButton
                    :to="`/work/tasks/${task.id}`"
                    size="xs"
                    color="neutral"
                    variant="ghost"
                    icon="i-lucide-arrow-up-right"
                    label="Abrir"
                    :ui="{ label: 'hidden sm:inline' }"
                  />
                </div>
              </li>
            </ol>
            <UEmpty
              v-else
              icon="i-lucide-inbox"
              title="Sem tarefas neste processo"
              class="py-4"
            />
          </div>
        </template>
      </ShellDataTable>

      <WorkBulkActionsModal
        v-model:open="bulkOpen"
        :processes="selectedProcessBulkItems"
        :tasks="selectedTaskBulkItems"
        :can-administer="canAdmin"
        :can-update-processes="canUpdateProcesses"
        @done="() => { clearSelection(); void load() }"
      />
    </template>
  </ShellPagePanel>
</template>

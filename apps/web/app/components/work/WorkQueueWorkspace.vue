<script setup lang="ts">
/**
 * Workspace da fila de trabalho — Fila (mestre–detalhe) ou Lista (tabular).
 *
 * URL canônica:
 * - `/work/tasks` — sem seleção
 * - `/work/tasks/{id}` — tarefa no path
 * - query: filtros + `view=lista` opcional — nunca `task` / `office_id`
 *
 * Compat: `/work/tasks?task=N` → `/work/tasks/N` (preserva demais query).
 */
import { breakpointsTailwind } from '@vueuse/core'
import { h } from 'vue'
import type { TableColumn } from '@nuxt/ui'
import UCheckbox from '@nuxt/ui/components/Checkbox.vue'
import type { OperationalTaskDetail, OperationalTaskSummary, WorkDepartment } from '~/types/work'
import type { DataTableFilterDefinition, DataTableFilterModel } from '~/types/data-table-filter'
import type { SavedListFilterPayload } from '~/types/saved-list-filters'
import { apiErrorMessage } from '~/utils/api-error'
import {
  parseWorkQueueQuery,
  serializeWorkQueueQuery,
  useWorkQueueFilters,
  workQueuePath,
  workTaskPath,
  type WorkQueueView
} from '~/composables/useWorkQueueFilters'
import {
  hasActiveWorkQueueFiltersForSave,
  workQueueFiltersToPayload,
  workQueuePayloadToFilters
} from '~/utils/saved-list-filters'
import { createFilterModel, findDefinition } from '~/utils/data-table-filters'
import { formatDueDate } from '~/utils/work-labels'
import { sortHeader } from '~/utils/table-sort'
import { canAdministerWork, canExecuteWorkTasks } from '~/utils/permissions'
import ShellListFilterToolbar from '~/components/shell/ListFilterToolbar.vue'
import ShellScrollableTabs from '~/components/shell/ScrollableTabs.vue'
import ShellDataTable from '~/components/shell/DataTable.vue'
import WorkBulkActionsModal from '~/components/work/WorkBulkActionsModal.vue'
import type { WorkBulkItem } from '~/components/work/WorkBulkActionsModal.vue'
import WorkTaskStatusSelect from '~/components/work/WorkTaskStatusSelect.vue'
import { COMPACT_BUTTON_LABEL_UI } from '~/utils/list-filter-layout'

const route = useRoute()
const router = useRouter()
const api = useApi()
const toast = useToast()
const { me, sessionEpoch } = useDashboard()
const {
  filters,
  selectedTaskId,
  patch,
  selectTask,
  clearTask,
  apiParams
} = useWorkQueueFilters()

const canExecute = computed(() => canExecuteWorkTasks(me.value))
const canAdmin = computed(() => canAdministerWork(me.value))
const rowSelection = ref<Record<string, boolean>>({})
const bulkOpen = ref(false)

const departments = ref<WorkDepartment[]>([])

onMounted(async () => {
  try {
    const res = await api.work.departments.list({ per_page: 100, is_active: true })
    departments.value = Array.isArray(res?.data) ? res.data : []
  } catch {
    departments.value = []
  }
})

const queueDefinitions = computed((): DataTableFilterDefinition[] => [
  {
    key: 'department_id',
    kind: 'option',
    label: 'Departamento',
    emptyValue: '',
    items: departments.value.map(d => ({ label: d.name, value: String(d.id) }))
  },
  {
    key: 'client_id',
    kind: 'client',
    label: 'Cliente',
    emptyValue: null
  },
  {
    key: 'scope',
    kind: 'option',
    label: 'Escopo',
    emptyValue: 'default',
    items: [
      { label: 'Minhas', value: 'mine' },
      { label: 'Departamento', value: 'department' },
      { label: 'Escritório', value: 'office' }
    ]
  }
])

function queueModelsFromFilters(): DataTableFilterModel[] {
  const models: DataTableFilterModel[] = []
  const f = filters.value
  const defs = queueDefinitions.value

  if (f.department_id) {
    const def = findDefinition(defs, 'department_id')
    if (def) {
      const model = createFilterModel(def, String(f.department_id))
      if (model) models.push(model)
    }
  }
  if (f.client_id) {
    const def = findDefinition(defs, 'client_id')
    if (def) {
      const model = createFilterModel(def, f.client_id)
      if (model) models.push(model)
    }
  }
  if (f.scope && f.scope !== 'default') {
    const def = findDefinition(defs, 'scope')
    if (def) {
      const model = createFilterModel(def, f.scope)
      if (model) models.push(model)
    }
  }
  return models
}

const queueChipModels = computed(() => queueModelsFromFilters())

function onQueueModelsUpdate(models: DataTableFilterModel[]) {
  const dept = models.find(m => m.key === 'department_id')
  const client = models.find(m => m.key === 'client_id')
  const scope = models.find(m => m.key === 'scope')
  void patch({
    department_id: dept ? Number(dept.value) || null : null,
    client_id: client && typeof client.value === 'number' ? client.value : null,
    scope: scope ? String(scope.value) : 'default'
  })
}

function onQueueClear() {
  void patch({
    q: '',
    department_id: null,
    assignee_membership_id: null,
    client_id: null,
    scope: 'default',
    page: 1
  })
}

function onQueuePreset(payload: SavedListFilterPayload) {
  const next = workQueuePayloadToFilters(payload)
  void patch({
    tab: next.tab,
    q: next.q,
    department_id: next.department_id,
    assignee_membership_id: next.assignee_membership_id,
    client_id: next.client_id,
    scope: next.scope,
    page: 1,
    per_page: next.per_page
  }, { resetPage: false })
}

// Legado ?task= → path canônico
watch(
  () => route.query.task,
  async (legacy) => {
    if (legacy === undefined || legacy === null || legacy === '') return
    const id = Number(Array.isArray(legacy) ? legacy[0] : legacy)
    if (!Number.isFinite(id) || id <= 0) return
    const q = { ...(route.query as Record<string, unknown>) }
    delete q.task
    await router.replace({
      path: workTaskPath(id),
      query: serializeWorkQueueQuery(parseWorkQueueQuery(q))
    })
  },
  { immediate: true }
)

const items = ref<OperationalTaskSummary[]>([])
const detail = ref<OperationalTaskDetail | null>(null)
const loading = ref(false)
const detailLoading = ref(false)
const loadError = ref<string | null>(null)
const total = ref(0)
const itemRefs = ref<Record<number, { el: HTMLElement | null } | null>>({})
/**
 * Desktop Fila: mestre–detalhe estilo inbox/chat.
 * Detalhe aberto por default (empty state sem seleção); toggle ainda colapsa.
 */
const detailOpen = ref(true)
/** Evita re-selecionar logo após dismiss explícito (X). */
const suppressAutoSelect = ref(false)

const breakpoints = useBreakpoints(breakpointsTailwind)
const isMobile = breakpoints.smaller('lg')

const queueView = computed(() => filters.value.view)
const isLista = computed(() => queueView.value === 'lista')
const isFila = computed(() => !isLista.value)
const detailPaneVisible = computed(
  () => isFila.value && !isMobile.value && detailOpen.value
)

function setQueueView(next: WorkQueueView) {
  if (filters.value.view === next) return
  if (next === 'fila') {
    detailOpen.value = true
    suppressAutoSelect.value = false
  }
  void patch({ view: next }, { resetPage: false })
}

const tabs = [
  { label: 'Abertas', value: 'open' },
  { label: 'Hoje', value: 'hoje' },
  { label: 'Atrasadas', value: 'atrasadas' },
  { label: 'Semana', value: 'semana' },
  { label: 'Impedidas', value: 'impedidas' },
  { label: 'Concluídas', value: 'concluidas' }
]

const selectedTab = computed({
  get: () => filters.value.tab,
  set: (v: string) => {
    void router.replace({
      path: workQueuePath(),
      query: serializeWorkQueueQuery({
        ...filters.value,
        tab: v,
        page: 1
      })
    })
  }
})

const selectedId = selectedTaskId

const detailSlideoverOpen = computed({
  get: () => {
    if (!selectedId.value) return false
    return isLista.value || isMobile.value
  },
  set: (open: boolean) => {
    if (!open) void clearSelection()
  }
})

async function loadQueue() {
  const epoch = sessionEpoch.value
  loading.value = true
  loadError.value = null
  try {
    const res = await api.work.queue(apiParams())
    if (epoch !== sessionEpoch.value) return
    items.value = res.data
    total.value = res.meta.total
    rowSelection.value = {}

    const current = selectedTaskId.value
    if (current && !items.value.some(i => i.id === current)) {
      if (route.path.startsWith('/work/tasks/')) {
        await clearTask()
      }
    }

    if (selectedTaskId.value) {
      await loadDetail(selectedTaskId.value)
    } else {
      detail.value = null
      if (
        !suppressAutoSelect.value
        && isFila.value
        && !isMobile.value
        && items.value.length > 0
      ) {
        detailOpen.value = true
        await select(items.value[0]!.id)
        return
      }
    }
  } catch (e) {
    if (epoch !== sessionEpoch.value) return
    loadError.value = apiErrorMessage(e, 'Não foi possível carregar a fila.')
    toast.add({ title: loadError.value, color: 'error' })
  } finally {
    if (epoch === sessionEpoch.value) loading.value = false
  }
}

async function loadDetail(id: number) {
  const epoch = sessionEpoch.value
  detailLoading.value = true
  try {
    const res = await api.work.tasks.get(id)
    if (epoch !== sessionEpoch.value) return
    if (selectedTaskId.value !== id) return
    detail.value = res.data
  } catch (e) {
    if (epoch !== sessionEpoch.value) return
    toast.add({ title: apiErrorMessage(e, 'Falha ao carregar tarefa.'), color: 'error' })
    detail.value = null
  } finally {
    if (epoch === sessionEpoch.value) detailLoading.value = false
  }
}

async function select(id: number) {
  suppressAutoSelect.value = false
  await selectTask(id)
  if (isFila.value && !isMobile.value) detailOpen.value = true
  await loadDetail(id)
  if (isFila.value) {
    nextTick(() => {
      const ref = itemRefs.value[id]
      const el = ref?.el
      el?.scrollIntoView({ block: 'nearest' })
    })
  }
}

function toggleDetail() {
  if (!isFila.value) return
  detailOpen.value = !detailOpen.value
}

async function clearSelection() {
  suppressAutoSelect.value = true
  detailOpen.value = true
  detail.value = null
  await clearTask()
}

const search = computed({
  get: () => filters.value.q,
  set: (v: string) => { void patch({ q: v }) }
})

function onQueueSearch(value: string) {
  search.value = value
}

function onListPage(page: number) {
  void patch({ page }, { resetPage: false })
}

function onListPerPage(perPage: number) {
  void patch({ per_page: perPage, page: 1 }, { resetPage: false })
}

const taskListColumns = computed<TableColumn<OperationalTaskSummary>[]>(() => {
  const selectColumn: TableColumn<OperationalTaskSummary> = {
    id: 'select',
    enableHiding: false,
    enableSorting: false,
    meta: { class: { th: 'w-10 min-w-10', td: 'w-10 min-w-10' } },
    header: ({ table: current }) => h(UCheckbox, {
      'modelValue': current.getIsSomePageRowsSelected()
        ? 'indeterminate'
        : current.getIsAllPageRowsSelected(),
      'onUpdate:modelValue': (value: unknown) =>
        current.toggleAllPageRowsSelected(!!value),
      'ariaLabel': 'Selecionar todas as tarefas desta página'
    }),
    cell: ({ row }) => h(UCheckbox, {
      'modelValue': row.getIsSelected(),
      'onUpdate:modelValue': (value: unknown) => row.toggleSelected(!!value),
      'ariaLabel': `Selecionar ${row.original.title}`
    })
  }

  const columns: TableColumn<OperationalTaskSummary>[] = [
    {
      accessorKey: 'title',
      header: ({ column }) => sortHeader('Tarefa', column),
      meta: { class: { th: 'w-full max-w-0 min-w-48', td: 'w-full max-w-0 min-w-48' } }
    },
    {
      accessorKey: 'status',
      header: ({ column }) => sortHeader('Status', column),
      meta: { class: { th: 'w-40 min-w-36', td: 'w-40 min-w-36' } }
    },
    {
      id: 'effective_due_date',
      accessorKey: 'effective_due_date',
      header: ({ column }) => sortHeader('Prazo', column),
      meta: { class: { th: 'w-28 min-w-24', td: 'w-28 min-w-24' } }
    },
    {
      id: 'client_name',
      accessorKey: 'client_name',
      header: ({ column }) => sortHeader('Cliente / Processo', column),
      enableSorting: true,
      meta: { class: { th: 'w-56 min-w-44', td: 'w-56 min-w-44' } }
    },
    {
      id: 'assignee_name',
      accessorKey: 'assignee_name',
      header: ({ column }) => sortHeader('Responsável', column),
      meta: { class: { th: 'w-36 min-w-28', td: 'w-36 min-w-28' } }
    },
    {
      accessorKey: 'actions',
      header: '',
      enableSorting: false,
      meta: { class: { th: 'w-14 min-w-12', td: 'w-14 min-w-12' } }
    }
  ]

  return (canExecute.value || canAdmin.value) ? [selectColumn, ...columns] : columns
})

const sortingState = computed(() => {
  if (!filters.value.sort) return []
  return [{ id: filters.value.sort, desc: filters.value.direction === 'desc' }]
})

function onListSortingUpdate(next: Array<{ id: string, desc: boolean }>) {
  const first = next[0]
  const allowed = new Set(['title', 'status', 'effective_due_date', 'client_name', 'assignee_name'])
  if (!first || !allowed.has(first.id)) {
    void patch({ sort: null, direction: null, page: 1 }, { resetPage: false })
    return
  }
  void patch({
    sort: first.id,
    direction: first.desc ? 'desc' : 'asc',
    page: 1
  }, { resetPage: false })
}

const selectedTaskBulkItems = computed<WorkBulkItem[]>(() =>
  items.value
    .filter(task => rowSelection.value[String(task.id)] === true)
    .map(task => ({
      id: task.id,
      lock_version: task.lock_version,
      label: task.title
    }))
)

const selectedCount = computed(() => selectedTaskBulkItems.value.length)

const canBulk = computed(() => canExecute.value || canAdmin.value)

function openBulkActions() {
  if (!selectedCount.value || !canBulk.value) return
  bulkOpen.value = true
}

function clearListSelection() {
  rowSelection.value = {}
}

function taskOriginLabel(item: OperationalTaskSummary): string {
  const client = item.process?.client?.name
  const process = item.process?.title
  if (client && process) return `${client} · ${process}`
  if (client) return client
  if (process) return process
  return 'Sem cliente'
}

defineShortcuts({
  arrowdown: () => {
    if (isLista.value || isInputFocused()) return
    const list = items.value
    if (!list.length) return
    const idx = list.findIndex(i => i.id === selectedId.value)
    const next = idx === -1 ? list[0] : list[Math.min(list.length - 1, idx + 1)]
    if (next) void select(next.id)
  },
  arrowup: () => {
    if (isLista.value || isInputFocused()) return
    const list = items.value
    if (!list.length) return
    const idx = list.findIndex(i => i.id === selectedId.value)
    const next = idx === -1 ? list[list.length - 1] : list[Math.max(0, idx - 1)]
    if (next) void select(next.id)
  },
  escape: () => {
    if (isLista.value || isInputFocused()) return
    if (detailOpen.value && isFila.value && !isMobile.value) {
      detailOpen.value = false
      return
    }
    if (selectedId.value) void clearSelection()
  }
})

watch(
  selectedTaskId,
  (id, prev) => {
    // Deep-link / chegada com id no path: abre o detalhe na Fila desktop
    if (id && !prev && isFila.value && !isMobile.value) {
      detailOpen.value = true
    }
  },
  { immediate: true }
)

watch(
  () => [
    filters.value.tab,
    filters.value.q,
    filters.value.department_id,
    filters.value.client_id,
    filters.value.scope
  ],
  () => {
    suppressAutoSelect.value = false
  }
)

function isInputFocused() {
  if (!import.meta.client) return false
  const el = document.activeElement as HTMLElement | null
  if (!el) return false
  const tag = el.tagName
  return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || el.isContentEditable
}

watch(
  () => [
    filters.value.tab,
    filters.value.q,
    filters.value.department_id,
    filters.value.assignee_membership_id,
    filters.value.client_id,
    filters.value.scope,
    filters.value.page,
    filters.value.per_page,
    filters.value.view,
    filters.value.sort,
    filters.value.direction,
    selectedTaskId.value,
    sessionEpoch.value
  ],
  () => { void loadQueue() },
  { immediate: true }
)

watch(sessionEpoch, () => {
  items.value = []
  detail.value = null
  detailOpen.value = true
  suppressAutoSelect.value = false
  loadError.value = null
  clearListSelection()
  void clearTask()
  void patch({
    page: 1,
    department_id: null,
    client_id: null,
    assignee_membership_id: null,
    q: '',
    scope: 'default',
    sort: null,
    direction: null
  })
})
</script>

<template>
  <!-- ===== Visão Lista (painel único) ===== -->
  <template v-if="isLista">
    <UDashboardPanel
      id="work-queue-list-view"
      data-testid="work-queue-list-panel"
      class="min-w-0"
    >
      <template #header>
        <UDashboardNavbar title="Tarefas" data-testid="page-navbar">
          <template #leading>
            <UDashboardSidebarCollapse />
          </template>
          <template #trailing>
            <UFieldGroup data-testid="work-queue-view-toggle">
              <UButton
                label="Fila"
                icon="i-lucide-messages-square"
                size="sm"
                :variant="isFila ? 'solid' : 'outline'"
                :color="isFila ? 'primary' : 'neutral'"
                @click="setQueueView('fila')"
              />
              <UButton
                label="Lista"
                icon="i-lucide-list"
                size="sm"
                :variant="isLista ? 'solid' : 'outline'"
                :color="isLista ? 'primary' : 'neutral'"
                @click="setQueueView('lista')"
              />
            </UFieldGroup>
            <UBadge
              :label="String(total)"
              variant="subtle"
              data-testid="work-queue-total"
            />
          </template>
        </UDashboardNavbar>

        <UDashboardToolbar
          data-testid="work-queue-toolbar"
          :ui="{
            root: 'flex-col items-stretch justify-start gap-2 py-2 overflow-x-auto min-h-0'
          }"
        >
          <ShellScrollableTabs
            v-model="selectedTab"
            :items="tabs"
            size="sm"
            class="w-full min-w-0"
            aria-label="Filtrar fila por prazo"
            test-id="work-queue-tabs"
          />
          <ShellListFilterToolbar
            :q="search"
            search-placeholder="Buscar tarefa ou processo…"
            search-aria-label="Buscar na fila"
            :definitions="queueDefinitions"
            :models="queueChipModels"
            :loading="loading"
            :reset-key="sessionEpoch"
            surface="work.queue"
            :get-payload="() => workQueueFiltersToPayload(filters)"
            :can-save="() => hasActiveWorkQueueFiltersForSave(filters)"
            test-id-prefix="work-queue"
            @update:q="onQueueSearch"
            @update:models="onQueueModelsUpdate"
            @clear="onQueueClear"
            @refresh="loadQueue"
            @apply-preset="onQueuePreset"
          >
            <template #actions>
              <div
                v-if="canBulk && selectedCount > 0"
                data-testid="work-queue-bulk-actions"
              >
                <UButton
                  color="neutral"
                  variant="subtle"
                  icon="i-lucide-list-checks"
                  label="Ações"
                  aria-label="Ações em massa"
                  :ui="COMPACT_BUTTON_LABEL_UI"
                  data-testid="work-queue-bulk-actions-menu"
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
          Tarefas
        </h1>

        <ShellDataTable
          v-model:row-selection="rowSelection"
          :sorting="sortingState"
          test-id="work-queue-table"
          ui-preset="monitoring-compact"
          primary-column-id="title"
          status-column-id="status"
          :summary-column-ids="['effective_due_date', 'client_name', 'assignee_name']"
          :columns="taskListColumns"
          :data="items"
          :loading="loading"
          :error="loadError"
          :page="filters.page"
          :total="total"
          :items-per-page="filters.per_page"
          :selected-count="selectedCount"
          :selection-enabled="canExecute || canAdmin"
          :manual-sorting="true"
          empty-title="Nenhuma tarefa nesta aba"
          empty-description="Ajuste filtros ou gere processos a partir de um modelo."
          per-page-aria-label="Tarefas por página"
          footer-test-id="work-queue-list-footer"
          @update:page="onListPage"
          @update:items-per-page="onListPerPage"
          @update:sorting="onListSortingUpdate"
          @retry="loadQueue"
        >
          <template #title-cell="{ row }">
            <div class="min-w-0">
              <p class="truncate font-medium text-highlighted">
                {{ row.original.title }}
              </p>
              <p
                v-if="row.original.is_critical"
                class="text-xs text-warning"
              >
                Crítica
              </p>
            </div>
          </template>
          <template #status-cell="{ row }">
            <WorkTaskStatusSelect
              :task-id="row.original.id"
              :status="row.original.status"
              :lock-version="row.original.lock_version"
              :can-claim="!row.original.assignee?.membership_id"
              :disabled="!(canExecute || canAdmin)"
              @updated="loadQueue"
            />
          </template>
          <template #effective_due_date-cell="{ row }">
            <span class="tabular-nums text-sm">
              {{ formatDueDate(row.original.effective_due_date || row.original.due_date) }}
            </span>
          </template>
          <template #client_name-cell="{ row }">
            <span class="block truncate text-sm text-toned">
              {{ taskOriginLabel(row.original) }}
            </span>
          </template>
          <template #assignee_name-cell="{ row }">
            <span
              class="block truncate text-sm"
              :class="row.original.assignee?.name ? '' : 'text-warning'"
            >
              {{ row.original.assignee?.name || 'Sem responsável' }}
            </span>
          </template>
          <template #actions-cell="{ row }">
            <div class="flex justify-end">
              <UButton
                size="xs"
                color="neutral"
                variant="ghost"
                icon="i-lucide-arrow-up-right"
                aria-label="Abrir tarefa"
                @click.stop="select(row.original.id)"
              />
            </div>
          </template>
        </ShellDataTable>

        <WorkBulkActionsModal
          v-model:open="bulkOpen"
          :tasks="selectedTaskBulkItems"
          :can-administer="canAdmin"
          @done="() => { clearListSelection(); void loadQueue() }"
        />
      </template>
    </UDashboardPanel>
  </template>

  <!-- ===== Visão Fila (mestre–detalhe) ===== -->
  <template v-else>
    <UDashboardPanel
      id="work-queue-list"
      data-testid="work-queue-panel"
      :resizable="detailPaneVisible"
      :default-size="detailPaneVisible ? 28 : undefined"
      :min-size="detailPaneVisible ? 22 : undefined"
      :max-size="detailPaneVisible ? 36 : undefined"
      :class="detailPaneVisible ? 'min-w-0' : 'min-w-0 flex-1'"
    >
      <template #header>
        <UDashboardNavbar title="Tarefas" data-testid="page-navbar">
          <template #leading>
            <UDashboardSidebarCollapse />
          </template>
          <template #trailing>
            <UFieldGroup data-testid="work-queue-view-toggle">
              <UButton
                label="Fila"
                icon="i-lucide-messages-square"
                size="sm"
                :variant="isFila ? 'solid' : 'outline'"
                :color="isFila ? 'primary' : 'neutral'"
                @click="setQueueView('fila')"
              />
              <UButton
                label="Lista"
                icon="i-lucide-list"
                size="sm"
                :variant="isLista ? 'solid' : 'outline'"
                :color="isLista ? 'primary' : 'neutral'"
                @click="setQueueView('lista')"
              />
            </UFieldGroup>
            <UBadge
              :label="String(total)"
              variant="subtle"
              data-testid="work-queue-total"
            />
            <UTooltip
              :text="detailOpen ? 'Fechar detalhe' : 'Abrir detalhe'"
              class="hidden lg:inline-flex"
            >
              <UButton
                icon="i-lucide-panel-right"
                :color="detailOpen ? 'primary' : 'neutral'"
                :variant="detailOpen ? 'soft' : 'ghost'"
                :aria-label="detailOpen ? 'Fechar detalhe' : 'Abrir detalhe'"
                :aria-pressed="detailOpen"
                data-testid="work-queue-detail-toggle"
                @click="toggleDetail"
              />
            </UTooltip>
          </template>
        </UDashboardNavbar>

        <UDashboardToolbar
          data-testid="work-queue-toolbar"
          :ui="{
            root: 'flex-col items-stretch justify-start gap-2 py-2 overflow-x-auto min-h-0'
          }"
        >
          <ShellScrollableTabs
            v-model="selectedTab"
            :items="tabs"
            size="sm"
            class="w-full min-w-0"
            aria-label="Filtrar fila por prazo"
            test-id="work-queue-tabs"
          />
          <ShellListFilterToolbar
            :q="search"
            search-placeholder="Buscar tarefa ou processo…"
            search-aria-label="Buscar na fila"
            :definitions="queueDefinitions"
            :models="queueChipModels"
            :loading="loading"
            :reset-key="sessionEpoch"
            surface="work.queue"
            :get-payload="() => workQueueFiltersToPayload(filters)"
            :can-save="() => hasActiveWorkQueueFiltersForSave(filters)"
            test-id-prefix="work-queue"
            @update:q="onQueueSearch"
            @update:models="onQueueModelsUpdate"
            @clear="onQueueClear"
            @refresh="loadQueue"
            @apply-preset="onQueuePreset"
          >
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
          Tarefas
        </h1>

        <div v-if="loadError" class="p-4">
          <UAlert color="error" :title="loadError">
            <template #actions>
              <UButton
                size="xs"
                variant="soft"
                label="Tentar de novo"
                @click="loadQueue"
              />
            </template>
          </UAlert>
        </div>

        <div v-else-if="loading" class="space-y-3 p-4">
          <USkeleton v-for="i in 6" :key="i" class="h-16 w-full" />
        </div>

        <UEmpty
          v-else-if="!items.length"
          data-testid="work-queue-empty"
          icon="i-lucide-inbox"
          title="Nenhuma tarefa nesta aba"
          description="Ajuste filtros ou gere processos a partir de um modelo."
        />

        <div
          v-else
          role="listbox"
          aria-label="Fila de tarefas"
          class="overflow-y-auto divide-y divide-default"
        >
          <WorkQueueListItem
            v-for="item in items"
            :key="item.id"
            :ref="(el: unknown) => { itemRefs[item.id] = el as { el: HTMLElement | null } | null }"
            :item="item"
            :selected="selectedId === item.id"
            @select="select"
          />
        </div>
      </template>
    </UDashboardPanel>

    <WorkTaskDetailPanel
      v-if="detailPaneVisible"
      class="hidden min-w-0 flex-1 lg:flex"
      :detail="detail"
      :loading="detailLoading"
      @close="clearSelection"
      @refreshed="loadQueue"
    />
  </template>

  <USlideover
    v-if="isLista || isMobile"
    v-model:open="detailSlideoverOpen"
    title="Tarefa"
    :class="isLista ? undefined : 'lg:hidden'"
    @update:open="(v: boolean) => { if (!v) clearSelection() }"
  >
    <template #body>
      <WorkTaskDetailPanel
        :detail="detail"
        :loading="detailLoading"
        @close="clearSelection"
        @refreshed="loadQueue"
      />
    </template>
  </USlideover>
</template>

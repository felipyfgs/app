<script setup lang="ts">
/**
 * Workspace da fila de trabalho (mestre–detalhe).
 *
 * URL canônica:
 * - `/work` — sem seleção
 * - `/work/tasks/{id}` — tarefa no path
 * - query: só filtros (tab, q, page…) — nunca `task` / `office_id`
 *
 * Compat: `/work?task=N` → `/work/tasks/N` (preserva demais query).
 */
import WorkSectionNav from '~/components/navigation/WorkSectionNav.vue'
import { breakpointsTailwind } from '@vueuse/core'
import type { OperationalTaskDetail, OperationalTaskSummary, WorkDepartment } from '~/types/work'
import type { DataTableFilterDefinition, DataTableFilterModel } from '~/types/data-table-filter'
import type { SavedListFilterPayload } from '~/types/saved-list-filters'
import { apiErrorMessage } from '~/utils/api-error'
import {
  parseWorkQueueQuery,
  serializeWorkQueueQuery,
  useWorkQueueFilters,
  workQueuePath,
  workTaskPath
} from '~/composables/useWorkQueueFilters'
import {
  hasActiveWorkQueueFiltersForSave,
  workQueueFiltersToPayload,
  workQueuePayloadToFilters
} from '~/utils/saved-list-filters'
import { createFilterModel, findDefinition } from '~/utils/data-table-filters'
import ShellListFilterToolbar from '~/components/shell/ListFilterToolbar.vue'
import ShellScrollableTabs from '~/components/shell/ScrollableTabs.vue'

const route = useRoute()
const router = useRouter()
const api = useApi()
const toast = useToast()
const { sessionEpoch } = useDashboard()
const {
  filters,
  selectedTaskId,
  patch,
  selectTask,
  clearTask,
  apiParams
} = useWorkQueueFilters()

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
const mobileOpen = ref(false)
const itemRefs = ref<Record<number, { el: HTMLElement | null } | null>>({})

const breakpoints = useBreakpoints(breakpointsTailwind)
const isMobile = breakpoints.smaller('lg')

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
    // Troca de aba limpa a seleção (volta a /work) e aplica o filtro na query.
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

async function loadQueue() {
  const epoch = sessionEpoch.value
  loading.value = true
  loadError.value = null
  try {
    const res = await api.work.queue(apiParams())
    if (epoch !== sessionEpoch.value) return
    items.value = res.data
    total.value = res.meta.total

    const current = selectedTaskId.value
    if (current && !items.value.some(i => i.id === current)) {
      // Tarefa fora da lista filtrada: limpa seleção (mantém filtros)
      if (route.path.startsWith('/work/tasks/')) {
        await clearTask()
      }
    } else if (!current && items.value[0] && !isMobile.value) {
      // Desktop: auto-seleciona a primeira via path canônico
      await selectTask(items.value[0].id)
      return
    }

    if (selectedTaskId.value) {
      await loadDetail(selectedTaskId.value)
    } else {
      detail.value = null
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
  await selectTask(id)
  if (isMobile.value) mobileOpen.value = true
  await loadDetail(id)
  nextTick(() => {
    const ref = itemRefs.value[id]
    const el = ref?.el
    el?.scrollIntoView({ block: 'nearest' })
  })
}

async function clearSelection() {
  mobileOpen.value = false
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

defineShortcuts({
  arrowdown: () => {
    if (isInputFocused()) return
    const list = items.value
    if (!list.length) return
    const idx = list.findIndex(i => i.id === selectedId.value)
    const next = idx === -1 ? list[0] : list[Math.min(list.length - 1, idx + 1)]
    if (next) void select(next.id)
  },
  arrowup: () => {
    if (isInputFocused()) return
    const list = items.value
    if (!list.length) return
    const idx = list.findIndex(i => i.id === selectedId.value)
    const next = idx === -1 ? list[list.length - 1] : list[Math.max(0, idx - 1)]
    if (next) void select(next.id)
  }
})

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
    selectedTaskId.value,
    sessionEpoch.value
  ],
  () => { void loadQueue() },
  { immediate: true }
)

watch(sessionEpoch, () => {
  items.value = []
  detail.value = null
  loadError.value = null
  mobileOpen.value = false
  void clearTask()
  void patch({
    page: 1,
    department_id: null,
    client_id: null,
    assignee_membership_id: null,
    q: '',
    scope: 'default'
  })
})

// Mobile: path com task abre slideover
watch(selectedTaskId, (id) => {
  if (id && isMobile.value) mobileOpen.value = true
  if (!id) mobileOpen.value = false
}, { immediate: true })
</script>

<template>
  <UDashboardPanel
    id="work-queue-list"
    data-testid="work-queue-panel"
    resizable
    :default-size="28"
    :min-size="22"
    :max-size="36"
    class="min-w-0"
  >
    <template #header>
      <UDashboardNavbar title="Minha fila" data-testid="page-navbar">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
        <template #trailing>
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
        <WorkSectionNav />
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
        Minha fila
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
    v-if="!isMobile && selectedId"
    class="hidden min-w-0 flex-1 lg:flex"
    :detail="detail"
    :loading="detailLoading"
    @close="clearSelection"
    @refreshed="loadQueue"
  />
  <div
    v-else-if="!isMobile"
    class="hidden min-w-0 flex-1 items-center justify-center lg:flex"
    data-testid="work-queue-neutral"
  >
    <UIcon name="i-lucide-inbox" class="size-32 text-dimmed" />
  </div>

  <USlideover
    v-model:open="mobileOpen"
    title="Tarefa"
    class="lg:hidden"
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

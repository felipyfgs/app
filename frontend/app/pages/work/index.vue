<script setup lang="ts">
/**
 * Minha fila — arquétipo Inbox (inbox.vue + InboxList + InboxMail).
 * Dois UDashboardPanel irmãos; dados reais via /api/v1/work/queue.
 */
import { breakpointsTailwind } from '@vueuse/core'
import type { OperationalTaskDetail, OperationalTaskSummary } from '~/types/work'
import { apiErrorMessage } from '~/utils/api-error'
import { useWorkQueueFilters } from '~/composables/useWorkQueueFilters'

const api = useApi()
const toast = useToast()
const { sessionEpoch } = useDashboard()
const { filters, patch, apiParams } = useWorkQueueFilters()

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
  set: (v: string) => { void patch({ tab: v, task: null }) }
})

const selectedId = computed(() => filters.value.task)

async function loadQueue() {
  const epoch = sessionEpoch.value
  loading.value = true
  loadError.value = null
  try {
    const res = await api.work.queue(apiParams())
    if (epoch !== sessionEpoch.value) return
    items.value = res.data
    total.value = res.meta.total

    const current = filters.value.task
    if (current && !items.value.some(i => i.id === current)) {
      await patch({ task: items.value[0]?.id ?? null }, { resetPage: false })
    } else if (!current && items.value[0] && !isMobile.value) {
      await patch({ task: items.value[0].id }, { resetPage: false })
    }

    if (filters.value.task) {
      await loadDetail(filters.value.task)
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
    if (filters.value.task !== id) return
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
  await patch({ task: id }, { resetPage: false })
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
  await patch({ task: null }, { resetPage: false })
  detail.value = null
}

const search = computed({
  get: () => filters.value.q,
  set: (v: string) => { void patch({ q: v }) }
})

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
  // Zera filtros tenant-scoped (IDs de office anterior não devem filtrar o próximo).
  void patch({
    task: null,
    page: 1,
    department_id: null,
    client_id: null,
    assignee_membership_id: null,
    q: ''
  })
})
</script>

<template>
  <UDashboardPanel id="work-queue-list" data-testid="work-queue-panel" resizable :default-size="25" :min-size="20" :max-size="30">
  <template #header>
    <UDashboardNavbar title="Minha fila" data-testid="page-navbar">
      <template #leading>
        <UDashboardSidebarCollapse />
      </template>
      <template #trailing>

      <UBadge :label="String(total)" variant="subtle" />
      </template>
      <template #right>

      <UTabs
        v-model="selectedTab"
        :items="tabs"
        :content="false"
        size="xs"
        class="max-w-full overflow-x-auto"
      />
      </template>
    </UDashboardNavbar>

      <UDashboardToolbar>
        <UInput
          v-model="search"
          icon="i-lucide-search"
          placeholder="Buscar tarefa ou processo…"
          class="w-full max-w-sm"
          aria-label="Buscar na fila"
        />
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

  <!-- Desktop: painel adjacente -->
  <WorkTaskDetailPanel
    v-if="!isMobile && selectedId"
    class="hidden lg:flex"
    :detail="detail"
    :loading="detailLoading"
    @close="clearSelection"
    @refreshed="loadQueue"
  />
  <div
    v-else-if="!isMobile"
    class="hidden lg:flex flex-1 items-center justify-center"
    data-testid="work-queue-neutral"
  >
    <UIcon name="i-lucide-inbox" class="size-32 text-dimmed" />
  </div>

  <!-- Mobile: slideover -->
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

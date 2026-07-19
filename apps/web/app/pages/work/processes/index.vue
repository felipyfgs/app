<script setup lang="ts">
/**
 * Listagem de processos — arquétipo customers.vue (lista admin server-side).
 */
import type { TableColumn } from '@nuxt/ui'
import type { OperationalProcess } from '~/types/work'
import { canManageWorkCatalog } from '~/utils/permissions'
import { apiErrorMessage } from '~/utils/api-error'
import {
  TABLE_CELL_BADGE_CLASS,
  TABLE_CELL_BADGE_UI
} from '~/utils/table-ui'
import ShellDataTable from '~/components/shell/DataTable.vue'
import {
  formatCompetence,
  formatDueDate,
  highestRiskColor,
  processStatusColor,
  processStatusLabel,
  workRiskLabel
} from '~/utils/work-labels'
import { truncateText } from '~/utils/format'
import type { DataTableFilterDefinition, DataTableFilterModel } from '~/types/data-table-filter'
import {
  createFilterModel,
  findDefinition
} from '~/utils/data-table-filters'
import {
  hasActiveWorkProcessesFiltersForSave,
  workProcessesFiltersToPayload,
  workProcessesPayloadToFilters
} from '~/utils/saved-list-filters'
import DataTableFilterRoot from '~/components/data-table-filter/Root.vue'
import DataTableFilterSaveFilterModal from '~/components/data-table-filter/SaveFilterModal.vue'
import DataTableFilterSavedFiltersMenu from '~/components/data-table-filter/SavedFiltersMenu.vue'
import DataTableFilterManageSavedFiltersModal from '~/components/data-table-filter/ManageSavedFiltersModal.vue'
import {
  COMPACT_BUTTON_LABEL_UI,
  LIST_FILTER_ACTIONS_ROW,
  LIST_FILTER_SEARCH_INPUT,
  LIST_FILTER_TOOLBAR_STACK
} from '~/utils/list-filter-layout'

const api = useApi()
const router = useRouter()
const route = useRoute()
const toast = useToast()
const { me, sessionEpoch } = useDashboard()

const items = ref<OperationalProcess[]>([])
const loading = ref(false)
const page = ref(Math.max(1, Number(route.query.page) || 1))
const perPage = ref(20)
const total = ref(0)
const q = ref(String(route.query.q || ''))
const competence = ref(String(route.query.competence || ''))
/** 'all' = sem filtro. */
const status = ref(String(route.query.status || 'all'))

const processFilterDefinitions: DataTableFilterDefinition[] = [
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
  }
]

function modelsFromProcessState(): DataTableFilterModel[] {
  const models: DataTableFilterModel[] = []
  const competenceDef = findDefinition(processFilterDefinitions, 'competence')
  const statusDef = findDefinition(processFilterDefinitions, 'status')
  if (competenceDef) {
    const model = createFilterModel(competenceDef, competence.value)
    if (model) models.push(model)
  }
  if (statusDef) {
    const model = createFilterModel(statusDef, status.value)
    if (model) models.push(model)
  }
  return models
}

const chipModels = ref<DataTableFilterModel[]>(modelsFromProcessState())

function syncChipsFromState() {
  chipModels.value = modelsFromProcessState()
}

function onStructuredFilters(models: DataTableFilterModel[]) {
  const competenceModel = models.find(m => m.key === 'competence')
  const statusModel = models.find(m => m.key === 'status')
  competence.value = competenceModel ? String(competenceModel.value) : ''
  status.value = statusModel ? String(statusModel.value) : 'all'
  chipModels.value = models
  page.value = 1
}

function onClearStructuredFilters() {
  competence.value = ''
  status.value = 'all'
  chipModels.value = []
  page.value = 1
}

const {
  canSavePreset,
  canShare: canShareFilters,
  presets,
  presetsLoading,
  saveOpen,
  manageOpen,
  saveLoading,
  saveError,
  manageError,
  actingId,
  clearPresetCache,
  onSavedMenuOpen,
  applyPreset,
  onSaveConfirm,
  onRename,
  onToggleShare,
  onDeletePreset,
  openManage,
  openSave
} = useSavedListPresets({
  surface: 'work.processes',
  resetKey: sessionEpoch,
  getPayload: () => workProcessesFiltersToPayload({
    q: q.value,
    competence: competence.value,
    status: status.value
  }),
  canSave: () => hasActiveWorkProcessesFiltersForSave({
    q: q.value,
    competence: competence.value,
    status: status.value
  }),
  onApply: (payload) => {
    const next = workProcessesPayloadToFilters(payload)
    q.value = next.q
    competence.value = next.competence
    status.value = next.status
    page.value = 1
    syncChipsFromState()
    // watch em [page,q,competence,status] sincroniza URL e recarrega
  }
})

// CTA leva a /work/templates (catálogo/lote) — só ADMIN com 2FA.
const canOpenTemplates = computed(() => canManageWorkCatalog(me.value))

const columns: TableColumn<OperationalProcess>[] = [
  { accessorKey: 'title', header: 'Processo' },
  { accessorKey: 'client', header: 'Cliente' },
  { accessorKey: 'competence', header: 'Competência' },
  { accessorKey: 'status', header: 'Status' },
  { accessorKey: 'progress', header: 'Progresso' },
  { accessorKey: 'risk', header: 'Risco' },
  { accessorKey: 'due_date', header: 'Prazo' },
  { accessorKey: 'assignee', header: 'Responsável' }
]

async function load() {
  const epoch = sessionEpoch.value
  loading.value = true
  try {
    const res = await api.work.processes.list({
      page: page.value,
      per_page: perPage.value,
      q: q.value || undefined,
      competence: competence.value || undefined,
      status: status.value && status.value !== 'all' ? status.value : undefined
    })
    if (epoch !== sessionEpoch.value) return
    items.value = res.data
    total.value = res.meta.total
  } catch (e) {
    if (epoch !== sessionEpoch.value) return
    toast.add({ title: apiErrorMessage(e, 'Falha ao listar processos.'), color: 'error' })
  } finally {
    if (epoch === sessionEpoch.value) loading.value = false
  }
}

function openRow(row: OperationalProcess) {
  navigateTo(`/work/processes/${row.id}`)
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

watch([page, q, competence, status], () => {
  router.replace({
    query: {
      page: page.value > 1 ? String(page.value) : undefined,
      q: q.value || undefined,
      competence: competence.value || undefined,
      status: status.value && status.value !== 'all' ? status.value : undefined
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
  chipModels.value = []
  total.value = 0
  clearPresetCache()
  void load()
})

onMounted(() => {
  syncChipsFromState()
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

      <DataTableFilterSaveFilterModal
        v-model:open="saveOpen"
        :can-share="canShareFilters"
        :loading="saveLoading"
        :error="saveError"
        @confirm="onSaveConfirm"
      />
      <DataTableFilterManageSavedFiltersModal
        v-model:open="manageOpen"
        :items="presets"
        :can-share="canShareFilters"
        :loading="presetsLoading"
        :acting-id="actingId"
        :error="manageError"
        @rename="onRename"
        @toggle-share="onToggleShare"
        @delete="onDeletePreset"
      />
    </template>

    <template #toolbar>
      <UDashboardToolbar>
        <div
          class="w-full min-w-0 p-1"
          data-testid="work-processes-toolbar"
        >
          <div :class="LIST_FILTER_TOOLBAR_STACK">
            <UInput
              v-model="q"
              icon="i-lucide-search"
              placeholder="Buscar…"
              :class="LIST_FILTER_SEARCH_INPUT"
              aria-label="Buscar processos"
            />
            <div :class="LIST_FILTER_ACTIONS_ROW">
              <DataTableFilterRoot
                :definitions="processFilterDefinitions"
                :model-value="chipModels"
                :reset-key="sessionEpoch"
                data-testid="work-processes-filters"
                @update:model-value="onStructuredFilters"
                @clear="onClearStructuredFilters"
              />
              <UButton
                v-if="canSavePreset"
                color="neutral"
                variant="outline"
                icon="i-lucide-save"
                label="Salvar"
                aria-label="Salvar filtros"
                :ui="COMPACT_BUTTON_LABEL_UI"
                data-testid="save-filters-button"
                @click="openSave"
              />
              <DataTableFilterSavedFiltersMenu
                :items="presets"
                :loading="presetsLoading"
                @apply="applyPreset"
                @manage="openManage"
                @open="onSavedMenuOpen"
              />
            </div>
          </div>
        </div>
      </UDashboardToolbar>
    </template>

    <template #body>
      <h1 data-testid="page-title" class="sr-only">
        Processos
      </h1>
      <ShellDataTable
        test-id="work-processes-table"
        ui-preset="monitoring-compact"
        table-class="cursor-pointer"
        primary-column-id="title"
        status-column-id="status"
        :summary-column-ids="['client', 'competence', 'progress', 'risk', 'due_date']"
        :columns="columns"
        :data="items"
        :loading="loading"
        :page="page"
        :total="total"
        :items-per-page="perPage"
        per-page-aria-label="Processos por página"
        @update:page="page = $event"
        @update:items-per-page="setPerPage"
        @select="(_e: Event, row: { original: OperationalProcess }) => openRow(row.original)"
      >
        <template #title-cell="{ row }">
          <span
            class="block min-w-0 max-w-xs truncate font-medium text-highlighted"
            :title="row.original.title || undefined"
          >
            {{ truncateText(row.original.title, 40) || row.original.title || '—' }}
          </span>
        </template>
        <template #client-cell="{ row }">
          <span
            class="block min-w-0 max-w-[12rem] truncate"
            :title="row.original.client?.name || undefined"
          >
            {{ truncateText(row.original.client?.name, 32) || row.original.client?.name || '—' }}
          </span>
        </template>
        <template #competence-cell="{ row }">
          {{ formatCompetence(row.original.competence) }}
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
          <div class="flex min-w-24 flex-col gap-1">
            <UProgress
              :model-value="row.original.progress_percent ?? 0"
              size="sm"
              :aria-label="`Progresso ${row.original.progress_percent ?? 0}%`"
            />
            <span class="text-xs text-muted">
              {{ row.original.completed_task_count ?? 0 }}/{{ row.original.task_count ?? 0 }}
            </span>
          </div>
        </template>
        <template #risk-cell="{ row }">
          <UBadge
            v-if="row.original.risks?.length"
            size="md"
            variant="subtle"
            :color="highestRiskColor(row.original.risks)"
            :label="workRiskLabel(row.original.risks[0]!)"
            :class="TABLE_CELL_BADGE_CLASS"
            :ui="TABLE_CELL_BADGE_UI"
          />
          <span v-else class="text-xs text-muted">—</span>
        </template>
        <template #due_date-cell="{ row }">
          {{ formatDueDate(row.original.due_date) }}
        </template>
        <template #assignee-cell="{ row }">
          {{ row.original.assignee?.name || '—' }}
        </template>
        <template #empty>
          <UEmpty
            icon="i-lucide-folder-open"
            title="Nenhum processo encontrado"
            description="Ajuste filtros ou crie um processo."
          />
        </template>
        <template #footer>
          <span class="tabular-nums">{{ total }}</span> processo(s)
        </template>
      </ShellDataTable>
    </template>
  </ShellPagePanel>
</template>

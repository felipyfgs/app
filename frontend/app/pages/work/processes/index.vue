<script setup lang="ts">
/**
 * Listagem de processos — arquétipo customers.vue (lista admin server-side).
 */
import type { TableColumn } from '@nuxt/ui'
import type { OperationalProcess } from '~/types/work'
import { canManageWorkCatalog } from '~/utils/permissions'
import { apiErrorMessage } from '~/utils/api-error'
import { DASHBOARD_TABLE_UI } from '~/utils/table-ui'
import {
  formatCompetence,
  formatDueDate,
  highestRiskColor,
  processStatusColor,
  processStatusLabel,
  workRiskLabel
} from '~/utils/work-labels'
import {
  hasActiveWorkProcessesFiltersForSave,
  workProcessesFiltersToPayload,
  workProcessesPayloadToFilters
} from '~/utils/saved-list-filters'
import DataTableFilterSaveFilterModal from '~/components/data-table-filter/SaveFilterModal.vue'
import DataTableFilterSavedFiltersMenu from '~/components/data-table-filter/SavedFiltersMenu.vue'
import DataTableFilterManageSavedFiltersModal from '~/components/data-table-filter/ManageSavedFiltersModal.vue'

const api = useApi()
const router = useRouter()
const route = useRoute()
const toast = useToast()
const { me, sessionEpoch } = useDashboard()

const items = ref<OperationalProcess[]>([])
const loading = ref(false)
const page = ref(Math.max(1, Number(route.query.page) || 1))
const total = ref(0)
const q = ref(String(route.query.q || ''))
const competence = ref(String(route.query.competence || ''))
/** 'all' = sem filtro (USelect não aceita value vazio). */
const status = ref(String(route.query.status || 'all'))

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
      per_page: 25,
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
  total.value = 0
  clearPresetCache()
  void load()
})

onMounted(load)
</script>

<template>
  <UDashboardPanel id="work-processes" data-testid="work-processes-panel">
    <template #header>
      <UDashboardNavbar title="Processos" data-testid="page-navbar">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
        <template #right>
          <UButton
            v-if="canOpenTemplates"
            icon="i-lucide-plus"
            label="Modelos / lote"
            to="/work/templates"
          />
        </template>
      </UDashboardNavbar>

      <UDashboardToolbar>
        <div class="flex flex-wrap items-center gap-2 p-1">
          <UInput
            v-model="q"
            icon="i-lucide-search"
            placeholder="Buscar…"
            class="w-56"
            aria-label="Buscar processos"
          />
          <UInput
            v-model="competence"
            placeholder="Competência YYYY-MM"
            class="w-40"
            aria-label="Filtrar por competência"
          />
          <USelect
            v-model="status"
            :items="[
              { label: 'Todos os status', value: 'all' },
              { label: 'A fazer', value: 'A_FAZER' },
              { label: 'Em progresso', value: 'EM_PROGRESSO' },
              { label: 'Impedido', value: 'IMPEDIDO' },
              { label: 'Concluído', value: 'CONCLUIDO' },
              { label: 'Arquivado', value: 'ARQUIVADO' }
            ]"
            class="w-44"
            aria-label="Filtrar por status"
          />
          <UButton
            v-if="canSavePreset"
            color="neutral"
            variant="outline"
            icon="i-lucide-save"
            label="Salvar"
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
      </UDashboardToolbar>

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

    <template #body>
      <h1 data-testid="page-title" class="sr-only">
        Processos
      </h1>
      <div v-if="loading" class="p-4 space-y-2">
        <USkeleton v-for="i in 5" :key="i" class="h-10 w-full" />
      </div>
      <UEmpty
        v-else-if="!items.length"
        icon="i-lucide-folder-open"
        title="Nenhum processo encontrado"
        description="Ajuste filtros ou crie um processo."
      />
      <template v-else>
        <UTable
          :data="items"
          :columns="columns"
          :ui="DASHBOARD_TABLE_UI"
          class="cursor-pointer"
          @select="(_e: Event, row: { original: OperationalProcess }) => openRow(row.original)"
        >
          <template #client-cell="{ row }">
            {{ row.original.client?.name || '—' }}
          </template>
          <template #competence-cell="{ row }">
            {{ formatCompetence(row.original.competence) }}
          </template>
          <template #status-cell="{ row }">
            <UBadge
              size="sm"
              variant="subtle"
              :color="processStatusColor(row.original.status)"
              :label="processStatusLabel(row.original.status)"
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
              size="sm"
              variant="subtle"
              :color="highestRiskColor(row.original.risks)"
              :label="workRiskLabel(row.original.risks[0]!)"
            />
            <span v-else class="text-xs text-muted">—</span>
          </template>
          <template #due_date-cell="{ row }">
            {{ formatDueDate(row.original.due_date) }}
          </template>
          <template #assignee-cell="{ row }">
            {{ row.original.assignee?.name || '—' }}
          </template>
        </UTable>
        <div class="flex items-center justify-between gap-2 border-t border-default p-3 text-sm text-muted">
          <span>{{ total }} processo(s)</span>
          <UPagination
            v-if="total > 25"
            v-model:page="page"
            :total="total"
            :items-per-page="25"
          />
        </div>
      </template>
    </template>
  </UDashboardPanel>
</template>

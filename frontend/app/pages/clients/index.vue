<script setup lang="ts">
/**
 * Lista admin de clientes — cópia do arquétipo customers.vue do template.
 * Fonte: .reference/nuxt-dashboard-template/app/pages/customers.vue
 * + https://dashboard-template.nuxt.dev/customers
 *
 * Busca, filtros, ordenação e paginação são server-side, com estado local como
 * no template. A URL canônica permanece `/clients`.
 */
import type { DropdownMenuItem, TableColumn } from '@nuxt/ui'
import type { Client, ClientListStats } from '~/types/api'
import { upperFirst } from 'scule'
import { sortHeader } from '~/utils/table-sort'
import { DENSE_DASHBOARD_TABLE_UI } from '~/utils/table-ui'
import type { DataTableFilterDefinition, DataTableFilterModel } from '~/types/data-table-filter'
import { createFilterModel, findDefinition } from '~/utils/data-table-filters'
import {
  clientsFiltersToPayload,
  clientsPayloadToFilters,
  hasActiveClientsFiltersForSave
} from '~/utils/saved-list-filters'
import DataTableFilterRoot from '~/components/data-table-filter/Root.vue'
import DataTableFilterSaveFilterModal from '~/components/data-table-filter/SaveFilterModal.vue'
import DataTableFilterSavedFiltersMenu from '~/components/data-table-filter/SavedFiltersMenu.vue'
import DataTableFilterManageSavedFiltersModal from '~/components/data-table-filter/ManageSavedFiltersModal.vue'

/** Mantém o preset customers.vue e reduz apenas o padding das células em xs. */
const clientsTableUi = {
  ...DENSE_DASHBOARD_TABLE_UI,
  th: `${DENSE_DASHBOARD_TABLE_UI.th} px-1 sm:px-4`,
  td: `${DENSE_DASHBOARD_TABLE_UI.td} px-1 sm:px-4`
}

const api = useApi()
const {
  canManageClients,
  canManageCredentials,
  isClientFormOpen,
  clientFormCreateNonce,
  sessionEpoch
} = useDashboard()
const toast = useToast()

const table = useTemplateRef('table')

const columnVisibility = ref()
const sorting = ref<{ id: string, desc: boolean }[]>([{ id: 'legal_name', desc: false }])
const page = ref(1)
const perPage = ref(20)
const total = ref(0)
const lastPage = ref(1)
const search = ref('')

const clients = ref<Client[]>([])
const stats = ref<ClientListStats>({
  total: 0,
  active: 0,
  with_credential: 0,
  without_credential: 0,
  credential_expiring_30d: 0,
  credential_expired: 0,
  capture_problem: 0
})
const loading = ref(false)
const loadError = ref<string | null>(null)

const formOpen = isClientFormOpen
const formClient = ref<Client | null>(null)
const detailOpen = ref(false)
const detailClientId = ref<number | null>(null)
const detailSection = ref<'resumo' | 'cadastro' | 'certificado' | 'sincronizacao'>('resumo')
const credentialModalOpen = ref(false)
const credentialModalClient = ref<Client | null>(null)
const deleteOpen = ref(false)
const deleteClient = ref<Client | null>(null)
const deleting = ref(false)

const emptyStats: ClientListStats = {
  total: 0,
  active: 0,
  with_credential: 0,
  without_credential: 0,
  credential_expiring_30d: 0,
  credential_expired: 0,
  capture_problem: 0
}

/**
 * Espelho do campo de busca do template, com consulta server-side.
 */
const filter = computed({
  get: (): string => search.value,
  set: (value: string) => {
    search.value = value
    page.value = 1
  }
})

type KpiFilter = 'total' | 'with_credential' | 'without_credential' | 'expiring'

/** Filtro dos cards KPI (clique = filtra a tabela pelo conteúdo do card). */
const kpiFilter = ref<KpiFilter>('total')
const statusFilter = ref('all')

const clientFilterDefinitions: DataTableFilterDefinition[] = [
  {
    key: 'status',
    kind: 'option',
    label: 'Estado',
    emptyValue: 'all',
    items: [
      { label: 'Ativos', value: 'active' },
      { label: 'Inativos', value: 'inactive' }
    ]
  }
]

function modelsFromClientStatus(): DataTableFilterModel[] {
  const def = findDefinition(clientFilterDefinitions, 'status')
  if (!def) return []
  const model = createFilterModel(def, statusFilter.value)
  return model ? [model] : []
}

const chipModels = ref<DataTableFilterModel[]>(modelsFromClientStatus())

function syncClientChips() {
  chipModels.value = modelsFromClientStatus()
}

function onStructuredFilters(models: DataTableFilterModel[]) {
  const statusModel = models.find(m => m.key === 'status')
  statusFilter.value = statusModel ? String(statusModel.value) : 'all'
  chipModels.value = models
  page.value = 1
}

function onClearStructuredFilters() {
  statusFilter.value = 'all'
  chipModels.value = []
  page.value = 1
}

const CLIENT_KPI_KEYS = new Set<string>([
  'total',
  'with_credential',
  'without_credential',
  'expiring'
])

/** Evita double-fetch quando preset hidrata vários refs de uma vez. */
let applyingClientsPreset = false

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
  surface: 'clients.index',
  resetKey: sessionEpoch,
  getPayload: () => clientsFiltersToPayload({
    q: search.value,
    status: statusFilter.value,
    operational_filter: kpiFilter.value
  }),
  canSave: () => hasActiveClientsFiltersForSave({
    q: search.value,
    status: statusFilter.value,
    operational_filter: kpiFilter.value
  }),
  onApply: (payload) => {
    const next = clientsPayloadToFilters(payload)
    applyingClientsPreset = true
    if (searchTimer) {
      clearTimeout(searchTimer)
      searchTimer = null
    }
    search.value = next.q
    statusFilter.value = next.status
    kpiFilter.value = (CLIENT_KPI_KEYS.has(next.operational_filter)
      ? next.operational_filter
      : 'total') as KpiFilter
    page.value = 1
    syncClientChips()
    void nextTick(() => {
      applyingClientsPreset = false
      void load()
    })
  }
})

function applyKpiFilter(key: KpiFilter) {
  // segundo clique no mesmo card limpa (volta ao total)
  kpiFilter.value = kpiFilter.value === key && key !== 'total' ? 'total' : key
  statusFilter.value = 'all'
  syncClientChips()
  page.value = 1
}

/**
 * KPIs de cadastro (contagens reais da API):
 * total · com A1 · sem A1 · a vencer
 * Captura/sync ficam em Documentos e no detalhe do cliente.
 */
const kpiCards = computed(() => [
  {
    key: 'total' as const,
    title: 'Total',
    value: stats.value.total,
    icon: 'i-lucide-users'
  },
  {
    key: 'with_credential' as const,
    title: 'Com A1',
    value: stats.value.with_credential ?? Math.max(0, stats.value.total - stats.value.without_credential),
    icon: 'i-lucide-badge-check'
  },
  {
    key: 'without_credential' as const,
    title: 'Sem A1',
    value: stats.value.without_credential,
    icon: 'i-lucide-shield-off'
  },
  {
    key: 'expiring' as const,
    title: 'A vencer (30d)',
    value: stats.value.credential_expiring_30d,
    icon: 'i-lucide-badge-alert'
  }
])

/**
 * Cadastro de clientes — colunas P0:
 * Razão social / nome · CNPJ/CPF · Certificado · Procuração · Estado · Ações
 * Mobile: nome + certificado + ações. Captura/sync ficam em Documentos.
 */
const columns: TableColumn<Client>[] = [
  {
    accessorKey: 'legal_name',
    header: ({ column }) => sortHeader('Razão social / nome', column),
    enableHiding: false,
    meta: {
      class: {
        th: 'w-[28%] min-w-20 sm:w-[28%] sm:min-w-36',
        td: 'w-[28%] min-w-20 sm:w-[28%] sm:min-w-36'
      }
    }
  },
  {
    id: 'cnpj',
    accessorFn: row => row.cnpj || row.root_cnpj,
    header: ({ column }) => sortHeader('CNPJ/CPF', column),
    meta: {
      class: {
        th: 'hidden sm:table-cell w-[14%] min-w-32',
        td: 'hidden sm:table-cell w-[14%] min-w-32'
      }
    }
  },
  {
    id: 'credential',
    accessorFn: row => row.credential_summary?.valid_to || '',
    header: 'Certificado digital',
    enableSorting: false,
    enableHiding: false,
    meta: {
      class: {
        th: 'w-[28%] min-w-24 sm:w-[18%] sm:min-w-32',
        td: 'w-[28%] min-w-24 sm:w-[18%] sm:min-w-32'
      }
    }
  },
  {
    id: 'procuracao',
    accessorFn: row => row.procuracao_status || '',
    header: 'Procuração',
    enableSorting: false,
    meta: {
      class: {
        th: 'hidden lg:table-cell w-[14%]',
        td: 'hidden lg:table-cell w-[14%]'
      }
    }
  },
  {
    accessorKey: 'is_active',
    header: ({ column }) => sortHeader('Estado', column),
    meta: {
      class: {
        th: 'hidden md:table-cell w-[10%]',
        td: 'hidden md:table-cell w-[10%]'
      }
    }
  },
  {
    id: 'actions',
    header: 'Ações',
    enableHiding: false,
    enableSorting: false,
    meta: {
      class: {
        th: 'w-[16%] min-w-12 sm:w-[10%] sm:min-w-20',
        td: 'w-[16%] min-w-12 sm:w-[10%] sm:min-w-20'
      }
    }
  }
]

type ChipTone = 'success' | 'warning' | 'error' | 'neutral' | 'info'

/**
 * Chip A1: válido / ausente / a vencer / vencido
 */
function credentialInfo(client: Client): {
  chipLabel: string
  color: ChipTone
  hasCredential: boolean
} {
  const summary = client.credential_summary
  if (!summary) {
    return {
      chipLabel: 'Sem A1',
      color: 'neutral',
      hasCredential: false
    }
  }

  const expired = summary.status === 'EXPIRED'
    || !!(summary.valid_to && new Date(summary.valid_to) < new Date())
  const validToLabel = formatDateOnly(summary.valid_to)

  if (expired) {
    return {
      chipLabel: validToLabel !== '—' ? `Vencido ${validToLabel}` : 'Vencido',
      color: 'error',
      hasCredential: true
    }
  }
  if (summary.expires_alert_1 || summary.expires_alert_7 || summary.expires_alert_30) {
    return {
      chipLabel: validToLabel !== '—' ? `A vencer ${validToLabel}` : 'A vencer',
      color: summary.expires_alert_1 ? 'error' : 'warning',
      hasCredential: true
    }
  }
  if (summary.status === 'ACTIVE' || summary.valid_to) {
    return {
      chipLabel: validToLabel !== '—' ? `Válido até ${validToLabel}` : 'Válido',
      color: 'success',
      hasCredential: true
    }
  }
  return {
    chipLabel: statusLabel(summary.status),
    color: 'neutral',
    hasCredential: true
  }
}

function openCredentialModal(client: Client) {
  credentialModalClient.value = client
  credentialModalOpen.value = true
}

/**
 * Menu compacto do A1 (⋮).
 * Uma só ação de edição no modal: Enviar (sem A1) ou Atualizar (com A1).
 */
function credentialActions(client: Client): DropdownMenuItem[][] {
  const hasCredential = !!client.credential_summary
  const items: DropdownMenuItem[] = []

  if (canManageCredentials.value) {
    items.push({
      label: hasCredential ? 'Atualizar' : 'Enviar',
      icon: hasCredential ? 'i-lucide-refresh-cw' : 'i-lucide-upload',
      onSelect: () => openCredentialModal(client)
    })
  }

  items.push(
    {
      label: 'Baixar',
      icon: 'i-lucide-download',
      disabled: !hasCredential,
      onSelect: () => {
        toast.add({
          title: 'Download do PFX indisponível',
          description: 'A API não expõe o arquivo do certificado.',
          color: 'warning'
        })
      }
    },
    {
      label: 'Senha',
      icon: 'i-lucide-key-round',
      disabled: !hasCredential,
      onSelect: () => {
        toast.add({
          title: 'Senha indisponível',
          description: 'A senha do A1 não é recuperável após o upload.',
          color: 'warning'
        })
      }
    },
    {
      label: 'Remover',
      icon: 'i-lucide-trash-2',
      color: 'error',
      disabled: !hasCredential || !canManageCredentials.value,
      onSelect: () => {
        toast.add({
          title: 'Remoção indisponível',
          description: 'Ainda não há endpoint para remover o A1. Use Atualizar.',
          color: 'warning'
        })
      }
    }
  )

  return [items]
}

async function copyCnpj(value?: string | null) {
  const clean = normalizeCnpj(value)
  if (!clean) return
  try {
    await navigator.clipboard.writeText(clean)
    toast.add({
      title: 'CNPJ copiado',
      description: clean,
      color: 'success'
    })
  } catch {
    toast.add({ title: 'Não foi possível copiar o CNPJ', color: 'error' })
  }
}

function formatDateOnly(value?: string | null): string {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return '—'
  return new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short' }).format(date)
}

function openPage(
  client: Client,
  section: 'resumo' | 'cadastro' | 'certificado' | 'sincronizacao' = 'resumo'
) {
  navigateTo(clientSectionPath(client.id, section))
}

function openModal(
  client: Client,
  section: 'resumo' | 'cadastro' | 'certificado' | 'sincronizacao' = 'resumo'
) {
  detailClientId.value = client.id
  detailSection.value = section
  detailOpen.value = true
}

/**
 * Menu da empresa (⋮ da coluna Ações).
 * 👤 / nome → abrir página; ⋮ A1 → certificado.
 * Aqui: ações comuns da empresa.
 */
function rowActions(client: Client): DropdownMenuItem[][] {
  const items: DropdownMenuItem[] = [
    {
      label: 'Preview',
      icon: 'i-lucide-scan-eye',
      onSelect: () => openModal(client, 'resumo')
    }
  ]

  if (canManageClients.value) {
    items.push({
      label: 'Editar',
      icon: 'i-lucide-pencil',
      onSelect: () => openEditForm(client)
    })
    if (client.is_active) {
      items.push({
        label: 'Excluir',
        icon: 'i-lucide-trash-2',
        color: 'error',
        onSelect: () => askDelete(client)
      })
    } else {
      items.push({
        label: 'Reativar',
        icon: 'i-lucide-rotate-ccw',
        onSelect: () => reactivateClient(client)
      })
    }
  }

  return [items]
}

function askDelete(client: Client) {
  deleteClient.value = client
  deleteOpen.value = true
}

async function confirmDelete() {
  if (!deleteClient.value || !canManageClients.value) return
  deleting.value = true
  try {
    await api.clients.update(deleteClient.value.id, {
      is_active: false,
      inactive_reason: 'Inativado pela lista de clientes'
    })
    toast.add({
      title: 'Cliente inativado',
      description: deleteClient.value.legal_name || deleteClient.value.name,
      color: 'success'
    })
    deleteOpen.value = false
    deleteClient.value = null
    await load()
  } catch (caught) {
    toast.add({
      title: apiErrorMessage(caught, 'Não foi possível inativar o cliente.'),
      color: 'error'
    })
  } finally {
    deleting.value = false
  }
}

async function reactivateClient(client: Client) {
  if (!canManageClients.value) return
  try {
    await api.clients.update(client.id, {
      is_active: true,
      inactive_reason: null
    })
    toast.add({
      title: 'Cliente reativado',
      description: client.legal_name || client.name,
      color: 'success'
    })
    await load()
  } catch (caught) {
    toast.add({
      title: apiErrorMessage(caught, 'Não foi possível reativar o cliente.'),
      color: 'error'
    })
  }
}

let loadSequence = 0
let searchTimer: ReturnType<typeof setTimeout> | null = null

/** Carrega somente o recorte solicitado; filtros e ordenação são aplicados pela API. */
async function load() {
  const sequence = ++loadSequence
  const epoch = sessionEpoch.value
  loading.value = true
  loadError.value = null
  try {
    const sort = sorting.value[0]
    const sortId = sort?.id === 'cnpj' || sort?.id === 'is_active'
      ? sort.id
      : 'legal_name'
    const response = await api.clients.list({
      page: page.value,
      per_page: perPage.value,
      q: search.value.trim() || undefined,
      is_active: statusFilter.value === 'all' ? undefined : statusFilter.value === 'active',
      operational_filter: kpiFilter.value === 'total' ? undefined : kpiFilter.value,
      sort: sortId,
      direction: sort?.desc ? 'desc' : 'asc'
    })
    if (sequence !== loadSequence || epoch !== sessionEpoch.value) return
    clients.value = response.data
    total.value = response.meta.total
    lastPage.value = response.meta.last_page
    stats.value = response.meta.stats || {
      ...emptyStats,
      total: response.meta.total
    }
  } catch (caught) {
    if (sequence !== loadSequence || epoch !== sessionEpoch.value) return
    loadError.value = apiErrorMessage(caught, 'Erro ao listar clientes.')
    toast.add({ title: loadError.value, color: 'error' })
  } finally {
    if (sequence === loadSequence && epoch === sessionEpoch.value) loading.value = false
  }
}

function resetTenantScopedList() {
  clients.value = []
  total.value = 0
  lastPage.value = 1
  page.value = 1
  search.value = ''
  kpiFilter.value = 'total'
  statusFilter.value = 'all'
  chipModels.value = []
  stats.value = { ...emptyStats }
  formOpen.value = false
  formClient.value = null
  detailOpen.value = false
  detailClientId.value = null
  loadError.value = null
  clearPresetCache()
  void load()
}

function openCreateForm() {
  formClient.value = null
  formOpen.value = true
}

function openEditForm(client: Client) {
  formClient.value = client
  formOpen.value = true
}

async function onFormSaved(payload: { id: number, mode: 'create' | 'edit', section?: 'resumo' | 'certificado' }) {
  formOpen.value = false
  formClient.value = null
  await load()
  if (payload.mode === 'create') {
    await navigateTo(clientSectionPath(payload.id, payload.section || 'resumo'))
  }
}

/** Navbar / palette / onboarding: "Novo cliente" sempre em modo create. */
watch(clientFormCreateNonce, () => {
  formClient.value = null
  formOpen.value = true
})

/** Fecha modal → limpa cliente residual (evita reabrir em edição). */
watch(formOpen, (open) => {
  if (!open) formClient.value = null
})

watch(search, () => {
  if (applyingClientsPreset) return
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => void load(), 350)
})

/** Mudança de recorte: volta à página 1 sem double-fetch quando já está nela. */
watch([statusFilter, kpiFilter, perPage], () => {
  if (applyingClientsPreset) return
  if (page.value !== 1) {
    page.value = 1
    return
  }
  void load()
})

watch(page, () => {
  if (applyingClientsPreset) return
  void load()
})

watch(sorting, () => {
  if (applyingClientsPreset) return
  if (page.value !== 1) {
    page.value = 1
    return
  }
  void load()
}, { deep: true })

onMounted(() => void load())

watch(sessionEpoch, () => {
  resetTenantScopedList()
})

onBeforeUnmount(() => {
  if (searchTimer) clearTimeout(searchTimer)
})
</script>

<template>
  <!--
    Shell (navbar + submenu Lista/Dashboard) vem de pages/clients.vue
    — mesmo padrão de settings.vue do template.
  -->
  <div class="flex w-full flex-col gap-4 sm:gap-5">
    <!--
          Cópia do HomeStats do template
          (.reference/.../home/HomeStats.vue + frontend HomeStats.vue):
          UPageGrid lg:gap-px, cards colados, leading circular, title uppercase.
          Clique filtra a tabela (em vez de `to` do demo).
        -->
    <UPageGrid
      data-testid="clients-stats"
      class="grid-cols-2 gap-4 sm:gap-6 lg:grid-cols-4 lg:gap-px"
    >
      <UPageCard
        v-for="kpi in kpiCards"
        :key="kpi.key"
        :icon="kpi.icon"
        :title="kpi.title"
        variant="subtle"
        :highlight="kpiFilter === kpi.key"
        highlight-color="primary"
        :ui="{
          container: 'gap-y-1.5',
          wrapper: 'items-start',
          leading: 'p-2.5 rounded-full bg-primary/10 ring ring-inset ring-primary/25 flex-col',
          title: 'font-normal text-muted text-xs uppercase'
        }"
        class="lg:rounded-none first:rounded-l-lg last:rounded-r-lg hover:z-1 cursor-pointer"
        :aria-pressed="kpiFilter === kpi.key"
        :aria-label="`Filtrar lista: ${kpi.title}`"
        role="button"
        tabindex="0"
        @click="applyKpiFilter(kpi.key)"
        @keydown.enter.prevent="applyKpiFilter(kpi.key)"
        @keydown.space.prevent="applyKpiFilter(kpi.key)"
      >
        <div class="flex items-center gap-2">
          <span class="text-2xl font-semibold text-highlighted">
            {{ loading && !clients.length ? '…' : kpi.value }}
          </span>
        </div>
      </UPageCard>
    </UPageGrid>

    <!--
          Toolbar copiada do template customers.vue:
          UInput max-w-sm + filtros/ações à direita.
        -->
    <div class="flex flex-wrap items-center justify-between gap-1.5">
      <UInput
        v-model="filter"
        class="max-w-sm"
        icon="i-lucide-search"
        placeholder="Filtrar por nome ou CNPJ/CPF..."
      />

      <div class="flex flex-wrap items-center gap-1.5">
        <DataTableFilterRoot
          :definitions="clientFilterDefinitions"
          :model-value="chipModels"
          :reset-key="sessionEpoch"
          data-testid="clients-structured-filters"
          @update:model-value="onStructuredFilters"
          @clear="onClearStructuredFilters"
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
        <UDropdownMenu
          :items="
            table?.tableApi
              ?.getAllColumns()
              .filter((column: any) => column.getCanHide())
              .map((column: any) => ({
                label: ({
                  credential: 'Certificado digital',
                  legal_name: 'Razão social / nome',
                  cnpj: 'CNPJ/CPF',
                  is_active: 'Estado',
                  actions: 'Ações'
                } as Record<string, string>)[column.id] || upperFirst(column.id),
                type: 'checkbox' as const,
                checked: column.getIsVisible(),
                onUpdateChecked(checked: boolean) {
                  table?.tableApi?.getColumn(column.id)?.toggleVisibility(!!checked)
                },
                onSelect(e?: Event) {
                  e?.preventDefault()
                }
              }))
          "
          :content="{ align: 'end' }"
        >
          <UButton
            label="Exibir"
            color="neutral"
            variant="outline"
            trailing-icon="i-lucide-settings-2"
          />
        </UDropdownMenu>
        <UButton
          icon="i-lucide-refresh-cw"
          color="neutral"
          variant="ghost"
          square
          aria-label="Atualizar lista"
          :loading="loading"
          @click="load"
        />
      </div>
    </div>

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

    <UAlert
      v-if="loadError"
      color="warning"
      variant="subtle"
      icon="i-lucide-wifi-off"
      :title="loadError"
      :actions="[{ label: 'Tentar novamente', color: 'neutral', variant: 'subtle', onClick: load }]"
    />

    <UTable
      v-if="loading || clients.length"
      ref="table"
      v-model:column-visibility="columnVisibility"
      v-model:sorting="sorting"
      data-testid="data-table"
      class="shrink-0"
      :data="clients"
      :columns="columns"
      :loading="loading"
      :ui="clientsTableUi"
    >
      <template #legal_name-cell="{ row }">
        <div class="min-w-0">
          <button
            type="button"
            class="block w-full truncate text-left font-medium text-highlighted hover:text-primary hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
            :title="row.original.display_name
              ? `${row.original.legal_name || row.original.name} · ${row.original.display_name}`
              : (row.original.legal_name || row.original.name)"
            @click="openPage(row.original)"
          >
            {{ row.original.legal_name || row.original.name }}
          </button>
          <p
            v-if="row.original.display_name"
            class="truncate text-xs text-muted"
          >
            {{ row.original.display_name }}
          </p>
          <!-- CNPJ sob o nome no mobile (coluna CNPJ some em xs) -->
          <p class="mt-0.5 font-mono text-xs text-dimmed sm:hidden">
            {{ formatCnpj(row.original.cnpj || row.original.root_cnpj) }}
          </p>
        </div>
      </template>

      <template #cnpj-cell="{ row }">
        <button
          type="button"
          class="group inline-flex w-full max-w-full items-center gap-1.5 font-mono text-highlighted hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
          :title="`Copiar ${normalizeCnpj(row.original.cnpj || row.original.root_cnpj)}`"
          @click.stop="copyCnpj(row.original.cnpj || row.original.root_cnpj)"
        >
          <span class="min-w-0 truncate">{{ formatCnpj(row.original.cnpj || row.original.root_cnpj) }}</span>
          <UIcon
            name="i-lucide-copy"
            class="size-3.5 shrink-0 opacity-0 transition-opacity group-hover:opacity-70"
            aria-hidden="true"
          />
        </button>
      </template>

      <template #credential-cell="{ row }">
        <div
          v-for="info in [credentialInfo(row.original)]"
          :key="`cred-${row.original.id}-${info.chipLabel}`"
          class="grid w-full min-w-0 grid-cols-[minmax(0,1fr)_2rem] items-center gap-1.5"
        >
          <UBadge
            :color="info.color"
            variant="soft"
            size="md"
            class="h-8 min-w-0 tabular-nums font-normal"
            :ui="{
              base: 'h-8 w-full min-w-0 justify-center rounded-md',
              label: 'truncate text-center'
            }"
            :title="info.chipLabel"
          >
            {{ info.chipLabel }}
          </UBadge>
          <div class="flex size-8 shrink-0 items-center justify-center">
            <UButton
              v-if="!info.hasCredential && canManageCredentials"
              icon="i-lucide-plus"
              color="success"
              variant="soft"
              square
              class="size-8"
              :aria-label="`Enviar certificado de ${row.original.legal_name || row.original.name}`"
              @click.stop="openCredentialModal(row.original)"
            />
            <UDropdownMenu
              v-else
              :content="{ align: 'end' }"
              :items="credentialActions(row.original)"
            >
              <UButton
                icon="i-lucide-ellipsis-vertical"
                color="neutral"
                variant="soft"
                square
                class="size-8"
                :aria-label="`Ações do certificado de ${row.original.legal_name || row.original.name}`"
              />
            </UDropdownMenu>
          </div>
        </div>
      </template>

      <template #procuracao-cell="{ row }">
        <ClientsClientProcuracaoBadge
          :status="row.original.procuracao_status"
          :checked-at="row.original.procuracao_checked_at"
        />
      </template>

      <template #is_active-cell="{ row }">
        <UBadge
          :color="row.original.is_active ? 'success' : 'neutral'"
          variant="soft"
          size="md"
          class="h-8 font-normal"
          :ui="{ base: 'h-8 rounded-md' }"
        >
          {{ row.original.is_active ? 'Ativo' : 'Inativo' }}
        </UBadge>
      </template>

      <template #actions-cell="{ row }">
        <!--
              Perfil e dropdown desacoplados (não UFieldGroup):
              👤 = abrir cliente | ⋮ = menu de ações.
              gap + divisor leve separam as duas intenções.
            -->
        <div class="flex items-center justify-end gap-1.5">
          <UButton
            icon="i-lucide-user-round"
            color="success"
            variant="soft"
            size="sm"
            square
            class="hidden size-8 sm:inline-flex"
            :aria-label="`Abrir ${row.original.legal_name || row.original.name}`"
            @click="openPage(row.original)"
          />
          <span
            class="hidden h-5 w-px shrink-0 bg-accented sm:block"
            aria-hidden="true"
          />
          <UDropdownMenu
            :content="{ align: 'end' }"
            :items="rowActions(row.original)"
          >
            <UButton
              icon="i-lucide-ellipsis-vertical"
              color="neutral"
              variant="soft"
              size="sm"
              square
              class="size-8"
              :aria-label="`Mais ações de ${row.original.legal_name || row.original.name}`"
            />
          </UDropdownMenu>
        </div>
      </template>
    </UTable>

    <UEmpty
      v-if="!loading && !loadError && !clients.length"
      icon="i-lucide-building-2"
      title="Nenhum cliente encontrado"
      description="Cadastre o primeiro cliente para começar."
    >
      <UButton
        v-if="canManageClients"
        label="Cadastrar cliente"
        icon="i-lucide-plus"
        @click="openCreateForm"
      />
    </UEmpty>

    <!-- Footer idêntico ao template -->
    <div class="flex items-center justify-between gap-3 border-t border-default pt-4 mt-auto">
      <div class="text-sm text-muted">
        {{ total }} cliente(s) · página {{ page }} de {{ lastPage }}
      </div>

      <div class="flex flex-wrap items-center gap-1.5">
        <USelect
          v-model="perPage"
          :items="[
            { label: '10 por página', value: 10 },
            { label: '20 por página', value: 20 },
            { label: '50 por página', value: 50 }
          ]"
          value-key="value"
          class="w-36"
          aria-label="Clientes por página"
        />
        <UPagination
          v-model:page="page"
          :items-per-page="perPage"
          :total="total"
        />
      </div>
    </div>

    <ClientsClientFormModal
      v-if="canManageClients"
      v-model:open="formOpen"
      :client="formClient"
      :can-manage-credentials="canManageCredentials"
      :can-manage-clients="canManageClients"
      @saved="onFormSaved"
      @open-existing="(id) => navigateTo(clientSectionPath(id))"
    />

    <ClientsClientDetailModal
      v-model:open="detailOpen"
      :client-id="detailClientId"
      :initial-section="detailSection"
      @updated="load"
    />

    <ClientsClientCredentialModal
      v-model:open="credentialModalOpen"
      :client-id="credentialModalClient?.id ?? null"
      :client-label="credentialModalClient?.legal_name || credentialModalClient?.name"
      :can-manage-credentials="canManageCredentials"
      @saved="load"
    />

    <UModal
      v-model:open="deleteOpen"
      title="Excluir cliente"
      :description="deleteClient
        ? `Inativar ${deleteClient.legal_name || deleteClient.name}? O cadastro permanece no escritório.`
        : undefined"
    >
      <template #body>
        <div class="flex justify-end gap-2">
          <UButton
            label="Cancelar"
            color="neutral"
            variant="subtle"
            :disabled="deleting"
            @click="() => { deleteOpen = false }"
          />
          <UButton
            label="Excluir"
            color="error"
            variant="solid"
            :loading="deleting"
            @click="confirmDelete"
          />
        </div>
      </template>
    </UModal>
  </div>
</template>

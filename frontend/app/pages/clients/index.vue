<script setup lang="ts">
/**
 * Lista admin de clientes — cópia do arquétipo customers.vue do template.
 * Fonte: .reference/nuxt-dashboard-template/app/pages/customers.vue
 * + https://dashboard-template.nuxt.dev/customers
 *
 * Busca/filtro: columnFilters na UTable (local, instantâneo) — sem debounce/API a cada tecla.
 * Paginação: getPaginationRowModel (client-side), como o demo.
 * Dados: carrega a lista completa do escritório uma vez (API); recarrega só no botão/atualizar.
 */
import type { DropdownMenuItem, TableColumn } from '@nuxt/ui'
import { getPaginationRowModel } from '@tanstack/table-core'
import type { Row } from '@tanstack/table-core'
import type { Client, ClientListStats } from '~/types/api'
import { upperFirst } from 'scule'

const UButton = resolveComponent('UButton')

const api = useApi()
const route = useRoute()
const { canManageClients, canManageCredentials } = useDashboard()
const toast = useToast()

const table = useTemplateRef('table')

/** Igual ao template: filtro de coluna na memória */
const columnFilters = ref([{
  id: 'legal_name',
  value: '' as string
}])
const columnVisibility = ref()
const sorting = ref<{ id: string, desc: boolean }[]>([])
const pagination = ref({
  pageIndex: 0,
  pageSize: 10
})

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

const formOpen = ref(false)
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
 * Espelho do `email` do template: get/set no columnFilter da UTable.
 * Sem spinner, sem API — só filtra as linhas já carregadas.
 */
const filter = computed({
  get: (): string => {
    return (table.value?.tableApi?.getColumn('legal_name')?.getFilterValue() as string) || ''
  },
  set: (value: string) => {
    table.value?.tableApi?.getColumn('legal_name')?.setFilterValue(value || undefined)
    table.value?.tableApi?.setPageIndex(0)
  }
})

type KpiFilter = 'total' | 'with_credential' | 'without_credential' | 'expiring' | 'capture_problem'

/** Filtro dos cards KPI (clique = filtra a tabela pelo conteúdo do card). */
const kpiFilter = ref<KpiFilter>('total')
const statusFilter = ref('all')

function isCredentialExpired(client: Client): boolean {
  const summary = client.credential_summary
  if (!summary) return false
  if (summary.status === 'EXPIRED') return true
  if (summary.valid_to && new Date(summary.valid_to) < new Date()) return true
  return false
}

function isCredentialExpiring(client: Client): boolean {
  const summary = client.credential_summary
  if (!summary || isCredentialExpired(client)) return false
  return !!(summary.expires_alert_1 || summary.expires_alert_7 || summary.expires_alert_30)
}

function isCaptureProblem(client: Client): boolean {
  const status = client.sync_summary?.status
  return status === 'BLOCKED' || status === 'ERROR'
}

/** Dados da tabela após o filtro do card (busca/estado da toolbar continuam na UTable). */
const tableData = computed(() => {
  const list = clients.value
  switch (kpiFilter.value) {
    case 'with_credential':
      return list.filter(c => !!c.credential_summary && !isCredentialExpired(c))
    case 'without_credential':
      return list.filter(c => !c.credential_summary)
    case 'expiring':
      return list.filter(c => isCredentialExpiring(c))
    case 'capture_problem':
      return list.filter(c => isCaptureProblem(c))
    default:
      return list
  }
})

function applyKpiFilter(key: KpiFilter) {
  // segundo clique no mesmo card limpa (volta ao total)
  kpiFilter.value = kpiFilter.value === key && key !== 'total' ? 'total' : key
  statusFilter.value = 'all'

  nextTick(() => {
    syncStatusColumnFilter()
    table.value?.tableApi?.setPageIndex(0)
  })
}

function syncStatusColumnFilter() {
  if (!table.value?.tableApi) return
  const statusColumn = table.value.tableApi.getColumn('is_active')
  if (!statusColumn) return
  if (statusFilter.value === 'all') {
    statusColumn.setFilterValue(undefined)
  } else if (statusFilter.value === 'active') {
    statusColumn.setFilterValue(true)
  } else {
    statusColumn.setFilterValue(false)
  }
}

watch(() => statusFilter.value, () => {
  syncStatusColumnFilter()
  table.value?.tableApi?.setPageIndex(0)
})

/**
 * KPIs operacionais (contagens reais da API):
 * total · com A1 · sem A1 · a vencer · captura problemática
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
  },
  {
    key: 'capture_problem' as const,
    title: 'Captura com problema',
    value: stats.value.capture_problem ?? 0,
    icon: 'i-lucide-triangle-alert'
  }
])

function sortHeader(label: string, column: { getIsSorted: () => false | 'asc' | 'desc', toggleSorting: (desc?: boolean) => void }) {
  const isSorted = column.getIsSorted()
  return h(UButton, {
    color: 'neutral',
    variant: 'ghost',
    label,
    icon: isSorted
      ? (isSorted === 'asc' ? 'i-lucide-arrow-up-narrow-wide' : 'i-lucide-arrow-down-wide-narrow')
      : 'i-lucide-arrow-up-down',
    class: '-mx-2.5',
    onClick: () => column.toggleSorting(column.getIsSorted() === 'asc')
  })
}

/** Busca por nome, display_name ou CNPJ (equivalente ao filter de email do template). */
function clientSearchFilter(row: Row<Client>, _columnId: string, filterValue: unknown): boolean {
  const raw = String(filterValue ?? '').trim().toLowerCase()
  if (!raw) return true
  const c = row.original
  const hay = [
    c.legal_name,
    c.display_name,
    c.name,
    c.cnpj,
    c.root_cnpj,
    c.tax_regime
  ].filter(Boolean).join(' ').toLowerCase()
  const digits = raw.replace(/[^a-z0-9]/gi, '')
  if (digits && (c.cnpj || c.root_cnpj || '').toLowerCase().includes(digits)) {
    return true
  }
  return hay.includes(raw)
}

/**
 * Posto de captura — colunas P0:
 * Razão · CNPJ · A1 · Captura · Sync · Ações
 * Mobile: razão + A1 + ações; captura/sync/CNPJ escondidos em xs.
 */
const columns: TableColumn<Client>[] = [
  {
    accessorKey: 'legal_name',
    header: ({ column }) => sortHeader('Razão social', column),
    enableHiding: false,
    filterFn: clientSearchFilter,
    meta: {
      class: {
        th: 'w-[28%] min-w-36',
        td: 'w-[28%] min-w-36'
      }
    }
  },
  {
    id: 'cnpj',
    accessorFn: row => row.cnpj || row.root_cnpj,
    header: ({ column }) => sortHeader('CNPJ', column),
    meta: {
      class: {
        th: 'hidden sm:table-cell w-[14%] min-w-36',
        td: 'hidden sm:table-cell w-[14%] min-w-36'
      }
    }
  },
  {
    id: 'credential',
    accessorFn: row => row.credential_summary?.valid_to || '',
    header: ({ column }) => sortHeader('A1', column),
    enableSorting: true,
    enableHiding: false,
    meta: {
      class: {
        th: 'w-[18%] min-w-36',
        td: 'w-[18%] min-w-36'
      }
    }
  },
  {
    id: 'capture',
    accessorFn: row => row.capture_summary?.status || '',
    header: ({ column }) => sortHeader('Captura', column),
    meta: {
      class: {
        th: 'hidden md:table-cell w-[12%]',
        td: 'hidden md:table-cell w-[12%]'
      }
    }
  },
  {
    id: 'sync',
    accessorFn: row => row.sync_summary?.status || '',
    header: ({ column }) => sortHeader('Sync', column),
    meta: {
      class: {
        th: 'hidden lg:table-cell w-[12%]',
        td: 'hidden lg:table-cell w-[12%]'
      }
    }
  },
  {
    accessorKey: 'is_active',
    header: ({ column }) => sortHeader('Estado', column),
    filterFn: 'equals',
    meta: {
      class: {
        th: 'hidden xl:table-cell w-[8%]',
        td: 'hidden xl:table-cell w-[8%]'
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
        th: 'w-[8%] min-w-20',
        td: 'w-[8%] min-w-20'
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

function captureInfo(client: Client): { chipLabel: string, color: ChipTone } {
  const summary = client.capture_summary
  if (!summary || summary.status === 'NONE') {
    return { chipLabel: 'Sem est.', color: 'neutral' }
  }
  if (summary.status === 'ON') {
    return { chipLabel: 'Captura on', color: 'success' }
  }
  if (summary.status === 'PARTIAL') {
    return { chipLabel: 'Parcial', color: 'warning' }
  }
  return { chipLabel: 'Captura off', color: 'neutral' }
}

function syncInfo(client: Client): { chipLabel: string, color: ChipTone, title?: string } {
  const summary = client.sync_summary
  if (!summary || !summary.has_cursor || summary.status === 'NONE') {
    return { chipLabel: 'Sem cursor', color: 'neutral' }
  }
  const last = summary.last_success_at
    ? `Último sucesso: ${formatDateTime(summary.last_success_at)}`
    : undefined
  switch (summary.status) {
    case 'BLOCKED':
      return { chipLabel: 'Bloqueado', color: 'error', title: last }
    case 'ERROR':
      return { chipLabel: 'Erro', color: 'error', title: last }
    case 'RUNNING':
      return { chipLabel: 'Em execução', color: 'info', title: last }
    case 'WAITING':
      return { chipLabel: 'Na fila', color: 'warning', title: last }
    case 'IDLE':
      return { chipLabel: 'OK', color: 'success', title: last }
    default:
      return { chipLabel: statusLabel(summary.status), color: 'neutral', title: last }
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

  items.push({
    label: 'Sincronizar',
    icon: 'i-lucide-refresh-cw',
    onSelect: () => openPage(client, 'sincronizacao')
  })

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

/** Carrega todos os clientes do escritório (como o useFetch do template com a lista completa). */
async function load() {
  loading.value = true
  loadError.value = null
  try {
    const perPage = 100
    const first = await api.clients.list({ page: 1, per_page: perPage })
    let all = [...first.data]
    const lastPage = first.meta.last_page || 1
    for (let p = 2; p <= lastPage; p++) {
      const pageRes = await api.clients.list({ page: p, per_page: perPage })
      all = all.concat(pageRes.data)
    }
    clients.value = all
    stats.value = first.meta.stats || {
      ...emptyStats,
      total: first.meta.total ?? all.length
    }
    // se stats.total veio sem o filtro completo, alinha ao tamanho real
    if (!first.meta.stats) {
      stats.value.total = all.length
      stats.value.active = all.filter(c => c.is_active).length
    }
  } catch (caught) {
    loadError.value = apiErrorMessage(caught, 'Erro ao listar clientes.')
    toast.add({ title: loadError.value, color: 'error' })
  } finally {
    loading.value = false
  }
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

onMounted(() => {
  if (canManageClients.value && route.query.new === '1') {
    openCreateForm()
  }
  // seed filter da URL (opcional), sem debounce
  if (typeof route.query.q === 'string' && route.query.q) {
    columnFilters.value = [{ id: 'legal_name', value: route.query.q }]
  }
  load()
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
          class="lg:grid-cols-5 gap-4 sm:gap-6 lg:gap-px"
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
            placeholder="Filtrar por nome ou CNPJ..."
          />

          <div class="flex flex-wrap items-center gap-1.5">
            <USelect
              v-model="statusFilter"
              :items="[
                { label: 'Todos', value: 'all' },
                { label: 'Ativos', value: 'active' },
                { label: 'Inativos', value: 'inactive' }
              ]"
              :ui="{ trailingIcon: 'group-data-[state=open]:rotate-180 transition-transform duration-200' }"
              placeholder="Filtrar estado"
              class="min-w-28"
            />
            <UDropdownMenu
              :items="
                table?.tableApi
                  ?.getAllColumns()
                  .filter((column: any) => column.getCanHide())
                  .map((column: any) => ({
                    label: ({
                      credential: 'A1',
                      legal_name: 'Razão social',
                      cnpj: 'CNPJ',
                      capture: 'Captura',
                      sync: 'Sync',
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
            <UButton
              v-if="canManageClients"
              icon="i-lucide-plus"
              label="Novo cliente"
              color="primary"
              @click="openCreateForm"
            />
          </div>
        </div>

        <UAlert
          v-if="loadError"
          color="warning"
          variant="subtle"
          icon="i-lucide-wifi-off"
          :title="clients.length ? 'Falha ao atualizar clientes' : 'Não foi possível carregar clientes'"
          :description="loadError"
          :actions="[{ label: 'Tentar novamente', color: 'neutral', variant: 'subtle', onClick: load }]"
        />

        <UTable
          ref="table"
          data-testid="data-table"
          v-model:column-filters="columnFilters"
          v-model:column-visibility="columnVisibility"
          v-model:sorting="sorting"
          v-model:pagination="pagination"
          :pagination-options="{
            getPaginationRowModel: getPaginationRowModel()
          }"
          class="shrink-0"
          :data="tableData"
          :columns="columns"
          :loading="loading"
          :ui="{
            base: 'table-fixed border-separate border-spacing-0',
            thead: '[&>tr]:bg-elevated/50 [&>tr]:after:content-none',
            tbody: '[&>tr]:last:[&>td]:border-b-0',
            // HubStrom: padding interno menor que o default Nuxt (td p-4) —
            // conteúdo mais próximo da borda, linha mais densa e legível.
            th: 'px-3 py-2 first:rounded-l-lg last:rounded-r-lg border-y border-default first:border-l last:border-r',
            td: 'border-b border-default px-3 py-2',
            separator: 'h-0'
          }"
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
              :key="`cred-${row.original.id}`"
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

          <template #capture-cell="{ row }">
            <UBadge
              v-for="info in [captureInfo(row.original)]"
              :key="`cap-${row.original.id}`"
              :color="info.color"
              variant="soft"
              size="sm"
              class="font-normal"
            >
              {{ info.chipLabel }}
            </UBadge>
          </template>

          <template #sync-cell="{ row }">
            <button
              v-for="info in [syncInfo(row.original)]"
              :key="`sync-${row.original.id}`"
              type="button"
              class="inline-flex focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
              :title="info.title || info.chipLabel"
              @click.stop="openPage(row.original, 'sincronizacao')"
            >
              <UBadge
                :color="info.color"
                variant="soft"
                size="sm"
                class="font-normal"
              >
                {{ info.chipLabel }}
              </UBadge>
            </button>
          </template>

          <template #is_active-cell="{ row }">
            <UBadge
              :color="row.original.is_active ? 'success' : 'neutral'"
              variant="soft"
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
                class="size-8"
                :aria-label="`Abrir ${row.original.legal_name || row.original.name}`"
                @click="openPage(row.original)"
              />
              <span
                class="h-5 w-px shrink-0 bg-accented"
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
            {{ table?.tableApi?.getFilteredRowModel().rows.length || 0 }} cliente(s)
          </div>

          <div class="flex items-center gap-1.5">
            <UPagination
              :default-page="(table?.tableApi?.getState().pagination.pageIndex || 0) + 1"
              :items-per-page="table?.tableApi?.getState().pagination.pageSize"
              :total="table?.tableApi?.getFilteredRowModel().rows.length || 0"
              @update:page="(p: number) => table?.tableApi?.setPageIndex(p - 1)"
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
                @click="deleteOpen = false"
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

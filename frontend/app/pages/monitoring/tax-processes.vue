<script setup lang="ts">
/**
 * Processos fiscais (e-Processo) — lista tenant-scoped via MonitoringModuleTable.
 * Arquétipo customers.vue; isolamento por office da sessão; sem segredos.
 */
import type { TableColumn } from '@nuxt/ui'
import type {
  FiscalTaxProcess,
  MonitoringFilterConfig,
  MonitoringFilterValue
} from '~/types/fiscal-modules'
import { sortHeader } from '~/utils/table-sort'

const UButton = resolveComponent('UButton')
const UBadge = resolveComponent('UBadge')

const api = useApi()
const { canTriggerSync, sessionEpoch } = useDashboard()
const toast = useToast()

const loading = ref(false)
const refreshingClientId = ref<number | null>(null)
const loadError = ref<string | null>(null)
const rows = ref<FiscalTaxProcess[]>([])
const page = ref(1)
const perPage = ref(25)
const lastPage = ref(1)
const total = ref(0)
const status = ref('all')
const clientId = ref<number | null>(null)
const sorting = ref<{ id: string, desc: boolean }[]>([{ id: 'client', desc: false }])
let loadSeq = 0
let filterTransactionDepth = 0

const statusItems = [
  { label: 'Todos', value: 'all' },
  { label: 'Aberto', value: 'OPEN' },
  { label: 'Desconhecido', value: 'UNKNOWN' }
]
const filters = computed<MonitoringFilterValue>(() => normalizeMonitoringFilters({
  status: status.value,
  clientId: clientId.value
}))
const filterConfig: MonitoringFilterConfig = {
  search: false,
  fields: [
    { key: 'status', kind: 'option', label: 'Status', items: statusItems },
    { key: 'clientId', kind: 'client', label: 'Cliente' }
  ]
}

async function applyFilters(nextValue: MonitoringFilterValue) {
  const next = normalizeMonitoringFilters(nextValue)
  if (status.value === next.status && clientId.value === next.clientId) return
  filterTransactionDepth += 1
  try {
    status.value = next.status
    clientId.value = next.clientId
    page.value = 1
    await nextTick()
  } finally {
    filterTransactionDepth -= 1
  }
  await load()
}

function resetFilters() {
  void applyFilters(resetMonitoringFilters())
}

async function load() {
  const seq = ++loadSeq
  const epoch = sessionEpoch.value
  loading.value = true
  loadError.value = null
  try {
    const res = await api.fiscal.taxProcesses.list({
      page: page.value,
      per_page: perPage.value,
      status: status.value === 'all' ? undefined : status.value,
      client_id: clientId.value != null && clientId.value >= 1 ? clientId.value : undefined
    })
    if (seq !== loadSeq || epoch !== sessionEpoch.value) return
    rows.value = res.data || []
    const meta = res.meta
    total.value = meta?.total ?? rows.value.length
    lastPage.value = meta?.last_page ?? 1
    if (typeof meta?.per_page === 'number') perPage.value = meta.per_page
  } catch (caught) {
    if (seq !== loadSeq || epoch !== sessionEpoch.value) return
    rows.value = []
    total.value = 0
    loadError.value = apiErrorMessage(caught, 'Falha ao carregar processos fiscais.')
  } finally {
    if (seq === loadSeq && epoch === sessionEpoch.value) loading.value = false
  }
}

async function setPage(next: number) {
  page.value = Math.max(1, Math.floor(Number(next) || 1))
  await load()
}

async function refreshClient(clientId: number) {
  if (!canTriggerSync.value) return
  refreshingClientId.value = clientId
  try {
    await api.fiscal.taxProcesses.refresh(clientId)
    toast.add({ title: 'Refresh enfileirado', color: 'success' })
  } catch (caught) {
    toast.add({
      title: 'Falha ao enfileirar refresh',
      description: apiErrorMessage(caught, 'Erro desconhecido'),
      color: 'error'
    })
  } finally {
    refreshingClientId.value = null
  }
}

function clientHref(id: number) {
  return `/monitoring/clients/${id}/tax_processes`
}

const columns: TableColumn<FiscalTaxProcess>[] = [
  {
    id: 'client',
    accessorKey: 'client_id',
    header: ({ column }) => sortHeader('Cliente', column),
    enableHiding: false,
    cell: ({ row }) => h(UButton, {
      variant: 'link',
      color: 'primary',
      to: clientHref(row.original.client_id),
      label: String(row.original.client_id)
    })
  },
  {
    accessorKey: 'process_number',
    header: 'Processo',
    enableSorting: false
  },
  {
    accessorKey: 'status',
    header: 'Status',
    enableSorting: false,
    cell: ({ row }) => h(UBadge, {
      color: row.original.status === 'OPEN' ? 'warning' : 'neutral',
      variant: 'subtle',
      label: row.original.status
    })
  },
  {
    id: 'source',
    header: 'Fonte',
    enableSorting: false,
    cell: ({ row }) => row.original.is_simulated ? 'Simulado' : 'SERPRO'
  },
  {
    id: 'refreshed',
    header: 'Atualizado',
    enableSorting: false,
    cell: ({ row }) => row.original.refreshed_at || row.original.observed_at || '—'
  },
  {
    id: 'actions',
    header: 'Ações',
    enableHiding: false,
    enableSorting: false,
    cell: ({ row }) => canTriggerSync.value
      ? h(UButton, {
          'size': 'xs',
          'variant': 'soft',
          'icon': 'i-lucide-refresh-cw',
          'loading': refreshingClientId.value === row.original.client_id,
          'aria-label': `Atualizar processos do cliente ${row.original.client_id}`,
          'onClick': () => refreshClient(row.original.client_id)
        })
      : null
  }
]

watch([status, clientId], () => {
  if (filterTransactionDepth > 0) return
  page.value = 1
  void load()
}, { immediate: true })
watch(sessionEpoch, () => {
  loadSeq += 1
  filterTransactionDepth += 1
  try {
    status.value = 'all'
    clientId.value = null
    rows.value = []
    total.value = 0
    lastPage.value = 1
    page.value = 1
  } finally {
    filterTransactionDepth -= 1
  }
  void load()
})
</script>

<template>
  <MonitoringModuleTable
    title="Processos fiscais"
    panel-id="monitoring-tax-processes"
    surface="monitoring.tax_processes"
    :columns="columns"
    :rows="rows"
    :loading="loading"
    :error="loadError"
    :page="page"
    :last-page="lastPage"
    :total="total"
    :per-page="perPage"
    :filters="filters"
    :filter-config="filterConfig"
    :sorting="sorting"
    :get-row-id="row => `tax-process:${row.id}`"
    :show-kpis="false"
    empty-title="Nenhum processo"
    empty-description="Atualize por cliente."
    :column-labels="{
      process_number: 'Processo',
      status: 'Status',
      source: 'Fonte',
      refreshed: 'Atualizado'
    }"
    @update:page="setPage"
    @update:sorting="sorting = $event"
    @apply-filters="applyFilters"
    @reset-filters="resetFilters"
    @refresh="load"
  >
    <template #nav>
      <MonitoringModuleNav active="tax_processes" />
    </template>
  </MonitoringModuleTable>
</template>

<script setup lang="ts">
/**
 * Cadastro e vínculos (PNR Contador) — lista tenant-scoped.
 * Arquétipo de lista do painel; sem office_id no request; sem segredos.
 */
import type { TableColumn } from '@nuxt/ui'
import type { FiscalRegistrationLink } from '~/types/fiscal-modules'

const UButton = resolveComponent('UButton')
const UBadge = resolveComponent('UBadge')

const api = useApi()
const { canTriggerSync, sessionEpoch } = useDashboard()
const toast = useToast()

const loading = ref(false)
const refreshingClientId = ref<number | null>(null)
const loadError = ref<string | null>(null)
const rows = ref<FiscalRegistrationLink[]>([])
const page = ref(1)
const lastPage = ref(1)
const status = ref('all')
let loadSeq = 0

async function load(reset = false) {
  const seq = ++loadSeq
  const epoch = sessionEpoch.value
  if (reset) {
    page.value = 1
    rows.value = []
    lastPage.value = 1
  }
  loading.value = true
  loadError.value = null
  try {
    const res = await api.fiscal.registrations.list({
      page: page.value,
      per_page: 25,
      status: status.value === 'all' ? undefined : status.value
    })
    if (seq !== loadSeq || epoch !== sessionEpoch.value) return
    const next = res.data || []
    rows.value = reset
      ? next
      : [...new Map([...rows.value, ...next].map(row => [row.id, row])).values()]
    lastPage.value = res.meta?.last_page ?? 1
  } catch (caught) {
    if (seq !== loadSeq || epoch !== sessionEpoch.value) return
    loadError.value = apiErrorMessage(caught, 'Falha ao carregar vínculos.')
  } finally {
    if (seq === loadSeq && epoch === sessionEpoch.value) loading.value = false
  }
}

async function refreshClient(clientId: number) {
  if (!canTriggerSync.value) return
  refreshingClientId.value = clientId
  try {
    await api.fiscal.registrations.refresh(clientId)
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
  return `/monitoring/clients/${id}?tab=registrations`
}

const columns: TableColumn<FiscalRegistrationLink>[] = [
  {
    accessorKey: 'client_id',
    header: 'Cliente',
    cell: ({ row }) => h(UButton, {
      variant: 'link',
      color: 'primary',
      to: clientHref(row.original.client_id),
      label: String(row.original.client_id)
    })
  },
  { accessorKey: 'link_key', header: 'Vínculo' },
  {
    accessorKey: 'status',
    header: 'Status',
    cell: ({ row }) => h(UBadge, {
      color: row.original.status === 'ACTIVE' ? 'success' : 'neutral',
      variant: 'subtle',
      label: row.original.status
    })
  },
  {
    id: 'source',
    header: 'Fonte',
    cell: ({ row }) => row.original.is_simulated ? 'Simulado' : 'SERPRO'
  },
  {
    accessorKey: 'refreshed_at',
    header: 'Atualizado',
    cell: ({ row }) => row.original.refreshed_at || row.original.observed_at || '—'
  },
  {
    id: 'actions',
    header: '',
    cell: ({ row }) => canTriggerSync.value
      ? h(UButton, {
          'size': 'xs',
          'variant': 'soft',
          'icon': 'i-lucide-refresh-cw',
          'loading': refreshingClientId.value === row.original.client_id,
          'aria-label': `Atualizar vínculos do cliente ${row.original.client_id}`,
          'onClick': () => refreshClient(row.original.client_id)
        })
      : null
  }
]

async function loadNext() {
  if (page.value >= lastPage.value || loading.value) return
  page.value++
  await load()
}

watch(status, () => void load(true), { immediate: true })
watch(sessionEpoch, () => void load(true))
</script>

<template>
  <UDashboardPanel id="monitoring-registrations">
    <template #header>
      <UDashboardNavbar title="Cadastro e vínculos">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
      </UDashboardNavbar>
      <UDashboardToolbar>
        <MonitoringModuleNav active="registrations" />
      </UDashboardToolbar>
    </template>

    <template #body>
      <div class="flex flex-col gap-4 p-4 sm:p-6">
        <div class="flex flex-wrap items-center gap-3">
          <USelect
            v-model="status"
            :items="[
              { label: 'Todos', value: 'all' },
              { label: 'Ativo', value: 'ACTIVE' },
              { label: 'Desconhecido', value: 'UNKNOWN' }
            ]"
            class="w-40"
            aria-label="Filtrar vínculos por status"
            data-testid="registrations-status-filter"
          />
          <UButton
            icon="i-lucide-refresh-cw"
            variant="soft"
            :loading="loading"
            label="Recarregar"
            @click="load(true)"
          />
        </div>

        <UAlert
          v-if="loadError"
          color="error"
          variant="subtle"
          :title="loadError"
          data-testid="registrations-error"
        />

        <div class="overflow-x-auto">
          <UTable
            :data="rows"
            :columns="columns"
            :loading="loading"
            :ui="{
              base: 'table-fixed border-separate border-spacing-0',
              thead: '[&>tr]:bg-elevated/50 [&>tr]:after:content-none',
              tbody: '[&>tr]:last:[&>td]:border-b-0',
              th: 'py-2 first:rounded-l-lg last:rounded-r-lg border-y border-default first:border-l last:border-r',
              td: 'border-b border-default',
              separator: 'h-0'
            }"
            data-testid="registrations-table"
          >
            <template #empty>
              <MonitoringTableEmptyState
                title="Nenhum vínculo projetado"
                description="Execute um refresh explícito por cliente para popular a carteira."
              />
            </template>
          </UTable>
        </div>

        <DashboardInfiniteTableLoader
          :loading="loading && rows.length > 0"
          :has-more="page < lastPage"
          @load="loadNext"
        />
      </div>
    </template>
  </UDashboardPanel>
</template>

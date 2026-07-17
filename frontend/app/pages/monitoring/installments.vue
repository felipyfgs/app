<script setup lang="ts">
/**
 * Parcelamentos — modalidades, pedido, saldo, parcelas, próxima, atraso, guia.
 * Detalhe do pedido em slideover navegável. Task 7.4
 */
import type { TableColumn } from '@nuxt/ui'
import type { InstallmentsClientDetail, InstallmentsClientRow } from '~/types/fiscal-modules'
import { sortHeader } from '~/utils/table-sort'

const FiscalStatusBadge = resolveComponent('FiscalStatusBadge')
const FiscalClientCell = resolveComponent('FiscalClientCell')
const UButton = resolveComponent('UButton')

const {
  page,
  perPage,
  total,
  lastPage,
  q,
  situation,
  clientId,
  loading,
  refreshing,
  loadError,
  overviewError,
  rows,
  counters,
  totalClients,
  lastValidAt,
  sorting,
  setPage,
  refresh,
  selectKpi,
  applyFilters
} = useFiscalModulePortfolio('installments')

const api = useApi()
const modalities = ref<Array<Record<string, unknown>>>([])
const modalitiesError = ref<string | null>(null)
/** Cápsula de modalidade: `all` ou código oficial (PARCSN, PARCMEI, …). */
const selectedModality = ref('all')

/** Catálogo oficial Integra-Parcelamento (fallback se a API falhar). */
const CATALOG_MODALITIES = [
  { code: 'PARCSN', label: 'PARCSN' },
  { code: 'PARCSN-ESP', label: 'PARCSN-ESP' },
  { code: 'PERTSN', label: 'PERTSN' },
  { code: 'RELPSN', label: 'RELPSN' },
  { code: 'PARCMEI', label: 'PARCMEI' },
  { code: 'PARCMEI-ESP', label: 'PARCMEI-ESP' },
  { code: 'PERTMEI', label: 'PERTMEI' },
  { code: 'RELPMEI', label: 'RELPMEI' }
] as const

const detailOpen = ref(false)
const detailLoading = ref(false)
const detailError = ref<string | null>(null)
const detailOrder = ref<Record<string, unknown> | null>(null)
const detailParcels = ref<Array<Record<string, unknown>>>([])

function clientHref(id: number) {
  return `/monitoring/clients/${id}`
}

function onClientId(id: number | null) {
  clientId.value = id != null && id > 0 ? String(id) : ''
}

function detailOf(row: InstallmentsClientRow): InstallmentsClientDetail {
  return row.detail || {}
}

function modalityCodeOf(row: InstallmentsClientRow): string {
  return String(detailOf(row).modality || '').trim().toUpperCase()
}

const modalityTabItems = computed(() => {
  const fromApi = modalities.value
    .map((m) => {
      const code = String(m.code || m.name || m.label || m.id || '').trim().toUpperCase()
      if (!code) return null
      return { label: code, value: code }
    })
    .filter((x): x is { label: string, value: string } => Boolean(x))

  const catalog = fromApi.length
    ? fromApi
    : CATALOG_MODALITIES.map(m => ({ label: m.label, value: m.code }))

  return [
    { label: 'Todas', value: 'all' },
    ...catalog
  ]
})

const displayRows = computed(() => {
  const mod = selectedModality.value
  if (!mod || mod === 'all') return rows.value
  return rows.value.filter(row => modalityCodeOf(row) === mod)
})

const displayTotal = computed(() => {
  if (!selectedModality.value || selectedModality.value === 'all') {
    return total.value
  }
  return displayRows.value.length
})

async function loadModalities() {
  try {
    modalities.value = (await api.fiscal.installments.modalities()).data || []
    modalitiesError.value = null
  } catch (caught) {
    modalities.value = []
    modalitiesError.value = apiErrorMessage(caught, 'Falha ao carregar modalidades do catálogo.')
  }
}

async function openOrder(orderId: number) {
  detailOpen.value = true
  detailLoading.value = true
  detailError.value = null
  detailOrder.value = null
  detailParcels.value = []
  try {
    const [orderRes, parcelsRes] = await Promise.allSettled([
      api.fiscal.installments.order(orderId),
      api.fiscal.installments.parcels({ order_id: orderId, per_page: 50 })
    ])
    if (orderRes.status === 'fulfilled') {
      detailOrder.value = orderRes.value.data
    } else {
      detailError.value = apiErrorMessage(orderRes.reason, 'Falha ao carregar pedido.')
    }
    if (parcelsRes.status === 'fulfilled') {
      detailParcels.value = (parcelsRes.value.data as Array<Record<string, unknown>>) || []
    }
  } finally {
    detailLoading.value = false
  }
}

const columns: TableColumn<InstallmentsClientRow>[] = [
  {
    id: 'client',
    header: ({ column }) => sortHeader('Cliente', column),
    enableHiding: false,
    cell: ({ row }) => h(FiscalClientCell, {
      clientId: row.original.client_id,
      name: row.original.name || row.original.display_name,
      legalName: row.original.legal_name,
      cnpjMasked: row.original.cnpj_masked,
      to: clientHref(row.original.client_id)
    })
  },
  {
    id: 'modality',
    header: 'Modalidade',
    enableSorting: false,
    cell: ({ row }) => String(detailOf(row.original).modality || '—')
  },
  {
    id: 'order',
    header: 'Pedido',
    enableSorting: false,
    cell: ({ row }) => {
      const d = detailOf(row.original)
      return String(d.external_order_id || d.order_id || '—')
    }
  },
  {
    id: 'total',
    header: 'Saldo / total',
    enableSorting: false,
    cell: ({ row }) => formatAmountCents(detailOf(row.original).total_amount_cents)
  },
  {
    id: 'parcels',
    header: 'Parcelas',
    enableSorting: false,
    cell: ({ row }) => {
      const d = detailOf(row.original)
      const count = d.parcel_count ?? '—'
      const overdue = d.overdue_parcels ?? 0
      return `${count}${overdue ? ` (${overdue} atraso)` : ''}`
    }
  },
  {
    id: 'next',
    header: 'Próxima parcela',
    enableSorting: false,
    cell: ({ row }) => {
      const d = detailOf(row.original)
      const due = formatDateTime(d.next_parcel_due_at)
      const amt = formatAmountCents(d.next_parcel_amount_cents)
      return `${due} · ${amt}`
    }
  },
  {
    id: 'overdue',
    header: 'Atraso',
    enableSorting: false,
    cell: ({ row }) => {
      const n = detailOf(row.original).overdue_parcels ?? 0
      if (!n) return '—'
      return h(FiscalStatusBadge, { status: 'ATTENTION', showHint: true })
    }
  },
  {
    id: 'guide',
    header: 'Guia',
    enableSorting: false,
    cell: ({ row }) => h(UButton, {
      size: 'xs',
      color: 'neutral',
      variant: 'ghost',
      label: 'Guias',
      to: `/monitoring/clients/${row.original.client_id}/guides`
    })
  },
  {
    id: 'situation',
    header: ({ column }) => sortHeader('Situação', column),
    cell: ({ row }) => h(FiscalStatusBadge, {
      status: String(detailOf(row.original).order_situation || row.original.situation)
    })
  },
  {
    id: 'actions',
    header: 'Ações',
    enableHiding: false,
    enableSorting: false,
    cell: ({ row }) => {
      const orderId = detailOf(row.original).order_id
      const children = [
        h(UButton, {
          size: 'xs',
          color: 'neutral',
          variant: 'ghost',
          label: 'Cliente',
          to: clientHref(row.original.client_id)
        })
      ]
      if (orderId) {
        children.push(h(UButton, {
          size: 'xs',
          color: 'primary',
          variant: 'ghost',
          label: 'Pedido',
          onClick: () => openOrder(Number(orderId))
        }))
      }
      return h('div', { class: 'flex justify-end gap-1' }, children)
    }
  }
]

onMounted(() => {
  void loadModalities()
})
</script>

<template>
  <MonitoringModuleTable
    title="Parcelamentos"
    panel-id="monitoring-installments"
    module-key="installments"
    :columns="columns"
    :rows="displayRows"
    :loading="loading"
    :refreshing="refreshing"
    :error="loadError"
    :page="page"
    :last-page="lastPage"
    :total="displayTotal"
    :per-page="perPage"
    :q="q"
    :situation="situation"
    :client-id="clientId"
    :total-clients="totalClients"
    :counters="counters"
    :last-good-at="lastValidAt"
    :sorting="sorting"
    show-client-picker
    empty-title="Nenhum parcelamento"
    :column-labels="{
      modality: 'Modalidade',
      order: 'Pedido',
      total: 'Saldo / total',
      parcels: 'Parcelas',
      next: 'Próxima parcela',
      overdue: 'Atraso',
      guide: 'Guia'
    }"
    @update:page="setPage"
    @update:q="q = $event"
    @update:situation="situation = $event"
    @update:client-id="onClientId"
    @update:sorting="sorting = $event"
    @apply-filters="applyFilters"
    @reset-filters="applyFilters"
    @refresh="refresh"
    @kpi-select="selectKpi"
  >
    <!-- Modalidades oficiais como cápsulas em largura total (padrão KPI/submódulos) -->
    <template #submodules>
      <div
        class="w-full min-w-0"
        data-testid="installments-modality-tabs"
      >
        <UTabs
          v-model="selectedModality"
          :items="modalityTabItems"
          :content="false"
          size="sm"
          color="primary"
          variant="pill"
          class="w-full"
          :ui="{
            root: 'w-full min-w-0',
            list: 'flex w-full min-w-0 flex-wrap justify-stretch gap-1 border border-default bg-elevated/60 p-1 shadow-xs',
            trigger: 'min-w-0 flex-1 basis-[calc(12.5%-0.25rem)] justify-center px-2 data-[state=active]:text-highlighted sm:basis-0',
            indicator: 'bg-default ring-1 ring-default'
          }"
          aria-label="Filtrar por modalidade do catálogo"
        />
      </div>
    </template>

    <template #utilities>
      <UAlert
        v-if="modalitiesError"
        color="warning"
        icon="i-lucide-triangle-alert"
        :title="modalitiesError"
        description="Exibindo catálogo oficial local (PARCSN…RELPMEI)."
        class="w-full"
      />
      <UAlert
        v-if="overviewError"
        color="warning"
        icon="i-lucide-triangle-alert"
        :title="overviewError"
        class="w-full"
      />
    </template>

    <template #detail>
      <USlideover
        v-model:open="detailOpen"
        title="Detalhe do pedido"
      >
        <template #body>
          <div
            v-if="detailLoading"
            class="py-8 text-sm text-muted"
          >
            Carregando pedido…
          </div>
          <UAlert
            v-else-if="detailError"
            color="error"
            :title="detailError"
          />
          <div
            v-else-if="detailOrder"
            class="flex flex-col gap-4"
          >
            <dl class="grid gap-2 text-sm sm:grid-cols-2">
              <div>
                <dt class="text-muted">
                  Pedido
                </dt>
                <dd class="font-medium">
                  {{ detailOrder.external_order_id || detailOrder.id }}
                </dd>
              </div>
              <div>
                <dt class="text-muted">
                  Situação
                </dt>
                <dd>
                  <FiscalStatusBadge :status="String(detailOrder.situation || detailOrder.status || '')" />
                </dd>
              </div>
              <div>
                <dt class="text-muted">
                  Total
                </dt>
                <dd class="font-medium">
                  {{ formatAmountCents(detailOrder.total_amount_cents as number | null) }}
                </dd>
              </div>
              <div>
                <dt class="text-muted">
                  Parcelas
                </dt>
                <dd class="font-medium">
                  {{ detailOrder.parcel_count ?? detailParcels.length }}
                </dd>
              </div>
            </dl>
            <div>
              <h3 class="mb-2 text-sm font-medium">
                Parcelas
              </h3>
              <div
                v-if="!detailParcels.length"
                class="text-sm text-muted"
              >
                Nenhuma parcela retornada.
              </div>
              <ul
                v-else
                class="divide-y divide-default text-sm"
              >
                <li
                  v-for="p in detailParcels"
                  :key="String(p.id)"
                  class="flex items-center justify-between gap-2 py-2"
                >
                  <span>
                    #{{ p.number || p.parcel_number || p.id }}
                    · {{ formatDateTime(String(p.due_at || '') || null) }}
                    · {{ formatAmountCents(p.amount_cents as number | null) }}
                  </span>
                  <FiscalStatusBadge :status="String(p.status || p.situation || '')" />
                </li>
              </ul>
            </div>
          </div>
        </template>
      </USlideover>
    </template>
  </MonitoringModuleTable>
</template>

<script setup lang="ts">
/**
 * Parcelamentos — modalidades, pedido, saldo, parcelas, próxima, atraso, guia.
 * Detalhe do pedido em slideover navegável. Task 7.4
 */
import type { TableColumn } from '@nuxt/ui'
import type { InstallmentsClientDetail, InstallmentsClientRow, MonitoringFilterConfig } from '~/types/fiscal-modules'
import { sortHeader } from '~/utils/table-sort'

const FiscalStatusBadge = resolveComponent('FiscalStatusBadge')
const FiscalClientCell = resolveComponent('FiscalClientCell')
const FiscalDocumentAction = resolveComponent('FiscalDocumentAction')
const UButton = resolveComponent('UButton')

const {
  page,
  perPage,
  total,
  lastPage,
  filters,
  loading,
  refreshing,
  loadError,
  overviewError,
  rows,
  counters,
  totalClients,
  lastValidAt,
  dataOrigin,
  dataOriginLabel,
  sourceLabel,
  asOf,
  surface,
  allowsDocument,
  sorting,
  setPage,
  refresh,
  applyFilters,
  applyQuickFilters,
  resetFilters
} = useFiscalModulePortfolio('installments')

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

const api = useApi()
const modalities = ref<Array<Record<string, unknown>>>([])
const modalitiesError = ref<string | null>(null)

const modalityFilterItems = computed(() => {
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
    { label: 'Todas as modalidades', value: 'all' },
    ...catalog
  ]
})

const filterConfig = computed<MonitoringFilterConfig>(() => ({
  fields: [
    { key: 'situation', kind: 'option', label: 'Situação' },
    { key: 'clientId', kind: 'client', label: 'Cliente' },
    {
      key: 'modality',
      kind: 'option',
      label: 'Modalidade',
      items: modalityFilterItems.value,
      // Single: UTabs de modalidade são exclusivos (1:1 com o chip).
      multiple: false
    }
  ]
}))

function getRowId(row: InstallmentsClientRow) {
  return `c:${row.client_id}`
}

/**
 * Cápsula de modalidade sincronizada com o filtro server-side (portfolio.modality).
 * Trocar a tab aplica modality no portfolio; chips/presets atualizam a cápsula.
 */
const selectedModality = computed({
  get: () => filters.value.modality || 'all',
  set: (value: string) => {
    const next = value || 'all'
    if ((filters.value.modality || 'all') === next) return
    void applyFilters({ ...filters.value, modality: next })
  }
})

const detailOpen = ref(false)
const detailLoading = ref(false)
const detailError = ref<string | null>(null)
const detailOrder = ref<Record<string, unknown> | null>(null)
const detailParcels = ref<Array<Record<string, unknown>>>([])
const detailRow = ref<InstallmentsClientRow | null>(null)

/**
 * O endpoint do pedido já retorna as parcelas persistidas. A consulta paralela
 * preserva o contrato paginado e serve como fallback para projeções antigas.
 * Ambas são leituras locais: abrir o detalhe nunca dispara uma consulta SERPRO.
 */
const orderParcels = computed(() => {
  const embedded = detailOrder.value?.parcels
  return Array.isArray(embedded) && embedded.length > 0
    ? embedded as Array<Record<string, unknown>>
    : detailParcels.value
})

const detailHasOverdueParcels = computed(() => orderParcels.value.some((parcel) => {
  const status = String(parcel.status || parcel.situation || '').toUpperCase()
  return status.includes('ATRAS') || status.includes('OVERDUE')
}))

function parcelLabel(parcel: Record<string, unknown>) {
  return String(parcel.parcel_number || parcel.number || parcel.id || '—')
}

function parcelStatus(parcel: Record<string, unknown>) {
  return String(parcel.status || parcel.situation || 'PENDING')
}

function parcelPaymentStatus(parcel: Record<string, unknown>) {
  const value = String(parcel.payment_status || '').trim()
  return value || null
}

function detailOf(row: InstallmentsClientRow): InstallmentsClientDetail {
  return row.detail || {}
}

const modalityTabItems = computed(() => {
  return modalityFilterItems.value.map(item => ({
    label: item.value === 'all' ? 'Todas' : item.label,
    value: item.value
  }))
})

/** Lista já filtrada server-side por modality — sem refiltro local. */
const displayRows = computed(() => rows.value)
const displayTotal = computed(() => total.value)

async function loadModalities() {
  try {
    modalities.value = (await api.fiscal.installments.modalities()).data || []
    modalitiesError.value = null
  } catch (caught) {
    modalities.value = []
    modalitiesError.value = apiErrorMessage(caught, 'Falha ao carregar modalidades do catálogo.')
  }
}

async function openOrder(orderId: number, row?: InstallmentsClientRow) {
  detailOpen.value = true
  detailLoading.value = true
  detailError.value = null
  detailOrder.value = null
  detailParcels.value = []
  detailRow.value = row ?? null
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
      cnpjMasked: row.original.cnpj_masked
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
      return h(FiscalStatusBadge, { fill: true, status: 'ATTENTION' })
    }
  },
  {
    id: 'guide',
    header: 'Guia',
    enableSorting: false,
    cell: ({ row }) => {
      const orderId = detailOf(row.original).order_id
      return h(UButton, {
        size: 'xs',
        color: 'neutral',
        variant: 'ghost',
        label: 'Ver no pedido',
        disabled: !orderId,
        onClick: () => orderId && openOrder(Number(orderId), row.original)
      })
    }
  },
  {
    id: 'situation',
    header: ({ column }) => sortHeader('Situação', column),
    cell: ({ row }) => h(FiscalStatusBadge, { fill: true, status: String(detailOf(row.original).order_situation || row.original.situation) })
  },
  {
    id: 'actions',
    header: 'Ações',
    enableHiding: false,
    enableSorting: false,
    cell: ({ row }) => {
      const orderId = detailOf(row.original).order_id
      const children = [
        h(FiscalDocumentAction, {
          document: row.original.document,
          disabled: !allowsDocument.value
        })
      ]
      if (orderId) {
        children.push(h(UButton, {
          size: 'xs',
          color: 'primary',
          variant: 'ghost',
          label: 'Pedido',
          onClick: () => openOrder(Number(orderId), row.original)
        }))
      }
      return h('div', { class: 'flex justify-end gap-1 items-center' }, children)
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
    :filters="filters"
    :filter-config="filterConfig"
    :total-clients="totalClients"
    :counters="counters"
    :last-good-at="lastValidAt"
    :data-origin="dataOrigin"
    :data-origin-label="dataOriginLabel"
    :source-label="sourceLabel"
    :as-of="asOf"
    :surface-summary="surface"
    :sorting="sorting"
    :get-row-id="getRowId"
    :get-client-id="row => row.client_id"
    :horizontal-scroll="true"
    table-class="min-w-[960px]"
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
    @update:sorting="sorting = $event"
    @quick-filter-change="applyQuickFilters"
    @apply-filters="applyFilters"
    @reset-filters="resetFilters"
    @refresh="refresh"
  >
    <!-- Modalidades oficiais como cápsulas em largura total (padrão KPI/submódulos) -->
    <template #submodules>
      <ShellScrollableTabs
        v-model="selectedModality"
        :items="modalityTabItems"
        size="sm"
        class="w-full min-w-0"
        aria-label="Filtrar por modalidade do catálogo"
        test-id="installments-modality-tabs"
      />
    </template>

    <template #utilities>
      <UAlert
        v-if="modalitiesError"
        color="warning"
        icon="i-lucide-triangle-alert"
        :title="modalitiesError || 'Catálogo local (PARCSN…RELPMEI)'"
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
            <div
              v-if="detailRow?.document"
              class="flex flex-wrap items-center gap-2"
            >
              <FiscalDocumentAction
                :document="detailRow.document"
                :disabled="!allowsDocument"
              />
            </div>
            <UAlert
              v-if="detailHasOverdueParcels"
              color="warning"
              icon="i-lucide-circle-alert"
              title="Há parcelas em atraso neste pedido"
              data-testid="installments-detail-overdue-alert"
            />

            <UPageCard
              title="Resumo do pedido"
              variant="subtle"
              data-testid="installments-order-summary"
            >
              <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                <div>
                  <dt class="text-muted">
                    Pedido
                  </dt>
                  <dd class="mt-1 font-medium text-highlighted">
                    {{ detailOrder.external_order_id || detailOrder.id || '—' }}
                  </dd>
                </div>
                <div>
                  <dt class="text-muted">
                    Situação
                  </dt>
                  <dd class="mt-1">
                    <FiscalStatusBadge :status="String(detailOrder.situation || detailOrder.status || '')" />
                  </dd>
                </div>
                <div>
                  <dt class="text-muted">
                    Modalidade
                  </dt>
                  <dd class="mt-1 font-medium text-highlighted">
                    {{ detailOrder.modality || '—' }}
                  </dd>
                </div>
                <div>
                  <dt class="text-muted">
                    Regime
                  </dt>
                  <dd class="mt-1 font-medium text-highlighted">
                    {{ detailOrder.regime || '—' }}
                  </dd>
                </div>
                <div>
                  <dt class="text-muted">
                    Valor consolidado
                  </dt>
                  <dd class="mt-1 font-medium text-highlighted">
                    {{ formatAmountCents(detailOrder.total_amount_cents as number | null) }}
                  </dd>
                </div>
                <div>
                  <dt class="text-muted">
                    Quantidade de parcelas
                  </dt>
                  <dd class="mt-1 font-medium text-highlighted">
                    {{ detailOrder.parcel_count ?? orderParcels.length }}
                  </dd>
                </div>
                <div>
                  <dt class="text-muted">
                    Solicitado em
                  </dt>
                  <dd class="mt-1 text-highlighted">
                    {{ formatDateTime(detailOrder.requested_at as string | null) }}
                  </dd>
                </div>
                <div>
                  <dt class="text-muted">
                    Consolidado em
                  </dt>
                  <dd class="mt-1 text-highlighted">
                    {{ formatDateTime(detailOrder.consolidated_at as string | null) }}
                  </dd>
                </div>
              </dl>
            </UPageCard>

            <UPageCard
              title="Parcelas e pagamentos"
              description="Histórico local do pedido. A abertura deste detalhe não realiza uma consulta fiscal."
              variant="subtle"
              data-testid="installments-order-parcels"
            >
              <div
                v-if="!orderParcels.length"
                class="flex flex-col items-center gap-2 py-6 text-center"
                data-testid="installments-order-parcels-empty"
              >
                <UIcon name="i-lucide-calendar-x" class="size-7 text-dimmed" />
                <p class="text-sm font-medium text-highlighted">
                  Nenhuma parcela disponível
                </p>
                <p class="text-sm text-muted">
                  O pedido foi importado sem parcelas associadas.
                </p>
              </div>
              <div
                v-else
                class="divide-y divide-default"
              >
                <article
                  v-for="parcel in orderParcels"
                  :key="String(parcel.id || parcel.parcel_key || parcelLabel(parcel))"
                  class="grid gap-3 py-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center"
                >
                  <div class="min-w-0">
                    <p class="font-medium text-highlighted">
                      Parcela {{ parcelLabel(parcel) }}
                    </p>
                    <p class="mt-1 text-sm text-muted">
                      Vencimento {{ formatDateTime(parcel.due_at as string | null) }}
                      <span v-if="parcel.paid_at"> · Pago em {{ formatDateTime(parcel.paid_at as string | null) }}</span>
                    </p>
                  </div>
                  <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                    <span class="font-medium text-highlighted">
                      {{ formatAmountCents(parcel.amount_cents as number | null) }}
                    </span>
                    <FiscalStatusBadge :status="parcelStatus(parcel)" />
                    <FiscalStatusBadge
                      v-if="parcelPaymentStatus(parcel)"
                      :status="parcelPaymentStatus(parcel) || ''"
                    />
                    <UBadge
                      v-if="parcel.document_available"
                      color="success"
                      variant="subtle"
                      icon="i-lucide-file-check-2"
                      label="Guia disponível"
                    />
                  </div>
                </article>
              </div>
            </UPageCard>
          </div>
        </template>
      </USlideover>
    </template>
  </MonitoringModuleTable>
</template>

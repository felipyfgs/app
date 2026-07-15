<script setup lang="ts">
/**
 * Parcelamentos — modalidades, pedido, saldo, parcelas, próxima, atraso, guia.
 * Detalhe do pedido em slideover navegável. Task 7.4
 */
import type { TableColumn } from '@nuxt/ui'
import type { InstallmentsClientDetail, InstallmentsClientRow } from '~/types/fiscal-modules'

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
  dataOrigin,
  isSynthetic,
  lastValidAt,
  refresh,
  selectKpi
} = useFiscalModulePortfolio('installments')

const api = useApi()
const modalities = ref<Array<Record<string, unknown>>>([])
const modalitiesError = ref<string | null>(null)

const detailOpen = ref(false)
const detailLoading = ref(false)
const detailError = ref<string | null>(null)
const detailOrder = ref<Record<string, unknown> | null>(null)
const detailParcels = ref<Array<Record<string, unknown>>>([])

function clientHref(id: number) {
  return `/monitoring/clients/${id}?tab=overview`
}

function onClientId(id: number | null) {
  clientId.value = id != null && id > 0 ? String(id) : ''
}

function detailOf(row: InstallmentsClientRow): InstallmentsClientDetail {
  return row.detail || {}
}

async function loadModalities() {
  try {
    modalities.value = (await api.fiscal.installments.modalities()).data || []
    modalitiesError.value = null
  } catch (caught) {
    modalities.value = []
    modalitiesError.value = apiErrorMessage(caught, 'Falha ao carregar modalidades.')
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
    header: 'Cliente',
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
    cell: ({ row }) => String(detailOf(row.original).modality || '—')
  },
  {
    id: 'order',
    header: 'Pedido',
    cell: ({ row }) => {
      const d = detailOf(row.original)
      return String(d.external_order_id || d.order_id || '—')
    }
  },
  {
    id: 'total',
    header: 'Saldo / total',
    cell: ({ row }) => formatAmountCents(detailOf(row.original).total_amount_cents)
  },
  {
    id: 'parcels',
    header: 'Parcelas',
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
    cell: ({ row }) => {
      const n = detailOf(row.original).overdue_parcels ?? 0
      if (!n) return '—'
      return h(FiscalStatusBadge, { status: 'ATTENTION', showHint: true })
    }
  },
  {
    id: 'guide',
    header: 'Guia',
    cell: ({ row }) => h(UButton, {
      size: 'xs',
      color: 'neutral',
      variant: 'ghost',
      label: 'Guias',
      to: `/monitoring/clients/${row.original.client_id}?tab=guides`
    })
  },
  {
    id: 'situation',
    header: 'Situação',
    cell: ({ row }) => h(FiscalStatusBadge, {
      status: String(detailOf(row.original).order_situation || row.original.situation)
    })
  },
  {
    id: 'actions',
    header: '',
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
  <FiscalModuleTable
    title="Parcelamentos"
    panel-id="monitoring-installments"
    description="Modalidades, pedidos, saldo, parcelas, atrasos e guias relacionadas — detalhe navegável por pedido."
    :columns="columns"
    :rows="rows"
    :loading="loading"
    :refreshing="refreshing"
    :error="loadError"
    :page="page"
    :last-page="lastPage"
    :total="total"
    :per-page="perPage"
    :q="q"
    :situation="situation"
    :total-clients="totalClients"
    :counters="counters"
    :data-origin="dataOrigin"
    :is-synthetic="isSynthetic"
    :last-good-at="lastValidAt"
    show-client-picker
    empty-title="Nenhum parcelamento na carteira"
    empty-description="A API do read model não retornou clientes com parcelamentos. Nada foi inventado."
    @update:page="page = $event"
    @update:q="q = $event"
    @update:situation="situation = $event"
    @update:client-id="onClientId"
    @refresh="refresh"
    @kpi-select="selectKpi"
  >
    <template #navbar-actions>
      <FiscalMonitoringPortfolioActions
        module-key="installments"
        :client-id="clientId"
        :situation="situation"
        :q="q"
        @refreshed="refresh"
      />
    </template>

    <template #utilities>
      <UAlert
        v-if="modalitiesError"
        color="warning"
        icon="i-lucide-triangle-alert"
        :title="modalitiesError"
        class="w-full"
      />
      <UPageCard
        v-else-if="modalities.length"
        variant="subtle"
        title="Modalidades do catálogo"
        class="w-full"
      >
        <div class="flex flex-wrap gap-2">
          <UBadge
            v-for="(m, i) in modalities"
            :key="i"
            color="neutral"
            variant="subtle"
          >
            {{ m.code || m.name || m.label || m.id || `Modalidade ${i + 1}` }}
          </UBadge>
        </div>
      </UPageCard>
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
  </FiscalModuleTable>
</template>

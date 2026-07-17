<script setup lang="ts">
/**
 * Guias (7.9) — amount_cents, payment_status e emissão independentes;
 * detalhe + download com token efêmero; FiscalClientPicker; demo quando origem DEMO.
 */
import type { TableColumn } from '@nuxt/ui'
import type { FiscalModuleFilterFormValue, FiscalModuleOverview } from '~/types/fiscal-modules'
import { resolveGuideEmissionCodes } from '~/utils/fiscal-high-risk'
import { sortHeader } from '~/utils/table-sort'

const FiscalStatusBadge = resolveComponent('FiscalStatusBadge')
const UButton = resolveComponent('UButton')

/** payment_status oficiais (TaxGuidePaymentStatus) — independentes de emissão. */
const PAYMENT_STATUS_ITEMS: Array<{ label: string, value: string }> = [
  { label: 'Todos os pagamentos', value: 'all' },
  { label: 'Desconhecido', value: 'UNKNOWN' },
  { label: 'Sem confirmação', value: 'NOT_CONFIRMED' },
  { label: 'Pago (oficial)', value: 'CONFIRMED' },
  { label: 'Parcial', value: 'PARTIAL' }
]

const api = useApi()
const { sessionEpoch, canAccessAdministration } = useDashboard()
const toast = useToast()
const {
  page, perPage, total, lastPage, clientId, competence, q,
  loading, loadError, applyPaginator, syncUrl, resetPage
} = useServerPage()

const paymentStatus = ref('all')

const rows = ref<Record<string, unknown>[]>([])
const overview = ref<FiscalModuleOverview | null>(null)
const overviewError = ref<string | null>(null)

const detailOpen = ref(false)
const detail = ref<Record<string, unknown> | null>(null)
const detailId = ref<number | null>(null)
const detailLoading = ref(false)
const detailError = ref<string | null>(null)
const downloading = ref(false)

const mutationOpen = ref(false)
const mutationRequest = ref<{
  client_id: number
  solution_code: string
  service_code: string
  operation_code: string
  competence_period_key?: string | null
  module?: string
} | null>(null)

const clientIdModel = computed<number | null>({
  get: () => {
    const n = Number(clientId.value)
    return Number.isFinite(n) && n > 0 ? n : null
  },
  set: (v) => {
    clientId.value = v && v > 0 ? String(v) : ''
  }
})

function versionOf(row: Record<string, unknown>): Record<string, unknown> | null {
  const v = row.current_version
  return v && typeof v === 'object' ? (v as Record<string, unknown>) : null
}

function emissionOf(row: Record<string, unknown>): string {
  const v = versionOf(row)
  return String(v?.emission_status || row.emission_status || '')
}

let overviewSeq = 0
let listSeq = 0

function stillCurrent(seq: number, kind: 'overview' | 'list', epoch: number) {
  if (epoch !== sessionEpoch.value) return false
  return kind === 'overview' ? seq === overviewSeq : seq === listSeq
}

async function loadOverview() {
  const seq = ++overviewSeq
  const epoch = sessionEpoch.value
  try {
    const data = (await api.fiscal.modules.overview('guides', {
      q: q.value.trim() || undefined,
      competence: competence.value || undefined,
      // overview usa situation de carteira; payment_status vai só na lista de guias
      situation: undefined
    })).data
    if (!stillCurrent(seq, 'overview', epoch)) return
    overview.value = data
    overviewError.value = null
  } catch (caught) {
    if (!stillCurrent(seq, 'overview', epoch)) return
    overviewError.value = apiErrorMessage(caught, 'Falha no overview de guias.')
  }
}

async function load() {
  const seq = ++listSeq
  const epoch = sessionEpoch.value
  loading.value = true
  loadError.value = null
  try {
    await syncUrl()
    if (!stillCurrent(seq, 'list', epoch)) return
    const res = await api.fiscal.guides.list({
      page: page.value,
      per_page: perPage.value,
      client_id: clientId.value ? Number(clientId.value) : undefined,
      competence: competence.value || undefined,
      payment_status: paymentStatus.value !== 'all' ? paymentStatus.value : undefined
    }) as Record<string, unknown>
    if (!stillCurrent(seq, 'list', epoch)) return
    rows.value = (res.data as Record<string, unknown>[]) || []
    applyPaginator(res)
    if (res.total == null && !(res.meta as { total?: number } | undefined)?.total) {
      total.value = rows.value.length
      lastPage.value = 1
    }
  } catch (caught) {
    if (!stillCurrent(seq, 'list', epoch)) return
    rows.value = []
    total.value = 0
    loadError.value = apiErrorMessage(caught, 'Falha ao carregar guias.')
  } finally {
    if (stillCurrent(seq, 'list', epoch)) loading.value = false
  }
}

async function openDetail(id: number) {
  detailOpen.value = true
  detailId.value = id
  detailLoading.value = true
  detailError.value = null
  detail.value = null
  try {
    detail.value = (await api.fiscal.guides.get(id)).data
  } catch (caught) {
    detailError.value = apiErrorMessage(caught, 'Falha ao carregar guia.')
  } finally {
    detailLoading.value = false
  }
}

async function downloadGuide(id: number) {
  downloading.value = true
  try {
    const tokenRes = await api.fiscal.guides.issueDownloadToken(id)
    const url = api.fiscal.guides.downloadUrl(tokenRes.data.token)
    window.open(url, '_blank', 'noopener')
  } catch (caught) {
    toast.add({
      title: apiErrorMessage(caught, 'Falha ao emitir token de download.'),
      color: 'error'
    })
  } finally {
    downloading.value = false
  }
}

const sorting = ref<{ id: string, desc: boolean }[]>([{ id: 'id', desc: true }])

const columns: TableColumn<Record<string, unknown>>[] = [
  {
    id: 'id',
    accessorKey: 'id',
    header: ({ column }) => sortHeader('ID', column),
    meta: { class: { th: 'w-16', td: 'w-16' } }
  },
  {
    id: 'client',
    header: ({ column }) => sortHeader('Cliente', column),
    cell: ({ row }) => {
      const cid = row.original.client_id as number | undefined
      if (!cid) return '—'
      return h(UButton, {
        size: 'xs',
        color: 'neutral',
        variant: 'link',
        label: `#${cid}`,
        to: `/monitoring/clients/${cid}/guides`
      })
    }
  },
  {
    id: 'system',
    header: 'Sistema / tipo',
    enableSorting: false,
    cell: ({ row }) =>
      [row.original.system_code, row.original.service_code].filter(Boolean).join(' / ') || '—'
  },
  {
    id: 'competence',
    header: ({ column }) => sortHeader('Competência', column),
    cell: ({ row }) => String(row.original.competence_period_key || row.original.period_key || '—')
  },
  {
    id: 'amount',
    header: 'Valor',
    enableSorting: false,
    cell: ({ row }) => formatAmountCents(row.original.amount_cents as number | null)
  },
  {
    id: 'due',
    header: 'Vencimento',
    enableSorting: false,
    cell: ({ row }) => formatDateTime(String(row.original.due_at || '') || null)
  },
  {
    id: 'emission',
    header: 'Emissão',
    enableSorting: false,
    cell: ({ row }) => {
      const status = emissionOf(row.original)
      return status
        ? h(FiscalStatusBadge, { status })
        : '—'
    }
  },
  {
    id: 'payment',
    header: 'Pagamento',
    enableSorting: false,
    cell: ({ row }) => h(FiscalStatusBadge, {
      status: String(row.original.payment_status || 'UNKNOWN')
    })
  },
  {
    id: 'validity',
    header: 'Validade',
    enableSorting: false,
    cell: ({ row }) => {
      const v = versionOf(row.original)
      return formatDateTime(String(v?.valid_until || '') || null)
    }
  },
  {
    id: 'version',
    header: 'Versão',
    enableSorting: false,
    cell: ({ row }) => {
      const v = versionOf(row.original)
      return String(
        v?.version_number
        ?? row.original.current_version_id
        ?? v?.id
        ?? '—'
      )
    }
  },
  {
    id: 'actions',
    header: 'Ações',
    enableHiding: false,
    enableSorting: false,
    cell: ({ row }) => {
      const id = Number(row.original.id)
      const children = [
        h(UButton, {
          size: 'xs',
          color: 'neutral',
          variant: 'ghost',
          label: 'Detalhe',
          onClick: () => openDetail(id)
        }),
        h(UButton, {
          size: 'xs',
          color: 'neutral',
          variant: 'ghost',
          label: 'Download',
          onClick: () => downloadGuide(id)
        })
      ]
      const emissionCodes = canAccessAdministration.value
        ? resolveGuideEmissionCodes(row.original)
        : null
      if (emissionCodes) {
        children.push(h(UButton, {
          size: 'xs',
          color: 'warning',
          variant: 'ghost',
          label: 'Emitir',
          onClick: () => {
            mutationRequest.value = {
              client_id: Number(row.original.client_id),
              solution_code: emissionCodes.solution_code,
              service_code: emissionCodes.service_code,
              operation_code: emissionCodes.operation_code,
              competence_period_key: String(row.original.competence_period_key || '') || null,
              module: emissionCodes.module || 'guides'
            }
            mutationOpen.value = true
          }
        }))
      }
      return h('div', { class: 'flex justify-end gap-1' }, children)
    }
  }
]

function setPage(next: number) {
  page.value = Math.max(1, Math.floor(Number(next) || 1))
}

function onClientId(id: number | null) {
  clientIdModel.value = id
}

function applyModuleFilters(filters: FiscalModuleFilterFormValue) {
  q.value = filters.q
  competence.value = filters.competence
  clientIdModel.value = filters.clientId
}

function resetModuleFilters(filters: FiscalModuleFilterFormValue) {
  paymentStatus.value = 'all'
  applyModuleFilters(filters)
}

/** Métricas opcionais do overview (campo extra pode existir no payload). */
const unpaidAmountCents = computed(() => {
  const metrics = overview.value?.metrics as Record<string, unknown> | undefined
  const raw = metrics?.unpaid_amount_cents
  return typeof raw === 'number' ? raw : null
})

function refreshAll() {
  void load()
  void loadOverview()
}

watch(page, () => {
  void load()
})
watch([paymentStatus, clientId, competence, q], () => {
  resetPage()
  void load()
  void loadOverview()
})
watch(sessionEpoch, () => {
  rows.value = []
  overview.value = null
  detail.value = null
  detailOpen.value = false
  mutationOpen.value = false
  mutationRequest.value = null
  void load()
  void loadOverview()
})
onMounted(() => {
  void load()
  void loadOverview()
})
</script>

<template>
  <MonitoringModuleTable
    title="Guias"
    panel-id="monitoring-guides"
    module-key="guides"
    :columns="columns"
    :rows="rows"
    :loading="loading"
    :error="loadError"
    :page="page"
    :last-page="lastPage"
    :total="total"
    :per-page="perPage"
    :q="q"
    :competence="competence"
    :client-id="clientId"
    :sorting="sorting"
    :total-clients="overview?.total_clients"
    :counters="overview?.counters"
    show-competence-filter
    show-client-picker
    :show-situation-filter="false"
    empty-title="Nenhuma guia"
    :column-labels="{
      system: 'Sistema / tipo',
      amount: 'Valor',
      due: 'Vencimento',
      emission: 'Emissão',
      payment: 'Pagamento',
      validity: 'Validade',
      version: 'Versão'
    }"
    @update:page="setPage"
    @update:q="q = $event"
    @update:competence="competence = $event"
    @update:client-id="onClientId"
    @update:sorting="sorting = $event"
    @apply-filters="applyModuleFilters"
    @reset-filters="resetModuleFilters"
    @refresh="refreshAll"
  >
    <template #nav>
      <MonitoringModuleNav active="guides" />
    </template>

    <template #toolbar-filters>
      <USelect
        v-model="paymentStatus"
        :items="PAYMENT_STATUS_ITEMS"
        value-key="value"
        class="w-full sm:min-w-48 sm:w-auto"
        aria-label="Filtrar por payment_status"
        data-testid="guides-payment-status-filter"
      />
    </template>

    <template #utilities>
      <UAlert
        v-if="overviewError"
        color="warning"
        icon="i-lucide-triangle-alert"
        :title="overviewError"
        class="w-full"
      >
        <template #actions>
          <UButton
            size="xs"
            color="neutral"
            variant="outline"
            label="Retry overview"
            @click="loadOverview"
          />
        </template>
      </UAlert>
      <p
        v-if="unpaidAmountCents != null"
        class="text-sm text-muted"
      >
        Em aberto (métrica do overview):
        <span class="font-medium text-highlighted">
          {{ formatAmountCents(unpaidAmountCents) }}
        </span>
      </p>
    </template>

    <template #detail>
      <USlideover
        v-model:open="detailOpen"
        title="Detalhe da guia"
      >
        <template #body>
          <div
            v-if="detailLoading"
            class="py-8 text-sm text-muted"
          >
            Carregando…
          </div>
          <UAlert
            v-else-if="detailError"
            color="error"
            :title="detailError"
          >
            <template #actions>
              <UButton
                v-if="detailId"
                size="xs"
                color="neutral"
                variant="outline"
                label="Tentar de novo"
                @click="openDetail(detailId)"
              />
            </template>
          </UAlert>
          <div
            v-else-if="detail"
            class="flex flex-col gap-4 text-sm"
          >
            <dl class="grid gap-2 sm:grid-cols-2">
              <div>
                <dt class="text-muted">
                  Valor
                </dt>
                <dd class="font-medium">
                  {{ formatAmountCents(detail.amount_cents as number | null) }}
                </dd>
              </div>
              <div>
                <dt class="text-muted">
                  Pagamento
                </dt>
                <dd>
                  <FiscalStatusBadge :status="String(detail.payment_status || '')" />
                </dd>
              </div>
              <div>
                <dt class="text-muted">
                  Emissão
                </dt>
                <dd>
                  <FiscalStatusBadge
                    v-if="emissionOf(detail)"
                    :status="emissionOf(detail)"
                  />
                  <span v-else>—</span>
                </dd>
              </div>
              <div>
                <dt class="text-muted">
                  Competência
                </dt>
                <dd>{{ detail.competence_period_key || '—' }}</dd>
              </div>
              <div>
                <dt class="text-muted">
                  Vencimento
                </dt>
                <dd>{{ formatDateTime(String(detail.due_at || '') || null) }}</dd>
              </div>
              <div>
                <dt class="text-muted">
                  Validade do documento
                </dt>
                <dd>
                  {{ formatDateTime(String(versionOf(detail)?.valid_until || '') || null) }}
                </dd>
              </div>
              <div>
                <dt class="text-muted">
                  Sistema
                </dt>
                <dd>{{ detail.system_code }} / {{ detail.service_code }}</dd>
              </div>
              <div>
                <dt class="text-muted">
                  Versão atual
                </dt>
                <dd>
                  {{ versionOf(detail)?.version_number ?? detail.current_version_id ?? '—' }}
                </dd>
              </div>
              <div v-if="detail.identifier_code">
                <dt class="text-muted">
                  Identificador
                </dt>
                <dd>{{ detail.identifier_code }}</dd>
              </div>
              <div v-if="detail.payment_source">
                <dt class="text-muted">
                  Fonte do pagamento
                </dt>
                <dd>{{ detail.payment_source }}</dd>
              </div>
            </dl>
            <UButton
              icon="i-lucide-download"
              label="Download protegido"
              :loading="downloading"
              @click="downloadGuide(Number(detail.id))"
            />
            <p class="text-xs text-muted">
              O download obtém um token efêmero; o caminho do cofre nunca é exposto.
            </p>
          </div>
        </template>
      </USlideover>

      <FiscalMutationConfirmModal
        v-model:open="mutationOpen"
        :request="mutationRequest"
        :context="{
          effect: 'Emitir guia (efeito financeiro/fiscal)',
          competence: mutationRequest?.competence_period_key || undefined
        }"
        @success="refreshAll"
      />
    </template>
  </MonitoringModuleTable>
</template>

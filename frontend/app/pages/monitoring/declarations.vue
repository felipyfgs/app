<script setup lang="ts">
/**
 * Declarações — overview/KPIs reais + carteira (obrigação, aplicabilidade,
 * competência, vencimento, entrega, evidência). Task 7.8
 */
import type { TableColumn } from '@nuxt/ui'
import type { DeclarationsClientDetail, DeclarationsClientRow } from '~/types/fiscal-modules'

const FiscalStatusBadge = resolveComponent('FiscalStatusBadge')
const FiscalClientCell = resolveComponent('FiscalClientCell')
const UButton = resolveComponent('UButton')
const UBadge = resolveComponent('UBadge')

const api = useApi()

const {
  page,
  perPage,
  total,
  lastPage,
  q,
  situation,
  competence,
  deliveryStatus,
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
} = useFiscalModulePortfolio('declarations')

/** Resumo real da API (por obrigação × aplicabilidade × entrega). */
interface DeclarationSummaryRow {
  obligation_definition_id?: number
  obligation_code?: string | null
  obligation_name?: string | null
  module_key?: string | null
  applicability?: string | null
  delivery_status?: string | null
  total?: number
}

const summary = ref<DeclarationSummaryRow[]>([])
const summaryError = ref<string | null>(null)
const summaryLoading = ref(false)

/** Enriquecimento por projeção (applicability / evidência) — resource backend. */
const projectionCache = ref<Record<number, Record<string, unknown>>>({})

const detailOpen = ref(false)
const detailLoading = ref(false)
const detailError = ref<string | null>(null)
const detailProjection = ref<Record<string, unknown> | null>(null)
const detailEvidences = ref<Array<Record<string, unknown>>>([])

const deliveryStatusItems = [
  { label: 'Todas as entregas', value: 'all' },
  { label: 'Desconhecido', value: 'UNKNOWN' },
  { label: 'Pendente', value: 'PENDING' },
  { label: 'Processando', value: 'PROCESSING' },
  { label: 'Em atenção', value: 'ATTENTION' },
  { label: 'Em dia', value: 'UP_TO_DATE' },
  { label: 'Erro', value: 'ERROR' },
  { label: 'Bloqueado', value: 'BLOCKED' },
  { label: 'Não aplicável', value: 'NOT_APPLICABLE' },
  { label: 'Não suportado', value: 'UNSUPPORTED' }
]

function clientHref(id: number) {
  return `/monitoring/clients/${id}?tab=declarations`
}

function onClientId(id: number | null) {
  clientId.value = id != null && id > 0 ? String(id) : ''
}

function detailOf(row: DeclarationsClientRow): DeclarationsClientDetail {
  return row.detail || {}
}

function projectionOf(row: DeclarationsClientRow): Record<string, unknown> | null {
  const id = detailOf(row).next_projection_id
  if (!id) return null
  return projectionCache.value[id] || null
}

function applicabilityLabel(code?: string | null) {
  const map: Record<string, string> = {
    APPLICABLE: 'Aplicável',
    NOT_APPLICABLE: 'Não aplicável',
    UNKNOWN: 'Desconhecida',
    UNSUPPORTED: 'Não suportada'
  }
  const k = String(code || '').toUpperCase()
  return map[k] || (k || '—')
}

function evidenceLabel(proj: Record<string, unknown> | null) {
  if (!proj) return '—'
  if (proj.conclusive_evidence_id || proj.evidence_artifact_id) {
    return 'Com evidência'
  }
  if (Array.isArray(proj.evidences) && proj.evidences.length) {
    return `${proj.evidences.length} evidência(s)`
  }
  return 'Sem evidência'
}

async function loadSummary() {
  summaryLoading.value = true
  try {
    const res = await api.fiscal.declarations.summary()
    const raw = res.data as unknown
    if (Array.isArray(raw)) {
      summary.value = raw as DeclarationSummaryRow[]
    } else if (raw && typeof raw === 'object') {
      // Contrato antigo/objeto — não stringify; extrai listas conhecidas ou vazio.
      const maybe = (raw as { items?: unknown, data?: unknown })
      const list = Array.isArray(maybe.items)
        ? maybe.items
        : Array.isArray(maybe.data)
          ? maybe.data
          : []
      summary.value = list as DeclarationSummaryRow[]
    } else {
      summary.value = []
    }
    summaryError.value = null
  } catch (caught) {
    summary.value = []
    summaryError.value = apiErrorMessage(caught, 'Falha ao carregar resumo de declarações.')
  } finally {
    summaryLoading.value = false
  }
}

async function enrichProjections() {
  const ids = rows.value
    .map(r => detailOf(r).next_projection_id)
    .filter((id): id is number => typeof id === 'number' && id > 0)
  const missing = ids.filter(id => !projectionCache.value[id])
  if (!missing.length) return

  const settled = await Promise.allSettled(
    missing.map(id => api.fiscal.declarations.get(id))
  )
  const next = { ...projectionCache.value }
  settled.forEach((result, i) => {
    const id = missing[i]
    if (id == null) return
    if (result.status === 'fulfilled' && result.value?.data) {
      next[id] = result.value.data as Record<string, unknown>
    }
  })
  projectionCache.value = next
}

async function openProjection(row: DeclarationsClientRow) {
  const id = detailOf(row).next_projection_id
  if (!id) return
  detailOpen.value = true
  detailLoading.value = true
  detailError.value = null
  detailProjection.value = null
  detailEvidences.value = []
  try {
    const res = await api.fiscal.declarations.get(id)
    const data = (res.data || {}) as Record<string, unknown>
    detailProjection.value = data
    projectionCache.value = { ...projectionCache.value, [id]: data }
    detailEvidences.value = Array.isArray(data.evidences)
      ? (data.evidences as Array<Record<string, unknown>>)
      : []
  } catch (caught) {
    detailError.value = apiErrorMessage(caught, 'Falha ao carregar projeção de declaração.')
  } finally {
    detailLoading.value = false
  }
}

const columns: TableColumn<DeclarationsClientRow>[] = [
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
    id: 'obligation',
    header: 'Obrigação',
    cell: ({ row }) => {
      const d = detailOf(row.original)
      const proj = projectionOf(row.original)
      return String(
        d.next_obligation_code
        || proj?.obligation_code
        || proj?.obligation_name
        || '—'
      )
    }
  },
  {
    id: 'applicability',
    header: 'Aplicabilidade',
    cell: ({ row }) => {
      const proj = projectionOf(row.original)
      const code = proj?.applicability != null ? String(proj.applicability) : null
      if (!code) return '—'
      return h(UBadge, { color: 'neutral', variant: 'subtle', size: 'sm' }, () => applicabilityLabel(code))
    }
  },
  {
    id: 'competence',
    header: 'Competência',
    cell: ({ row }) => String(
      row.original.competence
      || detailOf(row.original).next_period_key
      || projectionOf(row.original)?.period_key
      || '—'
    )
  },
  {
    id: 'due',
    header: 'Vencimento',
    cell: ({ row }) => formatDateTime(
      String(
        detailOf(row.original).next_due_at
        || row.original.next_deadline_at
        || projectionOf(row.original)?.due_at
        || ''
      ) || null
    )
  },
  {
    id: 'delivery',
    header: 'Entrega',
    cell: ({ row }) => {
      const status = String(
        detailOf(row.original).next_delivery_status
        || projectionOf(row.original)?.delivery_status
        || '—'
      )
      return status === '—' ? '—' : h(FiscalStatusBadge, { status, showHint: true })
    }
  },
  {
    id: 'evidence',
    header: 'Evidência',
    cell: ({ row }) => {
      const proj = projectionOf(row.original)
      if (!proj) {
        return detailOf(row.original).next_projection_id ? '…' : '—'
      }
      const label = evidenceLabel(proj)
      if (label === 'Com evidência' || label.endsWith('evidência(s)')) {
        return h(UBadge, { color: 'success', variant: 'subtle', size: 'sm' }, () => label)
      }
      return label
    }
  },
  {
    id: 'open',
    header: 'Abertas',
    cell: ({ row }) => String(detailOf(row.original).open_count ?? '—')
  },
  {
    id: 'situation',
    header: 'Situação',
    cell: ({ row }) => h(FiscalStatusBadge, {
      status: String(
        detailOf(row.original).next_situation
        || row.original.situation
      )
    })
  },
  {
    id: 'actions',
    header: '',
    meta: { class: { th: 'w-40', td: 'w-40' } },
    cell: ({ row }) => {
      const children = [
        h(UButton, {
          size: 'xs',
          color: 'neutral',
          variant: 'ghost',
          label: 'Cliente',
          to: clientHref(row.original.client_id)
        })
      ]
      if (detailOf(row.original).next_projection_id) {
        children.unshift(h(UButton, {
          size: 'xs',
          color: 'primary',
          variant: 'ghost',
          label: 'Projeção',
          onClick: () => openProjection(row.original)
        }))
      }
      return h('div', { class: 'flex justify-end gap-1' }, children)
    }
  }
]

watch(rows, () => {
  void enrichProjections()
}, { deep: false })

watch(lastValidAt, () => {
  void loadSummary()
})

onMounted(() => {
  void loadSummary()
})
</script>

<template>
  <FiscalModuleTable
    title="Declarações"
    panel-id="monitoring-declarations"
    description="Carteira de obrigações com aplicabilidade, competência, vencimento, entrega e evidência — sem inventar dados."
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
    :competence="competence"
    :delivery-status="deliveryStatus"
    :total-clients="totalClients"
    :counters="counters"
    :data-origin="dataOrigin"
    :is-synthetic="isSynthetic"
    :last-good-at="lastValidAt"
    show-competence-filter
    show-delivery-status-filter
    show-client-picker
    :delivery-status-items="deliveryStatusItems"
    empty-title="Nenhuma declaração na carteira"
    empty-description="A API do read model não retornou linhas. Nada foi inventado."
    @update:page="page = $event"
    @update:q="q = $event"
    @update:situation="situation = $event"
    @update:competence="competence = $event"
    @update:delivery-status="deliveryStatus = $event"
    @update:client-id="onClientId"
    @refresh="() => { refresh(); loadSummary() }"
    @kpi-select="selectKpi"
  >
    <template #navbar-actions>
      <FiscalMonitoringPortfolioActions
        module-key="declarations"
        :client-id="clientId"
        :competence="competence"
        :situation="situation"
        :q="q"
        @refreshed="() => { refresh(); loadSummary() }"
      />
    </template>

    <template #utilities>
      <UAlert
        v-if="summaryError"
        color="warning"
        icon="i-lucide-triangle-alert"
        :title="summaryError"
        class="w-full"
      />
      <UPageCard
        v-else-if="summaryLoading && !summary.length"
        variant="subtle"
        title="Resumo por obrigação"
        class="w-full"
      >
        <p class="text-sm text-muted">
          Carregando resumo…
        </p>
      </UPageCard>
      <UPageCard
        v-else-if="summary.length"
        variant="subtle"
        title="Resumo real (API)"
        description="Totais por obrigação, aplicabilidade e status de entrega."
        class="w-full"
      >
        <div class="overflow-x-auto">
          <table class="w-full min-w-[28rem] text-left text-sm">
            <thead class="text-xs text-muted">
              <tr class="border-b border-default">
                <th class="py-2 pe-3 font-medium">
                  Obrigação
                </th>
                <th class="py-2 pe-3 font-medium">
                  Aplicabilidade
                </th>
                <th class="py-2 pe-3 font-medium">
                  Entrega
                </th>
                <th class="py-2 font-medium text-end">
                  Total
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(row, idx) in summary"
                :key="`${row.obligation_definition_id}-${row.applicability}-${row.delivery_status}-${idx}`"
                class="border-b border-default/60 last:border-0"
              >
                <td class="py-2 pe-3">
                  <span class="font-medium text-highlighted">
                    {{ row.obligation_code || row.obligation_name || '—' }}
                  </span>
                  <span
                    v-if="row.obligation_name && row.obligation_code"
                    class="mt-0.5 block text-xs text-muted"
                  >
                    {{ row.obligation_name }}
                  </span>
                </td>
                <td class="py-2 pe-3">
                  {{ applicabilityLabel(row.applicability) }}
                </td>
                <td class="py-2 pe-3">
                  <FiscalStatusBadge
                    v-if="row.delivery_status"
                    :status="String(row.delivery_status)"
                    show-hint
                  />
                  <span v-else>—</span>
                </td>
                <td class="py-2 text-end tabular-nums font-medium">
                  {{ row.total ?? '—' }}
                </td>
              </tr>
            </tbody>
          </table>
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
        title="Projeção de declaração"
      >
        <template #body>
          <div
            v-if="detailLoading"
            class="py-8 text-sm text-muted"
          >
            Carregando projeção…
          </div>
          <UAlert
            v-else-if="detailError"
            color="error"
            :title="detailError"
          />
          <div
            v-else-if="detailProjection"
            class="flex flex-col gap-4"
          >
            <dl class="grid gap-2 text-sm sm:grid-cols-2">
              <div>
                <dt class="text-muted">
                  Obrigação
                </dt>
                <dd class="font-medium">
                  {{ detailProjection.obligation_code || detailProjection.obligation_name || '—' }}
                </dd>
              </div>
              <div>
                <dt class="text-muted">
                  Aplicabilidade
                </dt>
                <dd>
                  <UBadge
                    color="neutral"
                    variant="subtle"
                    size="sm"
                  >
                    {{ applicabilityLabel(String(detailProjection.applicability || '')) }}
                  </UBadge>
                </dd>
              </div>
              <div>
                <dt class="text-muted">
                  Competência
                </dt>
                <dd class="font-medium">
                  {{ detailProjection.period_key || '—' }}
                </dd>
              </div>
              <div>
                <dt class="text-muted">
                  Vencimento
                </dt>
                <dd class="font-medium">
                  {{ formatDateTime(String(detailProjection.due_at || '') || null) }}
                </dd>
              </div>
              <div>
                <dt class="text-muted">
                  Entrega
                </dt>
                <dd>
                  <FiscalStatusBadge
                    v-if="detailProjection.delivery_status"
                    :status="String(detailProjection.delivery_status)"
                    show-hint
                  />
                  <span v-else>—</span>
                </dd>
              </div>
              <div>
                <dt class="text-muted">
                  Situação
                </dt>
                <dd>
                  <FiscalStatusBadge
                    v-if="detailProjection.situation"
                    :status="String(detailProjection.situation)"
                  />
                  <span v-else>—</span>
                </dd>
              </div>
              <div>
                <dt class="text-muted">
                  Evidência conclusiva
                </dt>
                <dd class="font-medium">
                  {{ detailProjection.conclusive_evidence_id ? `#${detailProjection.conclusive_evidence_id}` : '—' }}
                </dd>
              </div>
              <div>
                <dt class="text-muted">
                  Artefato
                </dt>
                <dd class="font-medium">
                  {{ detailProjection.evidence_artifact_id ? `#${detailProjection.evidence_artifact_id}` : '—' }}
                </dd>
              </div>
            </dl>
            <p
              v-if="detailProjection.applicability_basis"
              class="text-xs text-muted"
            >
              Base: {{ detailProjection.applicability_basis }}
            </p>
            <div>
              <h3 class="mb-2 text-sm font-medium">
                Evidências
              </h3>
              <div
                v-if="!detailEvidences.length"
                class="text-sm text-muted"
              >
                Nenhuma evidência anexada retornada.
              </div>
              <ul
                v-else
                class="divide-y divide-default text-sm"
              >
                <li
                  v-for="ev in detailEvidences"
                  :key="String(ev.id)"
                  class="flex items-center justify-between gap-2 py-2"
                >
                  <span>
                    #{{ ev.id }}
                    · {{ ev.kind || ev.source || 'evidência' }}
                    · {{ formatDateTime(String(ev.observed_at || ev.created_at || '') || null) }}
                  </span>
                  <FiscalStatusBadge
                    v-if="ev.status || ev.situation"
                    :status="String(ev.status || ev.situation)"
                  />
                </li>
              </ul>
            </div>
            <UButton
              v-if="detailProjection.client_id"
              size="sm"
              color="neutral"
              variant="outline"
              label="Painel do cliente"
              :to="clientHref(Number(detailProjection.client_id))"
            />
          </div>
        </template>
      </USlideover>
    </template>
  </FiscalModuleTable>
</template>

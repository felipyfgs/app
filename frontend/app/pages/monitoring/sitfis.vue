<script setup lang="ts">
/**
 * Situação Fiscal (SITFIS) — carteira + idade/TTL + contagem de achados.
 * USlideover com findings/pendências normalizadas (sem JSON bruto).
 * Task 7.5 · deep-links /monitoring/clients/{id}?tab=sitfis
 */
import type { TableColumn } from '@nuxt/ui'
import type { FiscalFinding, FiscalPendingItem } from '~/types/api'
import type { SitfisClientDetail, SitfisClientRow } from '~/types/fiscal-modules'

const FiscalStatusBadge = resolveComponent('FiscalStatusBadge')
const FiscalClientCell = resolveComponent('FiscalClientCell')
const FiscalCoverageBadge = resolveComponent('FiscalCoverageBadge')
const UButton = resolveComponent('UButton')
const UBadge = resolveComponent('UBadge')

const api = useApi()
const { canTriggerSync } = useDashboard()
const toast = useToast()

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
} = useFiscalModulePortfolio('sitfis')

const slideOpen = ref(false)
const selected = ref<SitfisClientRow | null>(null)
const findings = ref<FiscalFinding[]>([])
const pending = ref<FiscalPendingItem[]>([])
const sitfisMeta = ref<{
  observed_at?: string | null
  age_seconds?: number | null
  ttl_seconds?: number | null
  is_within_ttl?: boolean
  expires_at?: string | null
  next_refresh_at?: string | null
  can_refresh?: boolean
  block_reason?: string | null
  source_provenance?: string | null
  verification_state?: string | null
  disclaimer?: string | null
  coverage?: string | null
} | null>(null)

function provenanceLabel(value?: string | null) {
  if (value === 'SERPRO_REAL') return 'Fonte SERPRO real'
  if (value === 'SIMULATED') return 'Simulado (desenvolvimento)'
  if (value === 'UNVERIFIED') return 'Não verificado (legado)'
  return null
}
const detailLoading = ref(false)
const detailError = ref<string | null>(null)
const detailRefreshing = ref(false)

function clientHref(id: number) {
  return `/monitoring/clients/${id}?tab=sitfis`
}

function onClientId(id: number | null) {
  clientId.value = id != null && id > 0 ? String(id) : ''
}

function detailOf(row: SitfisClientRow): SitfisClientDetail {
  return row.detail || {}
}

function ageLabel(seconds?: number | null) {
  if (seconds == null || !Number.isFinite(Number(seconds))) return '—'
  const s = Number(seconds)
  if (s < 60) return `${s}s`
  if (s < 3600) return `${Math.floor(s / 60)} min`
  if (s < 86400) return `${Math.floor(s / 3600)} h`
  return `${Math.floor(s / 86400)} d`
}

async function openDetail(row: SitfisClientRow) {
  selected.value = row
  slideOpen.value = true
  detailLoading.value = true
  detailError.value = null
  findings.value = []
  pending.value = []
  sitfisMeta.value = null
  try {
    const [findRes, pendRes, sitRes] = await Promise.allSettled([
      api.fiscal.findings({ client_id: row.client_id, per_page: 50, active_only: true }),
      api.fiscal.pending({ client_id: row.client_id, per_page: 50, status: 'OPEN' }),
      api.fiscal.sitfis.show(row.client_id)
    ])
    if (findRes.status === 'fulfilled') {
      findings.value = ((findRes.value as { data: FiscalFinding[] }).data) || []
    }
    if (pendRes.status === 'fulfilled') {
      pending.value = ((pendRes.value as { data: FiscalPendingItem[] }).data) || []
    }
    if (sitRes.status === 'fulfilled') {
      const view = sitRes.value.data || {}
      const d = detailOf(row)
      sitfisMeta.value = {
        observed_at: (view.observed_at as string | null)
          || (view.snapshot as { observed_at?: string } | undefined)?.observed_at
          || d.observed_at
          || null,
        age_seconds: (view.age_seconds as number | null) ?? d.age_seconds ?? null,
        ttl_seconds: (view.ttl_seconds as number | null) ?? d.ttl_seconds ?? null,
        is_within_ttl: view.is_within_ttl as boolean | undefined,
        expires_at: view.expires_at as string | null | undefined,
        next_refresh_at: view.next_refresh_at as string | null | undefined,
        can_refresh: view.can_refresh as boolean | undefined,
        block_reason: view.block_reason as string | null | undefined,
        source_provenance: (view.source_provenance as string | null | undefined)
          || (view.snapshot as { source_provenance?: string } | undefined)?.source_provenance
          || null,
        verification_state: (view.verification_state as string | null | undefined)
          || (view.snapshot as { verification_state?: string } | undefined)?.verification_state
          || null,
        disclaimer: view.disclaimer as string | null | undefined,
        coverage: String(
          (view.snapshot as { coverage?: string } | undefined)?.coverage
          || row.coverage
          || ''
        ) || null
      }
    }
    if (
      findRes.status === 'rejected'
      && pendRes.status === 'rejected'
      && sitRes.status === 'rejected'
    ) {
      detailError.value = 'Falha ao carregar detalhe SITFIS.'
    }
  } finally {
    detailLoading.value = false
  }
}

async function refreshSelected() {
  if (!selected.value || !canTriggerSync.value) return
  detailRefreshing.value = true
  try {
    await api.fiscal.sitfis.refresh({
      client_id: selected.value.client_id
    })
    toast.add({ title: 'Atualização SITFIS solicitada', color: 'success' })
    await openDetail(selected.value)
    await refresh()
  } catch (caught) {
    toast.add({
      title: apiErrorMessage(caught, 'Falha ao solicitar refresh SITFIS.'),
      color: 'error'
    })
  } finally {
    detailRefreshing.value = false
  }
}

const columns: TableColumn<SitfisClientRow>[] = [
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
    id: 'situation',
    header: 'Situação',
    cell: ({ row }) => h(FiscalStatusBadge, { status: row.original.situation, showHint: true })
  },
  {
    id: 'age',
    header: 'Idade / TTL',
    cell: ({ row }) => {
      const d = detailOf(row.original)
      const age = ageLabel(d.age_seconds)
      const ttl = d.ttl_seconds != null ? ageLabel(d.ttl_seconds) : '—'
      return h('span', { class: 'inline-flex items-center gap-1.5 text-sm' }, [
        age,
        h('span', { class: 'text-muted' }, `/ ${ttl}`),
        d.is_expired === true
          ? h(UBadge, { color: 'warning', variant: 'subtle', size: 'sm' }, () => 'Expirado')
          : null
      ])
    }
  },
  {
    id: 'findings',
    header: 'Achados',
    cell: ({ row }) => {
      const d = detailOf(row.original)
      return `${d.findings_count ?? 0} finding(s) · ${d.pending_count ?? 0} pend.`
    }
  },
  {
    id: 'coverage',
    header: 'Cobertura',
    cell: ({ row }) => h(FiscalCoverageBadge, { coverage: row.original.coverage })
  },
  {
    id: 'observed',
    header: 'Observado',
    cell: ({ row }) => formatDateTime(
      String(detailOf(row.original).observed_at || row.original.last_consulted_at || '') || null
    )
  },
  {
    id: 'actions',
    header: '',
    meta: { class: { th: 'w-40', td: 'w-40' } },
    cell: ({ row }) => h('div', { class: 'flex justify-end gap-1' }, [
      h(UButton, {
        size: 'xs',
        color: 'primary',
        variant: 'ghost',
        label: 'Achados',
        onClick: () => openDetail(row.original)
      }),
      h(UButton, {
        size: 'xs',
        color: 'neutral',
        variant: 'ghost',
        label: 'Cliente',
        to: clientHref(row.original.client_id)
      })
    ])
  }
]
</script>

<template>
  <FiscalModuleTable
    title="Situação Fiscal"
    panel-id="monitoring-sitfis"
    description="Snapshot com idade/TTL e achados normalizados. Abrir a carteira não dispara consulta nova."
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
    empty-title="Nenhum cliente com SITFIS na carteira"
    empty-description="A API do read model não retornou linhas. Nada foi inventado."
    @update:page="page = $event"
    @update:q="q = $event"
    @update:situation="situation = $event"
    @update:client-id="onClientId"
    @refresh="refresh"
    @kpi-select="selectKpi"
  >
    <template #navbar-actions>
      <FiscalMonitoringPortfolioActions
        module-key="sitfis"
        :client-id="clientId"
        :situation="situation"
        :q="q"
        @refreshed="refresh"
      />
    </template>

    <template #utilities>
      <UAlert
        color="info"
        icon="i-lucide-info"
        title="Snapshot com idade"
        description="Use «Solicitar atualização» no detalhe apenas se o TTL permitir. Achados e pendências são listados de forma normalizada — sem JSON bruto."
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
        v-model:open="slideOpen"
        :title="selected
          ? (selected.name || selected.legal_name || `Cliente #${selected.client_id}`)
          : 'Detalhe SITFIS'"
      >
        <template #body>
          <div
            v-if="detailLoading"
            class="py-8 text-sm text-muted"
          >
            Carregando achados…
          </div>
          <UAlert
            v-else-if="detailError"
            color="error"
            :title="detailError"
          />
          <div
            v-else
            class="flex flex-col gap-4"
          >
            <div class="flex flex-wrap items-center gap-2">
              <FiscalStatusBadge
                v-if="selected"
                :status="selected.situation"
                show-hint
              />
              <span class="text-sm text-muted">
                Idade: {{ ageLabel(sitfisMeta?.age_seconds ?? (selected ? detailOf(selected).age_seconds : null)) }}
                · TTL: {{ ageLabel(sitfisMeta?.ttl_seconds ?? (selected ? detailOf(selected).ttl_seconds : null)) }}
              </span>
              <UBadge
                v-if="provenanceLabel(sitfisMeta?.source_provenance)"
                :color="sitfisMeta?.source_provenance === 'SERPRO_REAL' ? 'success' : 'warning'"
                variant="subtle"
                size="sm"
              >
                {{ provenanceLabel(sitfisMeta?.source_provenance) }}
              </UBadge>
              <UBadge
                v-if="sitfisMeta?.block_reason === 'RUN_IN_PROGRESS'"
                color="info"
                variant="subtle"
                size="sm"
              >
                Atualização em processamento
              </UBadge>
              <UButton
                v-if="canTriggerSync && selected && sitfisMeta?.can_refresh !== false"
                size="xs"
                color="neutral"
                variant="outline"
                icon="i-lucide-refresh-cw"
                label="Solicitar atualização"
                :loading="detailRefreshing"
                @click="refreshSelected()"
              />
              <span
                v-else-if="sitfisMeta?.block_reason === 'WITHIN_TTL' && sitfisMeta?.next_refresh_at"
                class="text-xs text-muted"
              >
                Dados recentes · próxima atualização a partir de {{ formatDateTime(sitfisMeta.next_refresh_at) }}
              </span>
              <UButton
                v-if="selected"
                size="xs"
                color="neutral"
                variant="ghost"
                label="Painel do cliente"
                :to="clientHref(selected.client_id)"
              />
            </div>

            <UPageCard
              v-if="sitfisMeta || selected"
              variant="subtle"
              title="Resumo do snapshot"
            >
              <dl class="grid gap-2 text-sm sm:grid-cols-2">
                <div>
                  <dt class="text-muted">
                    Observado em
                  </dt>
                  <dd>
                    {{ formatDateTime(String(sitfisMeta?.observed_at || (selected ? detailOf(selected).observed_at : '') || '') || null) }}
                  </dd>
                </div>
                <div>
                  <dt class="text-muted">
                    Expira em
                  </dt>
                  <dd>
                    {{ formatDateTime(String(sitfisMeta?.expires_at || '') || null) }}
                  </dd>
                </div>
                <div>
                  <dt class="text-muted">
                    Dentro do TTL
                  </dt>
                  <dd>
                    <template v-if="sitfisMeta?.is_within_ttl === true">
                      Sim
                    </template>
                    <template v-else-if="sitfisMeta?.is_within_ttl === false">
                      Não
                    </template>
                    <template v-else>
                      —
                    </template>
                  </dd>
                </div>
                <div>
                  <dt class="text-muted">
                    Cobertura
                  </dt>
                  <dd>
                    <FiscalCoverageBadge
                      :coverage="String(sitfisMeta?.coverage || selected?.coverage || '')"
                    />
                  </dd>
                </div>
              </dl>
              <p
                v-if="sitfisMeta?.disclaimer"
                class="mt-3 text-xs text-muted"
              >
                {{ sitfisMeta.disclaimer }}
              </p>
            </UPageCard>

            <div>
              <h3 class="mb-2 text-sm font-medium">
                Findings ativos
              </h3>
              <div
                v-if="!findings.length"
                class="text-sm text-muted"
              >
                Nenhum finding ativo retornado.
              </div>
              <ul
                v-else
                class="divide-y divide-default"
              >
                <li
                  v-for="f in findings"
                  :key="f.id"
                  class="flex items-start justify-between gap-3 py-3 text-sm"
                >
                  <div class="min-w-0">
                    <p class="font-medium text-highlighted">
                      {{ f.title || f.code || `Finding #${f.id}` }}
                    </p>
                    <p class="text-xs text-muted">
                      {{ f.detail || '—' }}
                    </p>
                  </div>
                  <FiscalStatusBadge :status="f.situation || f.severity" />
                </li>
              </ul>
            </div>

            <div>
              <h3 class="mb-2 text-sm font-medium">
                Pendências abertas
              </h3>
              <div
                v-if="!pending.length"
                class="text-sm text-muted"
              >
                Nenhuma pendência aberta retornada.
              </div>
              <ul
                v-else
                class="divide-y divide-default"
              >
                <li
                  v-for="p in pending"
                  :key="p.id"
                  class="flex items-start justify-between gap-3 py-3 text-sm"
                >
                  <div class="min-w-0">
                    <p class="font-medium text-highlighted">
                      {{ p.title || p.code || `Pendência #${p.id}` }}
                    </p>
                    <p class="text-xs text-muted">
                      Venc.: {{ formatDateTime(p.due_at) }} · {{ p.detail || '—' }}
                    </p>
                  </div>
                  <FiscalStatusBadge :status="p.situation || p.status" />
                </li>
              </ul>
            </div>
          </div>
        </template>
      </USlideover>
    </template>
  </FiscalModuleTable>
</template>

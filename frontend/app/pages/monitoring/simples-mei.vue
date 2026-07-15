<script setup lang="ts">
/**
 * Simples Nacional / MEI — carteira via FiscalModuleTable + tabs PGDAS-D/PGMEI/DASN/Regime.
 * Task 7.2 · deep-links para /monitoring/clients/{id}?tab=overview
 */
import type { TableColumn } from '@nuxt/ui'
import type { SimplesMeiClientRow } from '~/types/fiscal-modules'
import { SIMPLES_MEI_TABS } from '~/types/fiscal-modules'

const FiscalStatusBadge = resolveComponent('FiscalStatusBadge')
const FiscalClientCell = resolveComponent('FiscalClientCell')
const FiscalCoverageBadge = resolveComponent('FiscalCoverageBadge')
const UButton = resolveComponent('UButton')

const route = useRoute()

function normalizeSubmodule(raw: unknown): string {
  const v = String(raw || 'PGDASD').toUpperCase()
  const allowed = new Set(SIMPLES_MEI_TABS.map(t => t.value))
  return allowed.has(v as typeof SIMPLES_MEI_TABS[number]['value']) ? v : 'PGDASD'
}

const submodule = ref(normalizeSubmodule(route.query.submodule || route.query.tab))

const {
  page,
  perPage,
  total,
  lastPage,
  q,
  situation,
  competence,
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
} = useFiscalModulePortfolio('simples_mei', { submodule })

const tabItems = SIMPLES_MEI_TABS.map(t => ({ label: t.label, value: t.value }))

function clientHref(clientId: number) {
  return `/monitoring/clients/${clientId}?tab=overview`
}

function onClientId(id: number | null) {
  clientId.value = id != null && id > 0 ? String(id) : ''
}

const columns: TableColumn<SimplesMeiClientRow>[] = [
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
    id: 'competence',
    header: 'Competência',
    cell: ({ row }) => {
      const d = row.original.detail
      return String(row.original.competence || d?.period_key || '—')
    }
  },
  {
    id: 'obligation',
    header: 'Obrigação / submódulo',
    cell: ({ row }) => {
      const d = row.original.detail
      const sub = d?.submodule || submodule.value || '—'
      const action = row.original.next_action
      return action ? `${sub} · ${action}` : String(sub)
    }
  },
  {
    id: 'situation',
    header: 'Situação',
    cell: ({ row }) => h(FiscalStatusBadge, { status: row.original.situation })
  },
  {
    id: 'coverage',
    header: 'Cobertura',
    cell: ({ row }) => h(FiscalCoverageBadge, { coverage: row.original.coverage })
  },
  {
    id: 'guide',
    header: 'Guia',
    cell: ({ row }) => {
      // Portfolio detail não expõe guia individual — deep-link para guias do cliente quando houver ação/prazo.
      const hasGuideHint = Boolean(row.original.next_action || row.original.next_deadline_at)
      return hasGuideHint
        ? h(UButton, {
            size: 'xs',
            color: 'neutral',
            variant: 'ghost',
            label: 'Ver guias',
            to: `/monitoring/clients/${row.original.client_id}?tab=guides`
          })
        : '—'
    }
  },
  {
    id: 'next',
    header: 'Próximo prazo',
    cell: ({ row }) => formatDateTime(row.original.next_deadline_at)
  },
  {
    id: 'consulted',
    header: 'Última consulta',
    cell: ({ row }) => formatDateTime(row.original.last_consulted_at)
  },
  {
    id: 'actions',
    header: '',
    meta: { class: { th: 'w-28', td: 'w-28' } },
    cell: ({ row }) => h(UButton, {
      size: 'xs',
      color: 'neutral',
      variant: 'ghost',
      label: 'Cliente',
      to: clientHref(row.original.client_id)
    })
  }
]
</script>

<template>
  <FiscalModuleTable
    title="Simples Nacional / MEI"
    panel-id="monitoring-simples-mei"
    description="Carteira de leitura PGDAS-D, PGMEI, DASN-SIMEI e regime — sem inventar dados quando a API estiver vazia."
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
    :submodule="submodule"
    :total-clients="totalClients"
    :counters="counters"
    :data-origin="dataOrigin"
    :is-synthetic="isSynthetic"
    :last-good-at="lastValidAt"
    show-competence-filter
    show-client-picker
    empty-title="Nenhum cliente Simples/MEI"
    empty-description="A API do read model não retornou linhas. Nada foi inventado."
    @update:page="page = $event"
    @update:q="q = $event"
    @update:situation="situation = $event"
    @update:competence="competence = $event"
    @update:submodule="submodule = $event"
    @update:client-id="onClientId"
    @refresh="refresh"
    @kpi-select="selectKpi"
  >
    <template #navbar-actions>
      <FiscalMonitoringPortfolioActions
        module-key="simples_mei"
        :client-id="clientId"
        :competence="competence"
        :situation="situation"
        :q="q"
        :submodule="submodule"
        @refreshed="refresh"
      />
    </template>

    <template #submodules>
      <UTabs
        v-model="submodule"
        :items="tabItems"
        :content="false"
        size="sm"
        class="w-auto max-w-full"
        data-testid="simples-mei-submodule-tabs"
      />
    </template>

    <template
      v-if="overviewError"
      #utilities
    >
      <UAlert
        color="warning"
        icon="i-lucide-triangle-alert"
        :title="overviewError"
        description="KPIs indisponíveis; a carteira tenta carregar independentemente."
        class="w-full"
      >
        <template #actions>
          <UButton
            size="xs"
            color="neutral"
            variant="outline"
            label="Retry overview"
            @click="refresh"
          />
        </template>
      </UAlert>
    </template>
  </FiscalModuleTable>
</template>

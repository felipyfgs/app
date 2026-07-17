<script setup lang="ts">
/**
 * Simples Nacional / MEI — carteira via MonitoringModuleTable + tabs PGDAS-D/PGMEI/DASN/Regime.
 * Task 7.2 · deep-links para /monitoring/clients/{id}
 */
import type { TableColumn } from '@nuxt/ui'
import type { SimplesMeiClientRow } from '~/types/fiscal-modules'
import { SIMPLES_MEI_TABS } from '~/types/fiscal-modules'
import { sortHeader } from '~/utils/table-sort'

const FiscalStatusBadge = resolveComponent('FiscalStatusBadge')
const FiscalClientCell = resolveComponent('FiscalClientCell')
const FiscalCoverageBadge = resolveComponent('FiscalCoverageBadge')
const UButton = resolveComponent('UButton')

const route = useRoute()

const submodule = ref(normalizeMonitoringSubmodule('simples_mei', route.params.submodule))

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
  lastValidAt,
  sorting,
  setPage,
  refresh,
  selectKpi,
  applyFilters
} = useFiscalModulePortfolio('simples_mei', {
  submodule,
  submodulePath: value => monitoringSubmodulePath('simples_mei', value)
})

const tabItems = SIMPLES_MEI_TABS.map(t => ({ label: t.label, value: t.value }))

watch(
  () => route.params.submodule,
  (raw) => {
    const next = normalizeMonitoringSubmodule('simples_mei', raw)
    if (next !== submodule.value) submodule.value = next
  }
)

function clientHref(clientId: number) {
  return `/monitoring/clients/${clientId}`
}

function onClientId(id: number | null) {
  clientId.value = id != null && id > 0 ? String(id) : ''
}

const columns: TableColumn<SimplesMeiClientRow>[] = [
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
    id: 'competence',
    header: ({ column }) => sortHeader('Competência', column),
    cell: ({ row }) => {
      const d = row.original.detail
      return String(row.original.competence || d?.period_key || '—')
    }
  },
  {
    id: 'obligation',
    header: 'Obrigação / submódulo',
    enableSorting: false,
    cell: ({ row }) => {
      const d = row.original.detail
      const sub = d?.submodule || submodule.value || '—'
      const action = row.original.next_action
      return action ? `${sub} · ${action}` : String(sub)
    }
  },
  {
    id: 'situation',
    header: ({ column }) => sortHeader('Situação', column),
    cell: ({ row }) => h(FiscalStatusBadge, { status: row.original.situation })
  },
  {
    id: 'coverage',
    header: 'Cobertura',
    enableSorting: false,
    cell: ({ row }) => h(FiscalCoverageBadge, { coverage: row.original.coverage })
  },
  {
    id: 'guide',
    header: 'Guia',
    enableSorting: false,
    cell: ({ row }) => {
      // Portfolio detail não expõe guia individual — deep-link para guias do cliente quando houver ação/prazo.
      const hasGuideHint = Boolean(row.original.next_action || row.original.next_deadline_at)
      return hasGuideHint
        ? h(UButton, {
            size: 'xs',
            color: 'neutral',
            variant: 'ghost',
            label: 'Ver guias',
            to: `/monitoring/clients/${row.original.client_id}/guides`
          })
        : '—'
    }
  },
  {
    id: 'next',
    header: 'Próximo prazo',
    enableSorting: false,
    cell: ({ row }) => formatDateTime(row.original.next_deadline_at)
  },
  {
    id: 'consulted',
    header: ({ column }) => sortHeader('Última consulta', column),
    cell: ({ row }) => formatDateTime(row.original.last_consulted_at)
  },
  {
    id: 'actions',
    header: 'Ações',
    enableHiding: false,
    enableSorting: false,
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
  <MonitoringModuleTable
    title="Simples Nacional / MEI"
    panel-id="monitoring-simples-mei"
    module-key="simples_mei"
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
    :client-id="clientId"
    :total-clients="totalClients"
    :counters="counters"
    :last-good-at="lastValidAt"
    :sorting="sorting"
    show-competence-filter
    show-client-picker
    empty-title="Nenhum cliente"
    :column-labels="{
      obligation: 'Obrigação / submódulo',
      guide: 'Guia',
      next: 'Próximo prazo',
      consulted: 'Última consulta'
    }"
    @update:page="setPage"
    @update:q="q = $event"
    @update:situation="situation = $event"
    @update:competence="competence = $event"
    @update:submodule="submodule = $event"
    @update:client-id="onClientId"
    @update:sorting="sorting = $event"
    @apply-filters="applyFilters"
    @reset-filters="applyFilters"
    @refresh="refresh"
    @kpi-select="selectKpi"
  >
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
  </MonitoringModuleTable>
</template>

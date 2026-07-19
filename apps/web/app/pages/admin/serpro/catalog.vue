<script setup lang="ts">
/**
 * Cobertura / catálogo de operações (platform_support).
 */
import type { TableColumn } from '@nuxt/ui'
import type { SerproCatalogEntry } from '~/types/api'
import { DASHBOARD_TABLE_UI } from '~/utils/table-ui'
import {
  LIST_FILTER_ACTIONS_ROW,
  LIST_FILTER_TOOLBAR_STACK
} from '~/utils/list-filter-layout'

definePageMeta({
  redirect: {
    path: '/admin/serpro/configuration',
    query: { section: 'coverage' }
  }
})

const api = useApi()
const { sessionEpoch } = useDashboard()

const loading = ref(false)
const loadError = ref<string | null>(null)
const rows = ref<SerproCatalogEntry[]>([])
const environment = ref('TRIAL')
const supportFilter = ref('all')

const envItems = [
  { label: 'Demonstração SERPRO', value: 'TRIAL' },
  { label: 'Produção', value: 'PRODUCTION' }
]

const supportItems = [
  { label: 'Todos', value: 'all' },
  { label: 'Implementado', value: 'IMPLEMENTED' },
  { label: 'Validado em produção', value: 'PRODUCTION_VALIDATED' },
  { label: 'Inventariado', value: 'INVENTORIED' }
]

const filtered = computed(() => {
  if (supportFilter.value === 'all') return rows.value
  return rows.value.filter(
    r => String(r.platform_support || '').toUpperCase() === supportFilter.value
  )
})

const columns: TableColumn<SerproCatalogEntry>[] = [
  {
    id: 'operation',
    header: 'Operação',
    cell: ({ row }) => row.original.label || row.original.operation_key || '—'
  },
  {
    id: 'coords',
    header: 'Coordenadas técnicas',
    cell: ({ row }) =>
      [row.original.system_code, row.original.service_code, row.original.operation_code]
        .filter(Boolean)
        .join(' / ') || row.original.operation_key || '—'
  },
  { accessorKey: 'power_code', header: 'Poder' },
  { accessorKey: 'platform_support', header: 'Cobertura' },
  { accessorKey: 'route', header: 'Rota' },
  {
    id: 'billable',
    header: 'Bilhetagem',
    cell: ({ row }) => (row.original.billable === false ? 'Isento' : row.original.billable === true ? 'Bilhetável' : '—')
  }
]

const coverageCounts = computed(() => {
  const map: Record<string, number> = {}
  for (const r of rows.value) {
    const k = String(r.platform_support || 'UNKNOWN').toUpperCase()
    map[k] = (map[k] || 0) + 1
  }
  return map
})

let loadSeq = 0

async function load() {
  const seq = ++loadSeq
  const epoch = sessionEpoch.value
  loading.value = true
  loadError.value = null
  try {
    const res = await api.platform.serpro.catalog({ environment: environment.value })
    if (seq !== loadSeq || epoch !== sessionEpoch.value) return
    rows.value = res.data || []
  } catch (caught) {
    if (seq !== loadSeq || epoch !== sessionEpoch.value) return
    rows.value = []
    loadError.value = apiErrorMessage(caught, 'Falha ao carregar catálogo/cobertura.')
  } finally {
    if (seq === loadSeq && epoch === sessionEpoch.value) {
      loading.value = false
    }
  }
}

function supportBadge(code?: string | null) {
  const c = String(code || '').toUpperCase()
  if (c === 'PRODUCTION_VALIDATED' || c === 'IMPLEMENTED') return 'success' as const
  if (c === 'SIMULATED') return 'neutral' as const
  if (c === 'INVENTORIED') return 'neutral' as const
  return 'neutral' as const
}

function supportLabel(code?: string | null) {
  const labels: Record<string, string> = {
    IMPLEMENTED: 'Implementado',
    PRODUCTION_VALIDATED: 'Validado em produção',
    INVENTORIED: 'Inventariado',
    SIMULATED: 'Registro histórico não oficial',
    UNKNOWN: 'Não classificado'
  }

  return labels[String(code || 'UNKNOWN').toUpperCase()] || String(code || 'Não classificado')
}

watch(environment, () => {
  rows.value = []
  loadError.value = null
  void load()
})
watch(sessionEpoch, () => {
  rows.value = []
  void load()
})
onMounted(load)
</script>

<template>
  <div
    class="flex flex-col gap-4 sm:gap-6"
    data-testid="admin-serpro-catalog"
  >
    <UPageCard
      title="Cobertura de operações"
      variant="naked"
      orientation="horizontal"
    >
      <UButton
        class="w-fit lg:ms-auto"
        color="neutral"
        variant="outline"
        icon="i-lucide-refresh-cw"
        label="Atualizar catálogo"
        :loading="loading"
        @click="load"
      />
    </UPageCard>

    <div :class="LIST_FILTER_TOOLBAR_STACK">
      <div
        v-if="rows.length"
        class="flex flex-wrap gap-1.5"
        aria-label="Resumo da cobertura no ambiente selecionado"
      >
        <UBadge
          v-for="(count, code) in coverageCounts"
          :key="code"
          :color="supportBadge(String(code))"
          variant="subtle"
        >
          {{ supportLabel(String(code)) }} · {{ count }}
        </UBadge>
      </div>

      <div :class="[LIST_FILTER_ACTIONS_ROW, 'items-end']">
        <UFormField label="Ambiente">
          <USelect
            v-model="environment"
            :items="envItems"
            value-key="value"
            class="w-36"
          />
        </UFormField>
        <UFormField label="Suporte">
          <USelect
            v-model="supportFilter"
            :items="supportItems"
            value-key="value"
            class="w-full sm:w-52"
          />
        </UFormField>
      </div>
    </div>

    <UAlert
      v-if="loadError"
      color="error"
      icon="i-lucide-circle-x"
      :title="loadError"
      :actions="[{ label: 'Tentar novamente', color: 'neutral', variant: 'subtle', onClick: load }]"
    />

    <div
      v-if="loading || filtered.length"
      class="overflow-x-auto"
    >
      <UTable
        :data="filtered"
        :loading="loading"
        :columns="columns"
        :ui="DASHBOARD_TABLE_UI"
        class="min-w-4xl shrink-0"
        data-testid="admin-serpro-catalog-table"
      >
        <template #platform_support-cell="{ row }">
          <div class="flex flex-wrap items-center gap-1">
            <UBadge
              :color="supportBadge(row.original.platform_support)"
              variant="subtle"
            >
              {{ supportLabel(row.original.platform_support) }}
            </UBadge>
          </div>
        </template>
      </UTable>
    </div>

    <UEmpty
      v-if="!loading && !loadError && !filtered.length"
      icon="i-lucide-search-x"
      title="Nenhuma operação encontrada"
    />
  </div>
</template>

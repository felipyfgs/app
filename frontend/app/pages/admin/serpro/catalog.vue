<script setup lang="ts">
/**
 * Cobertura / catálogo de operações (platform_support).
 */
import type { TableColumn } from '@nuxt/ui'
import type { SerproCatalogEntry } from '~/types/api'
import { DASHBOARD_TABLE_UI } from '~/utils/table-ui'

const api = useApi()
const { sessionEpoch } = useDashboard()

const loading = ref(false)
const loadError = ref<string | null>(null)
const rows = ref<SerproCatalogEntry[]>([])
const environment = ref('TRIAL')
const supportFilter = ref('all')

const envItems = [
  { label: 'Trial', value: 'TRIAL' },
  { label: 'Produção', value: 'PRODUCTION' }
]

const supportItems = [
  { label: 'Todos', value: 'all' },
  { label: 'IMPLEMENTED', value: 'IMPLEMENTED' },
  { label: 'PRODUCTION_VALIDATED', value: 'PRODUCTION_VALIDATED' },
  { label: 'INVENTORIED', value: 'INVENTORIED' },
  { label: 'SIMULATED', value: 'SIMULATED' }
]

const filtered = computed(() => {
  if (supportFilter.value === 'all') return rows.value
  return rows.value.filter(
    r => String(r.platform_support || '').toUpperCase() === supportFilter.value
  )
})

const columns: TableColumn<SerproCatalogEntry>[] = [
  {
    id: 'coords',
    header: 'Coordenadas',
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
  if (c === 'SIMULATED') return 'warning' as const
  if (c === 'INVENTORIED') return 'neutral' as const
  return 'neutral' as const
}

watch(environment, () => {
  void load()
})
watch(sessionEpoch, () => {
  rows.value = []
  void load()
})
onMounted(load)
</script>

<template>
  <div data-testid="admin-serpro-catalog">
    <UPageCard
      title="Cobertura de operações"
      description="Matriz idSistema/idServico → platform_support. Mutações permanecem bloqueadas nesta change."
      variant="naked"
      orientation="horizontal"
      class="mb-4"
    >
      <div class="flex w-fit flex-wrap items-end gap-2 lg:ms-auto">
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
            class="w-48"
          />
        </UFormField>
        <UButton
          color="neutral"
          variant="ghost"
          icon="i-lucide-refresh-cw"
          label="Atualizar"
          :loading="loading"
          @click="load"
        />
      </div>
    </UPageCard>

    <UAlert
      v-if="loadError"
      color="error"
      icon="i-lucide-circle-x"
      :title="loadError"
      class="mb-4"
    />

    <div class="mb-4 flex flex-wrap gap-2">
      <UBadge
        v-for="(count, code) in coverageCounts"
        :key="code"
        :color="supportBadge(String(code))"
        variant="subtle"
      >
        {{ code }}: {{ count }}
      </UBadge>
    </div>

    <UPageCard
      variant="subtle"
      :ui="{ container: 'p-0 sm:p-0 gap-y-0' }"
    >
      <UTable
        :data="filtered"
        :loading="loading"
        :columns="columns"
        :ui="DASHBOARD_TABLE_UI"
        data-testid="admin-serpro-catalog-table"
      >
        <template #platform_support-cell="{ row }">
          <div class="flex flex-wrap items-center gap-1">
            <UBadge
              :color="supportBadge(row.original.platform_support)"
              variant="subtle"
            >
              {{ row.original.platform_support || '—' }}
            </UBadge>
            <SerproProvenanceBadge
              v-if="String(row.original.platform_support).toUpperCase() === 'SIMULATED'"
              code="simulado"
            />
            <SerproProvenanceBadge
              v-else-if="['IMPLEMENTED', 'PRODUCTION_VALIDATED'].includes(String(row.original.platform_support).toUpperCase())"
              code="real"
            />
          </div>
        </template>
      </UTable>
      <p
        v-if="!loading && !filtered.length"
        class="p-4 text-sm text-muted"
      >
        Nenhuma operação no filtro.
      </p>
    </UPageCard>
  </div>
</template>

<script setup lang="ts">
/**
 * Painel de consumo do tenant (15.9).
 * Sem fatura global, custo interno de outros offices ou orçamento da software house.
 */
import type { TableColumn } from '@nuxt/ui'
import type { OfficeUsageEntry, OfficeUsageSummary } from '~/types/api'
import { DASHBOARD_TABLE_UI } from '~/utils/table-ui'

const api = useApi()
const { sessionEpoch } = useDashboard()

const now = new Date()
const year = ref(now.getFullYear())
const month = ref(now.getMonth() + 1)
const loading = ref(false)
const loadError = ref<string | null>(null)
const usage = ref<OfficeUsageSummary | null>(null)
const entries = ref<OfficeUsageEntry[]>([])
const entriesTotal = ref(0)
const page = ref(1)
const perPage = 20

const summary = computed(() => usage.value?.summary || null)

const serviceColumns: TableColumn<Record<string, unknown>>[] = [
  { accessorKey: 'service_code', header: 'Serviço' },
  { accessorKey: 'system_code', header: 'Sistema' },
  { accessorKey: 'consumption_class', header: 'Classe' },
  { accessorKey: 'total_quantity', header: 'Quantidade' },
  { accessorKey: 'entry_count', header: 'Lançamentos' },
  { accessorKey: 'billable_attempt_count', header: 'Tentativas faturáveis' }
]

const entryColumns: TableColumn<OfficeUsageEntry>[] = [
  {
    accessorKey: 'occurred_at',
    header: 'Quando',
    cell: ({ row }) => formatDateTime(row.original.occurred_at)
  },
  {
    id: 'svc',
    header: 'Operação',
    cell: ({ row }) =>
      [row.original.system_code, row.original.service_code, row.original.operation_code]
        .filter(Boolean)
        .join(' / ') || '—'
  },
  { accessorKey: 'quantity', header: 'Qtd' },
  { accessorKey: 'result', header: 'Resultado' },
  {
    accessorKey: 'client_id',
    header: 'Cliente',
    cell: ({ row }) => row.original.client_id ?? '—'
  }
]

let loadSeq = 0

async function load() {
  const seq = ++loadSeq
  const epoch = sessionEpoch.value
  loading.value = true
  loadError.value = null
  try {
    const [sumRes, entRes] = await Promise.allSettled([
      api.office.serproUsage.summary({ year: year.value, month: month.value }),
      api.office.serproUsage.entries({
        year: year.value,
        month: month.value,
        page: page.value,
        per_page: perPage
      })
    ])

    if (seq !== loadSeq || epoch !== sessionEpoch.value) return

    if (sumRes.status === 'fulfilled') {
      usage.value = sumRes.value.data
    } else {
      usage.value = null
      loadError.value = apiErrorMessage(sumRes.reason, 'Falha ao carregar consumo do escritório.')
    }

    if (entRes.status === 'fulfilled') {
      const body = entRes.value as Record<string, unknown>
      entries.value = (body.data as OfficeUsageEntry[]) || []
      entriesTotal.value = Number(
        (body.total as number | undefined)
        ?? (body.meta as { total?: number } | undefined)?.total
        ?? entries.value.length
      )
    } else {
      entries.value = []
      entriesTotal.value = 0
    }
  } finally {
    if (seq === loadSeq && epoch === sessionEpoch.value) {
      loading.value = false
    }
  }
}

watch([year, month, page], () => {
  void load()
})
watch(sessionEpoch, () => {
  usage.value = null
  entries.value = []
  entriesTotal.value = 0
  void load()
})
onMounted(load)
</script>

<template>
  <div class="space-y-6">
    <UPageCard
      variant="naked"
      title="Consumo do plano"
      description="Uso e franquia do escritório ativo. Não exibe fatura consolidada da plataforma nem dados de outros tenants."
    />

    <div class="flex flex-wrap items-end gap-2">
      <UFormField label="Ano">
        <UInput
          v-model.number="year"
          type="number"
          min="2020"
          max="2100"
          class="w-28"
        />
      </UFormField>
      <UFormField label="Mês">
        <UInput
          v-model.number="month"
          type="number"
          min="1"
          max="12"
          class="w-24"
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

    <UAlert
      v-if="loadError"
      color="error"
      icon="i-lucide-circle-x"
      :title="loadError"
    />

    <div
      v-if="loading && !summary"
      class="text-sm text-muted"
    >
      Carregando…
    </div>

    <UPageGrid
      v-else-if="summary"
      class="lg:grid-cols-4 gap-4"
      data-testid="usage-kpis"
    >
      <UPageCard
        variant="subtle"
        title="Período"
      >
        <p class="text-2xl font-semibold">
          {{ summary.period_month }}/{{ summary.period_year }}
        </p>
      </UPageCard>
      <UPageCard
        variant="subtle"
        title="Usado"
      >
        <p class="text-2xl font-semibold">
          {{ summary.used_quantity }}
        </p>
      </UPageCard>
      <UPageCard
        variant="subtle"
        title="Franquia"
      >
        <p class="text-2xl font-semibold">
          {{ summary.franchise_quota ?? '—' }}
        </p>
      </UPageCard>
      <UPageCard
        variant="subtle"
        title="Saldo"
      >
        <p class="text-2xl font-semibold">
          {{ summary.remaining ?? '—' }}
        </p>
        <UBadge
          v-if="summary.alert_threshold_reached"
          color="warning"
          variant="subtle"
          class="mt-2"
        >
          Alerta de franquia
        </UBadge>
      </UPageCard>
    </UPageGrid>

    <UAlert
      v-else-if="!loading"
      color="neutral"
      icon="i-lucide-inbox"
      title="Sem dados de consumo"
      description="A API não retornou resumo para o período. Nada foi inventado."
    />

    <UPageCard
      v-if="usage?.by_service?.length"
      variant="subtle"
      title="Por serviço"
    >
      <UTable
        :data="usage.by_service as unknown as Record<string, unknown>[]"
        :columns="serviceColumns"
        :ui="DASHBOARD_TABLE_UI"
      />
      <!-- Intencionalmente sem coluna de preço global / fatura consolidada -->
    </UPageCard>

    <UPageCard
      variant="subtle"
      title="Lançamentos do período"
      description="Somente operações atribuídas a este escritório."
    >
      <UEmpty
        v-if="!entries.length"
        icon="i-lucide-receipt"
        title="Nenhum lançamento no período"
      />
      <template v-else>
        <UTable
          :data="entries"
          :columns="entryColumns"
          :ui="DASHBOARD_TABLE_UI"
        />
        <div
          v-if="entriesTotal > perPage"
          class="mt-4 flex justify-end"
        >
          <UPagination
            v-model="page"
            :total="entriesTotal"
            :items-per-page="perPage"
          />
        </div>
      </template>
    </UPageCard>
  </div>
</template>

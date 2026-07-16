<script setup lang="ts">
/**
 * Preços/orçamento global e conciliação (PLATFORM_ADMIN).
 * Sem detalhe fiscal de tenant — apenas agregados e office_id opaco.
 */
import type { TableColumn } from '@nuxt/ui'
import type { SerproUsageConsolidation, SerproUsageReconciliation } from '~/types/api'
import { DASHBOARD_TABLE_UI } from '~/utils/table-ui'

const api = useApi()
const toast = useToast()
const { sessionEpoch } = useDashboard()

const now = new Date()
const year = ref(now.getFullYear())
const month = ref(now.getMonth() + 1)
const loading = ref(false)
const loadError = ref<string | null>(null)
const consolidation = ref<SerproUsageConsolidation | null>(null)
const budget = ref<Record<string, unknown> | null>(null)
const budgetMissing = ref(false)

const reconForm = reactive({
  official_total_cost_micros: 0,
  official_reference: '',
  notes: ''
})
const saving = ref(false)

const tenantColumns: TableColumn<{ office_id: number, entry_count?: number, total_quantity?: number, total_estimated_cost_micros?: number }>[] = [
  { accessorKey: 'office_id', header: 'Office ID' },
  { accessorKey: 'entry_count', header: 'Lançamentos' },
  { accessorKey: 'total_quantity', header: 'Quantidade' },
  {
    id: 'cost',
    header: 'Custo est. (µBRL)',
    cell: ({ row }) => row.original.total_estimated_cost_micros ?? '—'
  }
]

const reconColumns: TableColumn<SerproUsageReconciliation>[] = [
  { accessorKey: 'id', header: 'ID' },
  { accessorKey: 'status', header: 'Status' },
  { accessorKey: 'official_total_cost_micros', header: 'Oficial (µ)' },
  { accessorKey: 'estimated_total_cost_micros', header: 'Estimado (µ)' },
  { accessorKey: 'difference_micros', header: 'Diferença' },
  { accessorKey: 'official_reference', header: 'Referência' }
]

const tenants = computed(() => consolidation.value?.by_tenant || [])
const reconciliations = computed(() => consolidation.value?.reconciliations || [])

let loadSeq = 0

async function load() {
  const seq = ++loadSeq
  const epoch = sessionEpoch.value
  loading.value = true
  loadError.value = null
  try {
    const [consRes, budgetRes] = await Promise.allSettled([
      api.platform.serpro.usage.consolidation({ year: year.value, month: month.value }),
      api.platform.serpro.budgets.show({ year: year.value, month: month.value })
    ])
    if (seq !== loadSeq || epoch !== sessionEpoch.value) return

    if (consRes.status === 'fulfilled') {
      consolidation.value = consRes.value.data
    } else {
      consolidation.value = null
      loadError.value = apiErrorMessage(consRes.reason, 'Falha ao carregar consolidação.')
    }

    if (budgetRes.status === 'fulfilled') {
      budget.value = budgetRes.value.data
      budgetMissing.value = false
    } else {
      budget.value = null
      budgetMissing.value = true
    }
  } finally {
    if (seq === loadSeq && epoch === sessionEpoch.value) {
      loading.value = false
    }
  }
}

async function recompute() {
  saving.value = true
  try {
    await api.platform.serpro.usage.recompute({ year: year.value, month: month.value })
    toast.add({ title: 'Recomputação solicitada', color: 'success' })
    await load()
  } catch (caught) {
    toast.add({ title: apiErrorMessage(caught, 'Falha ao recomputar.'), color: 'error' })
  } finally {
    saving.value = false
  }
}

async function registerRecon() {
  if (reconForm.official_total_cost_micros < 0) {
    toast.add({ title: 'Informe o total oficial em micros de BRL.', color: 'warning' })
    return
  }
  saving.value = true
  try {
    await api.platform.serpro.usage.registerReconciliation({
      year: year.value,
      month: month.value,
      official_total_cost_micros: reconForm.official_total_cost_micros,
      official_reference: reconForm.official_reference || undefined,
      notes: reconForm.notes || undefined
    })
    toast.add({ title: 'Conciliação registrada', color: 'success' })
    reconForm.official_reference = ''
    reconForm.notes = ''
    await load()
  } catch (caught) {
    toast.add({ title: apiErrorMessage(caught, 'Falha ao registrar conciliação.'), color: 'error' })
  } finally {
    saving.value = false
  }
}

watch([year, month], () => {
  void load()
})
watch(sessionEpoch, () => {
  consolidation.value = null
  void load()
})
onMounted(load)
</script>

<template>
  <div data-testid="admin-serpro-usage">
    <UPageCard
      title="Orçamento, preços e conciliação"
      description="Consolidação global e fatura oficial. Sem PII fiscal de tenants — apenas office_id e agregados."
      variant="naked"
      orientation="horizontal"
      class="mb-4"
    >
      <div class="flex w-full flex-wrap items-end gap-2 lg:ms-auto lg:w-fit">
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
        <UButton
          color="neutral"
          variant="soft"
          icon="i-lucide-calculator"
          label="Recomputar"
          :loading="saving"
          @click="recompute"
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

    <div class="flex flex-col gap-4 sm:gap-6">
      <UPageCard
        variant="subtle"
        title="Orçamento global"
      >
        <div
          v-if="budget"
          class="text-sm"
        >
          <pre class="overflow-x-auto rounded bg-elevated p-3 text-xs text-muted">{{ JSON.stringify(budget, null, 2) }}</pre>
        </div>
        <p
          v-else-if="budgetMissing"
          class="text-sm text-muted"
        >
          Endpoint de budgets ainda não disponível — exibindo apenas consolidação de uso.
        </p>
        <p
          v-else
          class="text-sm text-muted"
        >
          Sem dados de orçamento.
        </p>
      </UPageCard>

      <UPageCard
        variant="subtle"
        title="Por tenant (agregado)"
      >
        <div class="mb-2 flex flex-wrap gap-2">
          <SerproProvenanceBadge code="estimado" />
          <span class="text-xs text-muted">Custos são estimados até conciliação oficial.</span>
        </div>
        <UTable
          :data="tenants"
          :loading="loading"
          :columns="tenantColumns"
          :ui="DASHBOARD_TABLE_UI"
        />
        <p
          v-if="!loading && !tenants.length"
          class="mt-2 text-sm text-muted"
        >
          Sem consumo no período.
        </p>
      </UPageCard>

      <UPageCard
        variant="subtle"
        title="Conciliações"
      >
        <div class="mb-3 flex flex-wrap gap-2">
          <SerproProvenanceBadge code="conciliado" />
        </div>
        <UTable
          :data="reconciliations"
          :loading="loading"
          :columns="reconColumns"
          :ui="DASHBOARD_TABLE_UI"
        />
      </UPageCard>

      <UPageCard
        variant="subtle"
        title="Registrar fatura oficial"
        description="Importa total oficial em micros de BRL para o ciclo."
      >
        <div class="grid gap-3 sm:grid-cols-2">
          <UFormField
            label="Total oficial (micros BRL)"
            required
          >
            <UInput
              v-model.number="reconForm.official_total_cost_micros"
              type="number"
              min="0"
              class="w-full"
            />
          </UFormField>
          <UFormField label="Referência oficial">
            <UInput
              v-model="reconForm.official_reference"
              class="w-full"
              placeholder="Nº fatura / ciclo"
              autocomplete="off"
            />
          </UFormField>
          <UFormField
            label="Notas"
            class="sm:col-span-2"
          >
            <UTextarea
              v-model="reconForm.notes"
              class="w-full"
              :rows="2"
            />
          </UFormField>
        </div>
        <div class="mt-4 flex justify-end">
          <UButton
            color="primary"
            label="Registrar conciliação"
            icon="i-lucide-scale"
            :loading="saving"
            @click="registerRecon"
          />
        </div>
      </UPageCard>
    </div>
  </div>
</template>

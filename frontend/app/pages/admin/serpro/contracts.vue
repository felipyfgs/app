<script setup lang="ts">
/**
 * Lista de versões de contrato SERPRO (metadados sanitizados).
 */
import type { TableColumn } from '@nuxt/ui'
import type { SerproContractSanitized } from '~/types/api'
import { DASHBOARD_TABLE_UI } from '~/utils/table-ui'

const api = useApi()
const toast = useToast()
const { sessionEpoch } = useDashboard()

const loading = ref(false)
const loadError = ref<string | null>(null)
const rows = ref<SerproContractSanitized[]>([])
const environment = ref('TRIAL')
const actingId = ref<number | null>(null)

// Reka/USelect proíbe value "" nos items (reserva para limpar seleção).
const envItems = [
  { label: 'Trial', value: 'TRIAL' },
  { label: 'Produção', value: 'PRODUCTION' },
  { label: 'Todos', value: 'all' }
]

const columns: TableColumn<SerproContractSanitized>[] = [
  { accessorKey: 'id', header: 'ID', meta: { class: { th: 'w-16', td: 'w-16' } } },
  { accessorKey: 'environment', header: 'Ambiente' },
  { accessorKey: 'status', header: 'Status' },
  {
    id: 'contractor',
    header: 'Contratante',
    cell: ({ row }) =>
      `${row.original.contractor_cnpj_masked || '—'} · ${row.original.contractor_name || ''}`
  },
  {
    id: 'cert',
    header: 'Cert. até',
    cell: ({ row }) => formatDateTime(row.original.cert_valid_to)
  },
  {
    id: 'flags',
    header: 'Flags',
    cell: ({ row }) => {
      const f: string[] = []
      if (row.original.has_pfx) f.push('PFX')
      if (row.original.has_oauth) f.push('OAuth')
      if (row.original.has_cached_token) f.push('token')
      if (row.original.credentials_exposed) f.push('EXPOSTA')
      return f.join(' · ') || '—'
    }
  }
]

let loadSeq = 0

async function load() {
  const seq = ++loadSeq
  const epoch = sessionEpoch.value
  loading.value = true
  loadError.value = null
  try {
    const res = await api.platform.serpro.contracts.list({
      environment: environment.value === 'all' ? undefined : environment.value
    })
    if (seq !== loadSeq || epoch !== sessionEpoch.value) return
    rows.value = res.data || []
  } catch (caught) {
    if (seq !== loadSeq || epoch !== sessionEpoch.value) return
    rows.value = []
    loadError.value = apiErrorMessage(caught, 'Falha ao listar contratos SERPRO.')
  } finally {
    if (seq === loadSeq && epoch === sessionEpoch.value) {
      loading.value = false
    }
  }
}

async function activate(row: SerproContractSanitized) {
  actingId.value = row.id
  try {
    await api.platform.serpro.contracts.activate(row.id, { replace: true })
    toast.add({ title: `Contrato #${row.id} ativado`, color: 'success' })
    await load()
  } catch (caught) {
    toast.add({ title: apiErrorMessage(caught, 'Falha ao ativar.'), color: 'error' })
  } finally {
    actingId.value = null
  }
}

async function deactivate(row: SerproContractSanitized) {
  actingId.value = row.id
  try {
    await api.platform.serpro.contracts.deactivate(row.id, { reason: 'Desativado via console' })
    toast.add({ title: `Contrato #${row.id} desativado`, color: 'warning' })
    await load()
  } catch (caught) {
    toast.add({ title: apiErrorMessage(caught, 'Falha ao desativar.'), color: 'error' })
  } finally {
    actingId.value = null
  }
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
  <div data-testid="admin-serpro-contracts">
    <UPageCard
      title="Contratos e credenciais"
      description="Somente metadados sanitizados. Sem download de PFX, Consumer Secret ou token."
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
            class="w-40"
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

    <UPageCard
      variant="subtle"
      :ui="{ container: 'p-0 sm:p-0 gap-y-0' }"
    >
      <UTable
        :data="rows"
        :loading="loading"
        :columns="columns"
        :ui="DASHBOARD_TABLE_UI"
        data-testid="admin-serpro-contracts-table"
      >
        <template #status-cell="{ row }">
          <div class="flex flex-wrap items-center gap-1">
            <UBadge
              :color="row.original.status === 'ACTIVE' ? 'success' : row.original.credentials_exposed ? 'error' : 'neutral'"
              variant="subtle"
            >
              {{ row.original.status }}
            </UBadge>
            <SerproProvenanceBadge
              v-if="row.original.credentials_exposed"
              code="possivelmente_bilhetavel"
            />
          </div>
        </template>
        <template #actions-cell>
          <!-- coluna opcional via template se columns tiverem id actions -->
        </template>
      </UTable>

      <div
        v-if="rows.length"
        class="space-y-2 border-t border-default p-4"
      >
        <p class="text-xs text-muted">
          Ações de cutover exigem TOTP e política de quatro olhos no backend.
        </p>
        <div class="flex flex-wrap gap-2">
          <template
            v-for="row in rows"
            :key="row.id"
          >
            <UButton
              v-if="row.status !== 'ACTIVE'"
              size="xs"
              color="primary"
              variant="soft"
              :label="`Ativar #${row.id}`"
              :loading="actingId === row.id"
              @click="activate(row)"
            />
            <UButton
              v-if="row.status === 'ACTIVE'"
              size="xs"
              color="neutral"
              variant="ghost"
              :label="`Desativar #${row.id}`"
              :loading="actingId === row.id"
              @click="deactivate(row)"
            />
          </template>
        </div>
      </div>

      <p
        v-if="!loading && !rows.length"
        class="p-4 text-sm text-muted"
      >
        Nenhum contrato neste filtro.
      </p>
    </UPageCard>
  </div>
</template>

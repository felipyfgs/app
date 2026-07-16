<script setup lang="ts">
/**
 * Procurações / poderes (15.3) — sem material sensível recuperável.
 */
import type { TableColumn } from '@nuxt/ui'
import type { TaxProxyPower } from '~/types/api'
import { DASHBOARD_TABLE_UI } from '~/utils/table-ui'

const api = useApi()
const toast = useToast()
const { sessionEpoch } = useDashboard()

const loading = ref(false)
const loadError = ref<string | null>(null)
const rows = ref<TaxProxyPower[]>([])
const clientId = ref('')
const syncing = ref(false)

const form = reactive({
  client_id: '',
  power_code: '',
  system_code: '',
  service_code: '',
  evidence_ref: '',
  valid_from: '',
  valid_to: ''
})
const saving = ref(false)

const columns: TableColumn<TaxProxyPower>[] = [
  { accessorKey: 'id', header: 'ID', meta: { class: { th: 'w-16', td: 'w-16' } } },
  { accessorKey: 'client_id', header: 'Cliente' },
  { accessorKey: 'power_code', header: 'Poder' },
  { accessorKey: 'system_code', header: 'Sistema' },
  {
    id: 'status',
    header: 'Status',
    cell: ({ row }) =>
      `${row.original.status}${row.original.is_currently_valid ? ' · válido' : ''}`
  },
  {
    id: 'contributor',
    header: 'Contribuinte',
    cell: ({ row }) => row.original.contributor_cnpj_masked || '—'
  },
  {
    id: 'valid',
    header: 'Vigência',
    cell: ({ row }) =>
      `${formatDate(row.original.valid_from)} → ${formatDate(row.original.valid_to)}`
  }
]

let loadSeq = 0

async function load() {
  const seq = ++loadSeq
  const epoch = sessionEpoch.value
  loading.value = true
  loadError.value = null
  try {
    const res = await api.office.serproAuthorization.proxyPowers(
      clientId.value ? { client_id: Number(clientId.value) } : undefined
    )
    if (seq !== loadSeq || epoch !== sessionEpoch.value) return
    rows.value = res.data || []
  } catch (caught) {
    if (seq !== loadSeq || epoch !== sessionEpoch.value) return
    rows.value = []
    loadError.value = apiErrorMessage(caught, 'Falha ao listar procurações.')
  } finally {
    if (seq === loadSeq && epoch === sessionEpoch.value) {
      loading.value = false
    }
  }
}

async function importPower() {
  if (!form.client_id || !form.power_code || !form.system_code || !form.evidence_ref) {
    toast.add({ title: 'Preencha cliente, poder, sistema e referência de evidência.', color: 'warning' })
    return
  }
  saving.value = true
  try {
    await api.office.serproAuthorization.importProxyPower({
      client_id: Number(form.client_id),
      power_code: form.power_code,
      system_code: form.system_code,
      service_code: form.service_code || undefined,
      evidence_ref: form.evidence_ref,
      valid_from: form.valid_from || undefined,
      valid_to: form.valid_to || undefined
    })
    toast.add({ title: 'Procuração importada (evidência referenciada)', color: 'success' })
    form.power_code = ''
    form.evidence_ref = ''
    await load()
  } catch (caught) {
    toast.add({ title: apiErrorMessage(caught, 'Falha ao importar procuração.'), color: 'error' })
  } finally {
    saving.value = false
  }
}

async function syncPowers() {
  syncing.value = true
  try {
    await api.office.serproAuthorization.syncProxyPowers(
      clientId.value ? { client_id: Number(clientId.value) } : undefined
    )
    toast.add({ title: 'Sincronização de poderes solicitada', color: 'success' })
    await load()
  } catch (caught) {
    toast.add({ title: apiErrorMessage(caught, 'Falha ao sincronizar poderes.'), color: 'error' })
  } finally {
    syncing.value = false
  }
}

watch(sessionEpoch, () => {
  rows.value = []
  form.client_id = ''
  form.power_code = ''
  form.system_code = ''
  form.service_code = ''
  form.evidence_ref = ''
  form.valid_from = ''
  form.valid_to = ''
  void load()
})
onMounted(load)
</script>

<template>
  <div class="space-y-6">
    <UPageCard
      variant="naked"
      title="Procurações"
      description="Poderes por contribuinte. Evidências por referência/hash — sem XML recuperável na UI."
    />

    <div class="flex flex-wrap gap-2">
      <UInput
        v-model="clientId"
        type="number"
        min="1"
        placeholder="Filtrar cliente"
        class="w-36"
      />
      <UButton
        color="neutral"
        variant="ghost"
        icon="i-lucide-refresh-cw"
        label="Atualizar"
        :loading="loading"
        @click="load"
      />
      <UButton
        color="primary"
        variant="soft"
        icon="i-lucide-cloud-download"
        label="Sincronizar"
        :loading="syncing"
        @click="syncPowers"
      />
    </div>

    <UAlert
      v-if="loadError"
      color="error"
      icon="i-lucide-circle-x"
      :title="loadError"
    />

    <div
      v-if="loading && !rows.length"
      class="text-sm text-muted"
    >
      Carregando…
    </div>
    <UEmpty
      v-else-if="!rows.length"
      icon="i-lucide-file-key"
      title="Nenhuma procuração retornada"
      description="A API não devolveu procurações para este escritório."
    />
    <UTable
      v-else
      :data="rows"
      :columns="columns"
      :ui="DASHBOARD_TABLE_UI"
    />

    <UPageCard
      variant="subtle"
      title="Importar evidência manual"
    >
      <div class="grid gap-3 sm:grid-cols-2">
        <UFormField label="Cliente ID">
          <UInput
            v-model="form.client_id"
            type="number"
          />
        </UFormField>
        <UFormField label="Código do poder">
          <UInput v-model="form.power_code" />
        </UFormField>
        <UFormField label="Sistema">
          <UInput v-model="form.system_code" />
        </UFormField>
        <UFormField label="Serviço (opcional)">
          <UInput v-model="form.service_code" />
        </UFormField>
        <UFormField
          label="Referência da evidência"
          class="sm:col-span-2"
        >
          <UInput
            v-model="form.evidence_ref"
            placeholder="ID externo / protocolo (não o XML completo)"
          />
        </UFormField>
        <UFormField label="Válido de">
          <UInput
            v-model="form.valid_from"
            type="date"
          />
        </UFormField>
        <UFormField label="Válido até">
          <UInput
            v-model="form.valid_to"
            type="date"
          />
        </UFormField>
      </div>
      <UButton
        class="mt-4"
        label="Importar"
        :loading="saving"
        @click="importPower"
      />
    </UPageCard>
  </div>
</template>

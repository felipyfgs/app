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
/** Rascunho no input de busca (não dispara API sozinho). */
const clientId = ref('')
/** Cliente efetivamente aplicado no feed / sync. */
const appliedClientId = ref<number | null>(null)
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
/** Seletores tipados (serviço/poder) — espelham o formulário de importação. */
const typedService = ref<string | undefined>(undefined)
const typedPower = ref<string | undefined>(undefined)

watch(typedService, (v) => {
  if (v) form.service_code = v
})
watch(typedPower, (v) => {
  if (v) form.power_code = v
})

const draftClientId = computed(() => {
  const n = Number(clientId.value)
  return Number.isInteger(n) && n > 0 ? n : null
})

const canSyncAppliedClient = computed(() =>
  appliedClientId.value !== null && draftClientId.value === appliedClientId.value
)

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
    const res = await api.office.serproAuthorization.proxyPowers({
      client_id: appliedClientId.value ?? undefined
    })
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

function applyClientFilter() {
  appliedClientId.value = draftClientId.value
  void load()
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
  if (!canSyncAppliedClient.value || appliedClientId.value === null) {
    toast.add({ title: 'Aplique o filtro de um cliente antes de sincronizar.', color: 'warning' })
    return
  }
  syncing.value = true
  try {
    await api.office.serproAuthorization.syncProxyPowers({ client_id: appliedClientId.value })
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
  clientId.value = ''
  appliedClientId.value = null
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
  <!--
    Lista em settings (members.vue): naked header + card subtle com search no header.
  -->
  <div>
    <UPageCard
      title="Procurações"
      description="Poderes e procurações do escritório — sem material sensível recuperável."
      variant="naked"
      orientation="horizontal"
      class="mb-4"
    >
      <div class="flex w-fit flex-wrap gap-2 lg:ms-auto">
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
          icon="i-lucide-cloud-download"
          label="Sincronizar"
          :loading="syncing"
          :disabled="!canSyncAppliedClient"
          @click="syncPowers"
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
      class="mb-4"
      :ui="{ container: 'p-0 sm:p-0 gap-y-0', wrapper: 'items-stretch', header: 'p-4 mb-0 border-b border-default' }"
    >
      <template #header>
        <div class="flex w-full flex-col gap-2 sm:flex-row sm:items-center">
          <UInput
            v-model="clientId"
            type="number"
            min="1"
            icon="i-lucide-search"
            placeholder="Filtrar por cliente (ID)"
            class="w-full"
            @keyup.enter="applyClientFilter"
          />
          <UButton
            color="neutral"
            label="Aplicar"
            class="w-full shrink-0 sm:w-auto"
            @click="applyClientFilter"
          />
        </div>
      </template>

      <div
        v-if="loading && !rows.length"
        class="px-4 py-6 text-sm text-muted sm:px-6"
      >
        Carregando…
      </div>
      <UEmpty
        v-else-if="!rows.length"
        icon="i-lucide-file-key"
        title="Nenhuma procuração retornada"
        description="A API não devolveu procurações para este escritório."
        class="py-6"
      />
      <div
        v-else
        class="overflow-x-auto"
      >
        <UTable
          :data="rows"
          :columns="columns"
          :ui="DASHBOARD_TABLE_UI"
        />
      </div>
    </UPageCard>

    <UPageCard
      variant="subtle"
      title="Importar evidência manual"
      description="Referencie evidência externa — não envie XML completo de procuração."
    >
      <SerproTypedSelectors
        v-model:service-code="typedService"
        v-model:power-code="typedPower"
        :show-environment="false"
        :show-client="false"
        class="mb-4"
      />
      <div class="grid gap-3 sm:grid-cols-2">
        <UFormField label="Cliente ID">
          <UInput
            v-model="form.client_id"
            type="number"
            class="w-full"
          />
        </UFormField>
        <UFormField label="Código do poder">
          <UInput
            v-model="form.power_code"
            class="w-full"
            data-testid="proxy-power-code"
          />
        </UFormField>
        <UFormField label="Sistema">
          <UInput
            v-model="form.system_code"
            class="w-full"
          />
        </UFormField>
        <UFormField label="Serviço (opcional)">
          <UInput
            v-model="form.service_code"
            class="w-full"
            data-testid="proxy-service-code"
          />
        </UFormField>
        <UFormField
          label="Referência da evidência"
          class="sm:col-span-2"
        >
          <UInput
            v-model="form.evidence_ref"
            placeholder="ID externo / protocolo (não o XML completo)"
            class="w-full"
          />
        </UFormField>
        <UFormField label="Válido de">
          <UInput
            v-model="form.valid_from"
            type="date"
            class="w-full"
          />
        </UFormField>
        <UFormField label="Válido até">
          <UInput
            v-model="form.valid_to"
            type="date"
            class="w-full"
          />
        </UFormField>
      </div>
      <div class="mt-4 flex justify-end border-t border-default pt-4">
        <UButton
          label="Importar"
          :loading="saving"
          class="w-full justify-center sm:w-auto"
          @click="importPower"
        />
      </div>
    </UPageCard>
  </div>
</template>

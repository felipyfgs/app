<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui'
import type { Client, Establishment, NfseNote } from '~/types/api'
import type { NoteListParams } from '~/composables/useApi'

const api = useApi()
const notes = ref<NfseNote[]>([])
const clients = ref<Client[]>([])
const establishments = ref<Establishment[]>([])
const cursor = ref<string | null>(null)
const loading = ref(false)
const loadingFilters = ref(false)
const toast = useToast()
const filters = reactive({
  access_key: '',
  client_id: '',
  establishment_id: '',
  issuer_cnpj: '',
  taker_cnpj: '',
  fiscal_role: '',
  competence: '',
  issued_from: '',
  issued_to: '',
  status: ''
})

const columns: TableColumn<NfseNote>[] = [
  { accessorKey: 'access_key', header: 'Chave' },
  { accessorKey: 'fiscal_role', header: 'Papel' },
  {
    accessorKey: 'issuer_cnpj',
    header: 'Emitente',
    meta: { class: { th: 'hidden lg:table-cell', td: 'hidden lg:table-cell' } }
  },
  {
    accessorKey: 'competence',
    header: 'Competência',
    meta: { class: { th: 'hidden md:table-cell', td: 'hidden md:table-cell' } }
  },
  {
    accessorKey: 'service_amount',
    header: 'Valor',
    meta: { class: { th: 'hidden xl:table-cell', td: 'hidden xl:table-cell' } }
  },
  { accessorKey: 'status', header: 'Situação' },
  { id: 'actions', header: '' }
]

const clientItems = computed(() => [
  { label: 'Todos os clientes', value: '' },
  ...clients.value.map(client => ({ label: client.name, value: String(client.id) }))
])
const establishmentItems = computed(() => [
  { label: 'Todos os estabelecimentos', value: '' },
  ...establishments.value.map(establishment => ({
    label: establishment.trade_name ? `${establishment.trade_name} · ${establishment.cnpj}` : establishment.cnpj,
    value: String(establishment.id)
  }))
])
const roleItems = [
  { label: 'Todos os papéis', value: '' },
  { label: 'Emitente', value: 'ISSUER' },
  { label: 'Tomador', value: 'TAKER' },
  { label: 'Intermediário', value: 'INTERMEDIARY' }
]
const statusItems = [
  { label: 'Todas as situações', value: '' },
  { label: 'Ativa', value: 'ACTIVE' },
  { label: 'Cancelada', value: 'CANCELLED' },
  { label: 'Em revisão', value: 'UNKNOWN' }
]

function queryParams(): NoteListParams {
  return {
    limit: 25,
    ...(filters.access_key ? { access_key: filters.access_key } : {}),
    ...(filters.client_id ? { client_id: Number(filters.client_id) } : {}),
    ...(filters.establishment_id ? { establishment_id: Number(filters.establishment_id) } : {}),
    ...(filters.issuer_cnpj ? { issuer_cnpj: filters.issuer_cnpj } : {}),
    ...(filters.taker_cnpj ? { taker_cnpj: filters.taker_cnpj } : {}),
    ...(filters.fiscal_role ? { fiscal_role: filters.fiscal_role as NoteListParams['fiscal_role'] } : {}),
    ...(filters.competence ? { competence: filters.competence } : {}),
    ...(filters.issued_from ? { issued_from: filters.issued_from } : {}),
    ...(filters.issued_to ? { issued_to: filters.issued_to } : {}),
    ...(filters.status ? { status: filters.status } : {}),
    ...(cursor.value ? { cursor: cursor.value } : {})
  }
}

async function load(reset = false) {
  if (reset) {
    cursor.value = null
  }
  loading.value = true
  try {
    const response = await api.notes.list(queryParams())
    notes.value = reset ? response.data : [...notes.value, ...response.data]
    cursor.value = response.meta.next_cursor
  } catch (caught) {
    toast.add({ title: apiErrorMessage(caught, 'Erro ao listar notas.'), color: 'error' })
  } finally {
    loading.value = false
  }
}

async function loadClients() {
  loadingFilters.value = true
  try {
    clients.value = (await api.clients.list({ per_page: 100 })).data
  } catch {
    clients.value = []
  } finally {
    loadingFilters.value = false
  }
}

async function onClientChange() {
  filters.establishment_id = ''
  establishments.value = []
  if (!filters.client_id) {
    return
  }
  try {
    establishments.value = (await api.clients.get(Number(filters.client_id))).data.establishments || []
  } catch {
    establishments.value = []
  }
}

function resetFilters() {
  Object.assign(filters, {
    access_key: '',
    client_id: '',
    establishment_id: '',
    issuer_cnpj: '',
    taker_cnpj: '',
    fiscal_role: '',
    competence: '',
    issued_from: '',
    issued_to: '',
    status: ''
  })
  establishments.value = []
  load(true)
}

onMounted(async () => {
  await Promise.all([loadClients(), load(true)])
})
</script>

<template>
  <UDashboardPanel id="notes">
    <template #header>
      <UDashboardNavbar title="Notas fiscais">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
        <template #right>
          <UButton
            icon="i-lucide-refresh-cw"
            color="neutral"
            variant="ghost"
            square
            :loading="loading"
            @click="load(true)"
          />
        </template>
      </UDashboardNavbar>
    </template>

    <template #body>
      <UCard>
        <form class="grid gap-3 md:grid-cols-2 xl:grid-cols-4" @submit.prevent="load(true)">
          <UFormField label="Chave de acesso" class="xl:col-span-2">
            <UInput v-model="filters.access_key" class="w-full" />
          </UFormField>
          <UFormField label="Cliente">
            <USelect
              v-model="filters.client_id"
              :items="clientItems"
              :loading="loadingFilters"
              class="w-full"
              @update:model-value="onClientChange"
            />
          </UFormField>
          <UFormField label="Estabelecimento">
            <USelect
              v-model="filters.establishment_id"
              :items="establishmentItems"
              :disabled="!filters.client_id"
              class="w-full"
            />
          </UFormField>
          <UFormField label="CNPJ emitente">
            <UInput v-model="filters.issuer_cnpj" class="w-full" />
          </UFormField>
          <UFormField label="CNPJ tomador">
            <UInput v-model="filters.taker_cnpj" class="w-full" />
          </UFormField>
          <UFormField label="Papel fiscal">
            <USelect v-model="filters.fiscal_role" :items="roleItems" class="w-full" />
          </UFormField>
          <UFormField label="Situação">
            <USelect v-model="filters.status" :items="statusItems" class="w-full" />
          </UFormField>
          <UFormField label="Competência" help="Não altera o filtro de emissão.">
            <UInput v-model="filters.competence" type="month" class="w-full" />
          </UFormField>
          <UFormField label="Emissão a partir de">
            <UInput v-model="filters.issued_from" type="date" class="w-full" />
          </UFormField>
          <UFormField label="Emissão até">
            <UInput v-model="filters.issued_to" type="date" class="w-full" />
          </UFormField>
          <div class="flex items-end gap-2">
            <UButton type="submit" icon="i-lucide-search" label="Aplicar filtros" />
            <UButton
              color="neutral"
              variant="ghost"
              type="button"
              label="Limpar"
              @click="resetFilters"
            />
          </div>
        </form>
      </UCard>

      <UTable
        :data="notes"
        :loading="loading"
        :columns="columns"
        class="shrink-0"
      >
        <template #access_key-cell="{ row }">
          <div class="max-w-56">
            <NuxtLink
              :to="`/notes/${row.original.access_key}`"
              class="block truncate font-mono text-sm font-medium text-primary hover:underline"
            >
              {{ row.original.access_key }}
            </NuxtLink>
            <p class="text-xs text-muted md:hidden">
              Competência {{ row.original.competence || '—' }}
            </p>
          </div>
        </template>
        <template #fiscal_role-cell="{ row }">
          {{ statusLabel(row.original.fiscal_role) }}
        </template>
        <template #service_amount-cell="{ row }">
          {{ formatCurrency(row.original.service_amount) }}
        </template>
        <template #status-cell="{ row }">
          <AppStatusBadge :status="row.original.status" />
        </template>
        <template #actions-cell="{ row }">
          <UButton
            :to="`/notes/${row.original.access_key}`"
            color="neutral"
            variant="ghost"
            icon="i-lucide-chevron-right"
            square
            aria-label="Abrir nota"
          />
        </template>
      </UTable>

      <UEmpty
        v-if="!loading && !notes.length"
        icon="i-lucide-file-search"
        title="Nenhuma nota encontrada"
        description="Revise os filtros ou aguarde a próxima sincronização do ADN."
      />

      <div v-if="cursor" class="flex justify-center border-t border-default pt-4">
        <UButton
          :loading="loading"
          color="neutral"
          variant="subtle"
          label="Carregar mais"
          @click="load(false)"
        />
      </div>
    </template>
  </UDashboardPanel>
</template>

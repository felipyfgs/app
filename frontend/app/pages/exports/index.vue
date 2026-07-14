<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui'
import type { ExportFilters, ExportJob } from '~/types/api'

const api = useApi()
const route = useRoute()
const router = useRouter()
const { canCreateExport } = useDashboard()
const items = ref<ExportJob[]>([])
const loading = ref(false)
const creating = ref(false)
const createOpen = ref(canCreateExport.value && route.query.new === '1')
const toast = useToast()
const form = reactive({
  access_key: '',
  issuer_cnpj: '',
  taker_cnpj: '',
  competence: '',
  fiscal_role: '',
  status: '',
  issued_from: '',
  issued_to: '',
  include_events: false
})

const columns: TableColumn<ExportJob>[] = [
  { accessorKey: 'id', header: 'ID' },
  { accessorKey: 'status', header: 'Status' },
  {
    accessorKey: 'files_count',
    header: 'Arquivos',
    meta: { class: { th: 'hidden sm:table-cell', td: 'hidden sm:table-cell' } }
  },
  {
    accessorKey: 'byte_size',
    header: 'Tamanho',
    meta: { class: { th: 'hidden md:table-cell', td: 'hidden md:table-cell' } }
  },
  {
    accessorKey: 'expires_at',
    header: 'Expira',
    meta: { class: { th: 'hidden lg:table-cell', td: 'hidden lg:table-cell' } }
  },
  { id: 'actions', header: '' }
]
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
const hasPending = computed(() => items.value.some(item => ['PENDING', 'PROCESSING'].includes(item.status)))

async function load(silent = false) {
  if (!silent) {
    loading.value = true
  }
  try {
    items.value = (await api.exports.list()).data
  } catch (caught) {
    if (!silent) {
      toast.add({ title: apiErrorMessage(caught, 'Erro ao listar exportações.'), color: 'error' })
    }
  } finally {
    loading.value = false
  }
}

function selectedFilters(): ExportFilters {
  return Object.fromEntries(
    Object.entries(form)
      .filter(([key, value]) => key !== 'include_events' && value !== '')
  ) as ExportFilters
}

async function create() {
  if (!canCreateExport.value) {
    return
  }

  creating.value = true
  try {
    await api.exports.create({
      filters: selectedFilters(),
      include_events: form.include_events
    })
    createOpen.value = false
    await router.replace({ query: {} })
    toast.add({ title: 'Exportação enfileirada.', color: 'success' })
    await load()
  } catch (caught) {
    toast.add({ title: apiErrorMessage(caught, 'Falha ao solicitar exportação.'), color: 'error' })
  } finally {
    creating.value = false
  }
}

const { pause, resume } = useIntervalFn(() => {
  if (hasPending.value) {
    load(true)
  }
}, 8000, { immediate: false })

watch(hasPending, pending => pending ? resume() : pause(), { immediate: true })
onMounted(load)
onBeforeUnmount(pause)
</script>

<template>
  <UDashboardPanel id="exports">
    <template #header>
      <UDashboardNavbar title="Exportações">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
        <template #right>
          <UButton
            v-if="canCreateExport"
            icon="i-lucide-plus"
            label="Nova exportação"
            @click="createOpen = true"
          />
        </template>
      </UDashboardNavbar>
    </template>

    <template #body>
      <UAlert
        icon="i-lucide-clock-3"
        title="Arquivos temporários"
        description="ZIPs concluídos ficam disponíveis por 24 horas e são removidos automaticamente após a expiração."
      />

      <UTable
        :data="items"
        :loading="loading"
        :columns="columns"
        class="shrink-0"
      >
        <template #status-cell="{ row }">
          <div>
            <AppStatusBadge :status="row.original.status" />
            <p v-if="row.original.error_message" class="mt-1 max-w-72 text-xs text-error">
              {{ row.original.error_message }}
            </p>
          </div>
        </template>
        <template #files_count-cell="{ row }">
          {{ row.original.files_count }}{{ row.original.include_events ? ' (com eventos)' : '' }}
        </template>
        <template #byte_size-cell="{ row }">
          {{ formatBytes(row.original.byte_size) }}
        </template>
        <template #expires_at-cell="{ row }">
          {{ formatDateTime(row.original.expires_at) }}
        </template>
        <template #actions-cell="{ row }">
          <UButton
            v-if="row.original.status === 'READY'"
            :href="api.exports.downloadUrl(row.original.id)"
            external
            download
            icon="i-lucide-download"
            label="Baixar"
            size="sm"
          />
        </template>
      </UTable>

      <UEmpty
        v-if="!loading && !items.length"
        icon="i-lucide-package-open"
        title="Nenhuma exportação"
        description="Solicite um ZIP usando os mesmos filtros do catálogo de notas."
      >
        <UButton v-if="canCreateExport" label="Solicitar exportação" @click="createOpen = true" />
      </UEmpty>

      <UModal
        v-if="canCreateExport"
        v-model:open="createOpen"
        title="Nova exportação"
        description="Deixe os filtros vazios para incluir todas as notas autorizadas do escritório."
      >
        <template #body>
          <form class="space-y-4" @submit.prevent="create">
            <UFormField label="Chave de acesso">
              <UInput v-model="form.access_key" class="w-full" />
            </UFormField>
            <div class="grid gap-4 sm:grid-cols-2">
              <UFormField label="CNPJ emitente">
                <UInput v-model="form.issuer_cnpj" class="w-full" />
              </UFormField>
              <UFormField label="CNPJ tomador">
                <UInput v-model="form.taker_cnpj" class="w-full" />
              </UFormField>
              <UFormField label="Competência">
                <UInput v-model="form.competence" type="month" class="w-full" />
              </UFormField>
              <UFormField label="Papel fiscal">
                <USelect v-model="form.fiscal_role" :items="roleItems" class="w-full" />
              </UFormField>
              <UFormField label="Emissão a partir de">
                <UInput v-model="form.issued_from" type="date" class="w-full" />
              </UFormField>
              <UFormField label="Emissão até">
                <UInput v-model="form.issued_to" type="date" class="w-full" />
              </UFormField>
              <UFormField label="Situação">
                <USelect v-model="form.status" :items="statusItems" class="w-full" />
              </UFormField>
            </div>
            <UCheckbox v-model="form.include_events" label="Incluir XMLs de eventos vinculados" />
            <div class="flex justify-end gap-2">
              <UButton
                color="neutral"
                variant="ghost"
                type="button"
                @click="createOpen = false"
              >
                Cancelar
              </UButton>
              <UButton type="submit" :loading="creating">
                Enfileirar exportação
              </UButton>
            </div>
          </form>
        </template>
      </UModal>
    </template>
  </UDashboardPanel>
</template>

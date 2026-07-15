<script setup lang="ts">
import * as z from 'zod'
import type { FormSubmitEvent, TableColumn } from '@nuxt/ui'
import type { ExportFilters, ExportJob } from '~/types/api'

const api = useApi()
const route = useRoute()
const router = useRouter()
const { canCreateExport } = useDashboard()
const items = ref<ExportJob[]>([])
const loading = ref(false)
const loadError = ref<string | null>(null)
const creating = ref(false)
const createOpen = ref(canCreateExport.value && route.query.new === '1')
const toast = useToast()

const schema = z.object({
  access_key: z.string().optional(),
  issuer_cnpj: z.string().optional(),
  taker_cnpj: z.string().optional(),
  competence: z.string().optional(),
  fiscal_role: z.string().optional(),
  status: z.string().optional(),
  issued_from: z.string().optional(),
  issued_to: z.string().optional(),
  include_events: z.boolean().optional()
})

type Schema = z.output<typeof schema>

const state = reactive<Partial<Schema>>({
  access_key: '',
  issuer_cnpj: '',
  taker_cnpj: '',
  competence: '',
  fiscal_role: 'all',
  status: 'all',
  issued_from: '',
  issued_to: '',
  include_events: false
})

const columns: TableColumn<ExportJob>[] = [
  { accessorKey: 'id', header: 'ID' },
  { accessorKey: 'status', header: 'Status' },
  {
    id: 'scope',
    header: 'Escopo',
    meta: { class: { th: 'hidden md:table-cell', td: 'hidden md:table-cell' } }
  },
  {
    accessorKey: 'files_count',
    header: 'Arquivos',
    meta: { class: { th: 'hidden sm:table-cell', td: 'hidden sm:table-cell' } }
  },
  {
    accessorKey: 'byte_size',
    header: 'Tamanho',
    meta: { class: { th: 'hidden lg:table-cell', td: 'hidden lg:table-cell' } }
  },
  {
    accessorKey: 'expires_at',
    header: 'Expira',
    meta: { class: { th: 'hidden lg:table-cell', td: 'hidden lg:table-cell' } }
  },
  { id: 'actions', header: '' }
]

// Reka UI/USelect proíbe value ""; use "all" como sentinela de “sem filtro”.
const FILTER_ALL = 'all'
const roleItems = [
  { label: 'Todos os papéis', value: FILTER_ALL },
  { label: 'Emitente', value: 'ISSUER' },
  { label: 'Tomador', value: 'TAKER' },
  { label: 'Intermediário', value: 'INTERMEDIARY' }
]
const statusItems = [
  { label: 'Todas as situações', value: FILTER_ALL },
  { label: 'Autorizada', value: 'AUTHORIZED' },
  { label: 'Cancelada', value: 'CANCELLED' },
  { label: 'Em revisão', value: 'UNKNOWN' }
]

const hasPending = computed(() =>
  items.value.some(item => ['PENDING', 'PROCESSING'].includes(item.status))
)

function isExpired(job: ExportJob) {
  if (job.status === 'EXPIRED') {
    return true
  }
  if (job.expires_at) {
    return new Date(job.expires_at).getTime() < Date.now()
  }
  return false
}

function canDownload(job: ExportJob) {
  return job.status === 'READY' && !isExpired(job)
}

function scopeSummary(job: ExportJob): string {
  const filters = job.filters || {}
  const parts = Object.entries(filters)
    .filter(([, value]) => value !== null && value !== undefined && value !== '')
    .map(([key, value]) => `${key}=${value}`)
  if (!parts.length) {
    return 'Todas as notas'
  }
  return parts.slice(0, 3).join(' · ') + (parts.length > 3 ? '…' : '')
}

async function load(silent = false) {
  if (!silent) {
    loading.value = true
  }
  try {
    items.value = (await api.exports.list()).data
    loadError.value = null
  } catch (caught) {
    loadError.value = apiErrorMessage(caught, 'Erro ao listar exportações.')
    if (!silent) {
      toast.add({ title: loadError.value, color: 'error' })
    }
    // Em falha transitória silenciosa, preserva items atuais.
  } finally {
    loading.value = false
  }
}

function selectedFilters(): ExportFilters {
  return Object.fromEntries(
    Object.entries(state)
      .filter(([key, value]) =>
        key !== 'include_events'
        && value !== ''
        && value !== undefined
        && value !== 'all'
      )
  ) as ExportFilters
}

function resetForm() {
  state.access_key = ''
  state.issuer_cnpj = ''
  state.taker_cnpj = ''
  state.competence = ''
  state.fiscal_role = 'all'
  state.status = 'all'
  state.issued_from = ''
  state.issued_to = ''
  state.include_events = false
}

async function onSubmit(_event: FormSubmitEvent<Schema>) {
  if (!canCreateExport.value || creating.value) {
    return
  }

  creating.value = true
  try {
    await api.exports.create({
      filters: selectedFilters(),
      include_events: !!state.include_events
    })
    createOpen.value = false
    resetForm()
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
watch(createOpen, (open) => {
  if (!open) {
    resetForm()
  }
})
onMounted(load)
onBeforeUnmount(pause)
</script>

<template>
  <UDashboardPanel id="exports">
    <template #header>
      <UDashboardNavbar data-testid="page-navbar" title="Exportações">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
        <template #right>
          <UButton
            v-if="canCreateExport"
            icon="i-lucide-plus"
            label="Nova exportação"
            class="hidden sm:inline-flex"
            @click="() => { createOpen = true }"
          />
          <UButton
            v-if="canCreateExport"
            icon="i-lucide-plus"
            square
            class="sm:hidden"
            aria-label="Nova exportação"
            @click="() => { createOpen = true }"
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

      <UAlert
        v-if="loadError"
        :color="items.length ? 'warning' : 'error'"
        icon="i-lucide-wifi-off"
        :title="items.length ? 'Falha ao atualizar exportações' : 'Não foi possível carregar exportações'"
        :description="loadError"
        :actions="[{ label: 'Tentar novamente', color: 'neutral', variant: 'subtle', onClick: () => load() }]"
      />

      <UTable
        data-testid="data-table"
        :data="items"
        :loading="loading"
        :columns="columns"
        class="shrink-0"
        :ui="{
          base: 'table-fixed border-separate border-spacing-0',
          thead: '[&>tr]:bg-elevated/50 [&>tr]:after:content-none',
          tbody: '[&>tr]:last:[&>td]:border-b-0',
          th: 'py-2 first:rounded-l-lg last:rounded-r-lg border-y border-default first:border-l last:border-r',
          td: 'border-b border-default',
          separator: 'h-0'
        }"
      >
        <template #status-cell="{ row }">
          <div>
            <AppStatusBadge :status="isExpired(row.original) && row.original.status === 'READY' ? 'EXPIRED' : row.original.status" />
            <p v-if="row.original.error_message" class="mt-1 max-w-72 text-xs text-error">
              {{ row.original.error_message }}
            </p>
          </div>
        </template>
        <template #scope-cell="{ row }">
          <span class="text-sm text-muted">{{ scopeSummary(row.original) }}</span>
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
            v-if="canDownload(row.original)"
            :href="api.exports.downloadUrl(row.original.id)"
            external
            download
            icon="i-lucide-download"
            label="Baixar"
            size="sm"
            aria-label="Baixar pacote de exportação"
          />
          <span
            v-else-if="isExpired(row.original)"
            class="text-xs text-muted"
          >
            Solicite novo pacote
          </span>
        </template>
      </UTable>

      <UEmpty
        v-if="!loading && !loadError && !items.length"
        icon="i-lucide-package-open"
        title="Nenhuma exportação"
        description="Solicite um ZIP usando os mesmos filtros do catálogo de notas."
      >
        <UButton v-if="canCreateExport" label="Solicitar exportação" @click="() => { createOpen = true }" />
      </UEmpty>

      <UModal
        v-if="canCreateExport"
        v-model:open="createOpen"
        title="Nova exportação"
        description="Deixe os filtros vazios para incluir todas as notas autorizadas do escritório."
      >
        <template #body>
          <UForm
            :schema="schema"
            :state="state"
            class="space-y-4"
            @submit="onSubmit"
          >
            <UFormField label="Chave de acesso" name="access_key">
              <UInput v-model="state.access_key" class="w-full" />
            </UFormField>
            <div class="grid gap-4 sm:grid-cols-2">
              <UFormField label="CNPJ emitente" name="issuer_cnpj">
                <UInput v-model="state.issuer_cnpj" class="w-full" />
              </UFormField>
              <UFormField label="CNPJ tomador" name="taker_cnpj">
                <UInput v-model="state.taker_cnpj" class="w-full" />
              </UFormField>
              <UFormField label="Competência" name="competence">
                <UInput v-model="state.competence" type="month" class="w-full" />
              </UFormField>
              <UFormField label="Papel fiscal" name="fiscal_role">
                <USelect v-model="state.fiscal_role" :items="roleItems" class="w-full" />
              </UFormField>
              <UFormField label="Emissão a partir de" name="issued_from">
                <UInput v-model="state.issued_from" type="date" class="w-full" />
              </UFormField>
              <UFormField label="Emissão até" name="issued_to">
                <UInput v-model="state.issued_to" type="date" class="w-full" />
              </UFormField>
              <UFormField label="Situação" name="status">
                <USelect v-model="state.status" :items="statusItems" class="w-full" />
              </UFormField>
            </div>
            <UCheckbox
              v-model="state.include_events"
              label="Incluir XMLs de eventos vinculados"
              name="include_events"
            />
            <div class="flex justify-end gap-2">
              <UButton
                color="neutral"
                variant="subtle"
                type="button"
                label="Cancelar"
                :disabled="creating"
                @click="() => { createOpen = false }"
              />
              <UButton
                type="submit"
                label="Enfileirar exportação"
                :loading="creating"
                :disabled="creating"
              />
            </div>
          </UForm>
        </template>
      </UModal>
    </template>
  </UDashboardPanel>
</template>

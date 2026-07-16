<script setup lang="ts">
/**
 * Histórico de lotes de importação XML/ZIP.
 * Arquétipo: customers.vue (lista + tabela) — adaptado a batches.
 */
import type { TableColumn, TableRow } from '@nuxt/ui'
import { DASHBOARD_TABLE_UI } from '~/utils/table-ui'

const api = useApi()
const router = useRouter()
const toast = useToast()
const { sessionEpoch, canImportDocuments } = useDashboard()

const items = ref<Array<Record<string, unknown>>>([])
const loading = ref(false)
const loadError = ref<string | null>(null)
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)

const columns: TableColumn<Record<string, unknown>>[] = [
  { accessorKey: 'id', header: 'Lote' },
  { accessorKey: 'status', header: 'Status' },
  {
    accessorKey: 'file_count',
    header: 'Arquivos',
    meta: { class: { th: 'hidden sm:table-cell', td: 'hidden sm:table-cell' } }
  },
  {
    accessorKey: 'processed_count',
    header: 'Processados',
    meta: { class: { th: 'hidden md:table-cell', td: 'hidden md:table-cell' } }
  },
  {
    accessorKey: 'imported_count',
    header: 'Importados',
    meta: { class: { th: 'hidden lg:table-cell', td: 'hidden lg:table-cell' } }
  },
  {
    accessorKey: 'created_at',
    header: 'Criado',
    meta: { class: { th: 'hidden md:table-cell', td: 'hidden md:table-cell' } }
  },
  { id: 'actions', header: '' }
]

function statusColor(status: string): 'success' | 'warning' | 'error' | 'info' | 'neutral' {
  if (status === 'COMPLETED') return 'success'
  if (status === 'COMPLETED_WITH_ERRORS') return 'warning'
  if (status === 'FAILED') return 'error'
  if (status === 'PROCESSING' || status === 'QUEUED') return 'info'
  return 'neutral'
}

async function load() {
  const epoch = sessionEpoch.value
  loading.value = true
  try {
    const res = await api.documents.importBatches({ page: page.value, per_page: 20 })
    if (epoch !== sessionEpoch.value) return
    items.value = res.data || []
    lastPage.value = Number(res.meta?.last_page || 1)
    total.value = Number(res.meta?.total || items.value.length)
    loadError.value = null
  } catch (caught) {
    if (epoch !== sessionEpoch.value) return
    loadError.value = apiErrorMessage(caught, 'Erro ao carregar lotes de importação.')
    toast.add({ title: loadError.value, color: 'error' })
  } finally {
    if (epoch === sessionEpoch.value) loading.value = false
  }
}

function openBatch(row: Record<string, unknown>) {
  const id = String(row.public_id || row.id || '')
  if (!id) return
  void router.push(`/docs/imports/${encodeURIComponent(id)}`)
}

function selectRow(_event: Event, row: TableRow<Record<string, unknown>>) {
  openBatch(row.original)
}

watch(page, () => {
  void load()
})

watch(sessionEpoch, () => {
  items.value = []
  page.value = 1
  total.value = 0
  loadError.value = null
  void load()
})

onMounted(() => {
  void load()
})
</script>

<template>
  <UDashboardPanel id="import-batches">
    <template #header>
      <UDashboardNavbar data-testid="page-navbar" title="Importações XML/ZIP">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
        <template #right>
          <UTooltip text="Atualizar histórico">
            <UButton
              icon="i-lucide-refresh-cw"
              color="neutral"
              variant="ghost"
              square
              aria-label="Atualizar histórico de lotes"
              :loading="loading"
              @click="load"
            />
          </UTooltip>
          <UButton
            v-if="canImportDocuments"
            icon="i-lucide-upload"
            label="Nova importação"
            to="/docs?import=1"
          />
        </template>
      </UDashboardNavbar>
    </template>

    <template #body>
      <UAlert
        icon="i-lucide-info"
        title="Lotes assíncronos de saída"
        description="Upload e processamento são estágios distintos. Feche o modal de envio sem perder o progresso — reabra o lote por esta lista ou URL."
      />

      <UAlert
        v-if="loadError"
        color="error"
        icon="i-lucide-wifi-off"
        title="Não foi possível carregar lotes"
        :description="loadError"
        :actions="[{ label: 'Tentar novamente', color: 'neutral', variant: 'subtle', onClick: load }]"
      />

      <UTable
        v-if="loading || items.length"
        data-testid="data-table"
        :data="items"
        :loading="loading"
        :columns="columns"
        class="shrink-0"
        :ui="DASHBOARD_TABLE_UI"
        @select="selectRow"
      >
        <template #id-cell="{ row }">
          <span class="font-mono text-xs">
            {{ String(row.original.public_id || row.original.id || '').slice(0, 12) }}…
          </span>
        </template>
        <template #status-cell="{ row }">
          <div class="flex flex-wrap items-center gap-1">
            <UBadge :color="statusColor(String(row.original.status))" variant="subtle">
              {{ statusLabel(String(row.original.status)) }}
            </UBadge>
            <UBadge
              v-if="row.original.upload_complete && !row.original.processing_complete"
              color="info"
              variant="outline"
              size="sm"
            >
              Processando
            </UBadge>
          </div>
        </template>
        <template #created_at-cell="{ row }">
          {{ row.original.created_at ? formatDateTime(String(row.original.created_at)) : '—' }}
        </template>
        <template #actions-cell="{ row }">
          <UButton
            color="neutral"
            variant="ghost"
            icon="i-lucide-eye"
            square
            aria-label="Abrir detalhe do lote"
            @click.stop="openBatch(row.original)"
          />
        </template>
      </UTable>

      <UEmpty
        v-if="!loading && !loadError && !items.length"
        icon="i-lucide-upload"
        title="Nenhum lote ainda"
        description="Importe XML/ZIP em Documentos para criar o primeiro lote."
        :actions="[{ label: 'Ir a Documentos', to: '/docs' }]"
      />

      <div
        v-if="lastPage > 1"
        class="flex items-center justify-between border-t border-default pt-4"
      >
        <p class="text-sm text-muted">
          {{ total }} lote(s)
        </p>
        <UPagination
          v-model:page="page"
          :total="total"
          :items-per-page="20"
          :sibling-count="1"
        />
      </div>
    </template>
  </UDashboardPanel>
</template>

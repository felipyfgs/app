<script setup lang="ts">
/**
 * Posto operacional Documentos (`/docs`) — tabela densa (customers)
 * + detalhe em modal responsivo + export. Views
 * ficam no submenu do sidebar (`navigation.ts`), não em tabs da página.
 * Fonte: .reference/nuxt-dashboard-template/app/pages/customers.vue
 */
import { documentKindLabel, isDocumentKindCaptureAvailable } from '~/utils/documentKinds'
import type { Client, Establishment, ExportFilters, NfseNote, NotesInsights } from '~/types/api'
import type { NoteListParams } from '~/composables/useApi'
import {
  activeTriageQueue,
  applyTriageQueue,
  catalogToExportFilters,
  emptyNotesFilters,
  FILTER_ALL,
  hasExportableCatalogFilters,
  isActiveFilterValue,
  type NotesFilterState,
  type NotesTriageQueue,
  type NotesViewMode
} from '~/utils/notes-filters'

/** Teto alinhado a BuildExportZipJob::MAX_ACCESS_KEYS */
const MAX_EXPORT_KEYS = 100

const api = useApi()
const route = useRoute()
const router = useRouter()
const toast = useToast()
const props = withDefaults(defineProps<{
  initialView?: NotesViewMode
}>(), {
  initialView: 'client'
})
const { canCreateExport, canImportDocuments, sessionEpoch } = useDashboard()

const notes = ref<NfseNote[]>([])
/** Lista de clientes (API de cadastro) na visão Por cliente — com captura/sync. */
const byClientRows = ref<Client[]>([])
const byClientPage = ref(1)
const byClientPerPage = ref(20)
const byClientTotal = ref(0)
const byClientLastPage = ref(1)
/** Filtro operacional server-side da lista de clientes em Documentos. */
const clientOperationalFilter = ref('total')
const clients = ref<Client[]>([])
const establishments = ref<Establishment[]>([])
const insights = ref<NotesInsights | null>(null)
const insightsLoading = ref(false)
const nextCursor = ref<string | null>(null)
/** Total no escopo dos filtros (meta.total da API). */
const listTotal = ref(0)
/** Tamanho do lote incremental (enviado como `limit`; API 1–100). */
const pageSize = ref(25)
const loading = ref(false)
const loadError = ref<string | null>(null)
const loadingFilters = ref(false)
const exporting = ref(false)
const importOpen = ref(false)
const importing = ref(false)
const importFiles = ref<File[]>([])
const importClientId = ref<string>(FILTER_ALL)
const selectedKeys = ref<string[]>([])

const triageQueue = computed(() => activeTriageQueue(filters))

const persistedFilters = useState<NotesFilterState>('notes-workspace-filters', emptyNotesFilters)
const filters = reactive<NotesFilterState>({ ...persistedFilters.value })
const view = ref<NotesViewMode>(props.initialView)

watch(filters, (value) => {
  persistedFilters.value = { ...value }
}, { deep: true })

watch(sessionEpoch, () => {
  const empty = emptyNotesFilters()
  Object.assign(filters, empty)
  persistedFilters.value = empty
})

const selectedAccessKey = computed(() =>
  typeof route.params.accessKey === 'string' ? route.params.accessKey : null
)

const selectedPreview = computed(() =>
  notes.value.find(n => n.access_key === selectedAccessKey.value) || null
)

/** Controla o detalhe canônico pela rota. */
const isDetailOpen = computed({
  get: () => !!selectedAccessKey.value,
  set: (open: boolean) => {
    if (!open) closeDetail()
  }
})

/**
 * Disponibilidade de captura do tipo filtrado.
 * - NFSE / NFE / NFCE: operacionais (NFC-e = saída/import, não DistDFe entrada).
 * - Linha da API só confirma true; `false`/ausente não deve sobrescrever o stack local.
 */
const kindCaptureAvailable = computed(() => {
  if (filters.kind === FILTER_ALL) return true
  if (isDocumentKindCaptureAvailable(filters.kind)) return true
  const runtimeValue = notes.value.find(note => note.kind === filters.kind)?.capture_available
  return runtimeValue === true
})

const kindCaptureUnavailableHint = computed(() => {
  const kind = filters.kind
  if (kind === 'CTE') {
    return 'CT-e DistDFe ainda não está ligado nesta instância (SEFAZ_CTE_ENABLED).'
  }
  return 'A fonte SEFAZ correspondente ainda não está habilitada nesta instância.'
})

const kindExportAvailable = computed(() =>
  filters.kind === FILTER_ALL
  || filters.kind === 'NFSE'
  || filters.kind === 'NFE'
  || filters.kind === 'NFCE'
)

function queryParams(cursor?: string | null): NoteListParams {
  return {
    limit: pageSize.value,
    ...(isActiveFilterValue(filters.q) ? { q: filters.q } : {}),
    ...(isActiveFilterValue(filters.kind) ? { kind: filters.kind } : {}),
    ...(isActiveFilterValue(filters.direction) ? { direction: filters.direction as NoteListParams['direction'] } : {}),
    ...(isActiveFilterValue(filters.client_id) ? { client_id: Number(filters.client_id) } : {}),
    ...(isActiveFilterValue(filters.establishment_id) ? { establishment_id: Number(filters.establishment_id) } : {}),
    ...(isActiveFilterValue(filters.issuer_cnpj) ? { issuer_cnpj: filters.issuer_cnpj } : {}),
    ...(isActiveFilterValue(filters.taker_cnpj) ? { taker_cnpj: filters.taker_cnpj } : {}),
    ...(isActiveFilterValue(filters.fiscal_role) ? { fiscal_role: filters.fiscal_role as NoteListParams['fiscal_role'] } : {}),
    ...(isActiveFilterValue(filters.competence) ? { competence: filters.competence } : {}),
    ...(isActiveFilterValue(filters.issued_from) ? { issued_from: filters.issued_from } : {}),
    ...(isActiveFilterValue(filters.issued_to) ? { issued_to: filters.issued_to } : {}),
    ...(isActiveFilterValue(filters.status) ? { status: filters.status } : {}),
    ...(filters.missing_party_name === '1' ? { missing_party_name: 1 } : {}),
    ...(cursor ? { cursor } : {})
  }
}

function resetPagination() {
  nextCursor.value = null
  listTotal.value = 0
}

/** Params de insights: sem cursor/limit; mantém escopo de filtro. */
function insightsParams(): NoteListParams {
  const p = queryParams()
  delete p.limit
  delete p.cursor
  return p
}

async function reloadActive() {
  await Promise.all([
    view.value === 'client' ? loadByClient() : load(true),
    loadInsights()
  ])
}

async function loadInsights() {
  insightsLoading.value = true
  try {
    insights.value = (await api.documents.insights(insightsParams())).data
  } catch {
    // Não bloqueia o catálogo se insights falharem
    insights.value = null
  } finally {
    insightsLoading.value = false
  }
}

async function onTriageSelect(queue: NotesTriageQueue) {
  // segundo clique na mesma fila limpa
  const nextQueue = triageQueue.value === queue && queue !== 'all' ? 'all' : queue
  const label = insights.value?.competence_current_label
  Object.assign(filters, applyTriageQueue(filters, nextQueue, label))
  selectedKeys.value = []
  // Fila de documento força view catálogo (exceto "all" em por cliente)
  if (nextQueue !== 'all' && view.value !== 'document') {
    persistedFilters.value = { ...filters }
    await router.push('/docs/catalog')
    return
  }
  await reloadActive()
}

/** Carrega o próximo lote usando exatamente o cursor entregue pela API. */
async function load(reset = false): Promise<void> {
  if (!reset && !nextCursor.value && notes.value.length) return
  if (reset) {
    resetPagination()
    notes.value = []
    selectedKeys.value = []
  }
  loading.value = true
  try {
    const response = await api.documents.list(queryParams(reset ? null : nextCursor.value))
    const merged = reset ? response.data : [...notes.value, ...response.data]
    notes.value = Array.from(new Map(merged.map(note => [note.access_key, note])).values())
    nextCursor.value = response.meta.next_cursor
    listTotal.value = response.meta.total
      ?? notes.value.length
    loadError.value = null
  } catch (caught) {
    loadError.value = apiErrorMessage(caught, 'Erro ao listar documentos.')
    toast.add({ title: loadError.value, color: 'error' })
  } finally {
    loading.value = false
  }
}

async function onPageSizeChange(size: number) {
  const next = Math.min(100, Math.max(1, Math.floor(size)))
  if (next === pageSize.value || loading.value) return
  pageSize.value = next
  selectedKeys.value = []
  await load(true)
}

async function loadByClient() {
  loading.value = true
  loadError.value = null
  try {
    const operational = clientOperationalFilter.value
    const response = await api.clients.list({
      page: byClientPage.value,
      per_page: byClientPerPage.value,
      q: isActiveFilterValue(filters.q) ? filters.q.trim() : undefined,
      operational_filter: operational === 'total'
        ? undefined
        : operational as 'with_credential' | 'without_credential' | 'expiring' | 'capture_problem',
      sort: 'legal_name',
      direction: 'asc'
    })
    byClientRows.value = response.data
    byClientTotal.value = response.meta.total
    byClientLastPage.value = response.meta.last_page
  } catch (caught) {
    loadError.value = apiErrorMessage(caught, 'Erro ao listar clientes.')
    toast.add({ title: loadError.value, color: 'error' })
    byClientRows.value = []
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
  filters.establishment_id = FILTER_ALL
  establishments.value = []
  if (!isActiveFilterValue(filters.client_id)) return
  try {
    establishments.value = (await api.clients.get(Number(filters.client_id))).data.establishments || []
  } catch {
    establishments.value = []
  }
}

function resetFilters() {
  Object.assign(filters, emptyNotesFilters())
  establishments.value = []
  selectedKeys.value = []
  clientOperationalFilter.value = 'total'
  byClientPage.value = 1
  reloadActive()
}

async function applyFilters() {
  selectedKeys.value = []
  byClientPage.value = 1
  await reloadActive()
}

async function onByClientPageChange(page: number) {
  if (page === byClientPage.value || loading.value) return
  byClientPage.value = page
  await loadByClient()
}

async function onByClientPerPageChange(size: number) {
  const next = Math.min(50, Math.max(10, Math.floor(size)))
  if (next === byClientPerPage.value || loading.value) return
  byClientPerPage.value = next
  byClientPage.value = 1
  await loadByClient()
}

async function selectNote(note: NfseNote) {
  await router.push(`/docs/${note.access_key}`)
}

async function closeDetail() {
  await router.replace('/docs/catalog')
}

async function openClientNotes(client: Client) {
  filters.client_id = String(client.id)
  filters.establishment_id = FILTER_ALL
  selectedKeys.value = []
  await onClientChange()
  persistedFilters.value = { ...filters }
  await router.push('/docs/catalog')
}

async function openClientDetail(client: Client) {
  await navigateTo(`/clients/${client.id}`)
}

function buildExportFiltersFromCatalog(): ExportFilters {
  return catalogToExportFilters(filters) as ExportFilters
}

const importDragOver = ref(false)
const importTotalBytes = computed(() => importFiles.value.reduce((s, f) => s + f.size, 0))
const importLimitExceeded = computed(() =>
  importFiles.value.length > 50 || importTotalBytes.value > 20 * 1024 * 1024
)

function setImportFiles(list: File[]) {
  importFiles.value = list.slice(0, 50)
}

function onImportFileChange(event: Event) {
  const input = event.target as HTMLInputElement
  setImportFiles(input.files ? Array.from(input.files) : [])
}

function onImportDrop(event: DragEvent) {
  event.preventDefault()
  importDragOver.value = false
  const files = event.dataTransfer?.files
  if (!files?.length) return
  setImportFiles(Array.from(files).filter(f =>
    /\.(xml|zip)$/i.test(f.name) || f.type.includes('xml') || f.type.includes('zip')
  ))
}

function removeImportFile(index: number) {
  importFiles.value = importFiles.value.filter((_, i) => i !== index)
}

async function submitImport() {
  if (!canImportDocuments.value || importing.value) return
  if (!importFiles.value.length) {
    toast.add({ title: 'Selecione ao menos um XML ou ZIP', color: 'warning' })
    return
  }
  if (importLimitExceeded.value) {
    toast.add({
      title: importFiles.value.length > 50
        ? 'Máximo de 50 arquivos por lote'
        : 'Total compactado excede 20 MiB',
      color: 'warning'
    })
    return
  }
  importing.value = true
  try {
    const clientId = isActiveFilterValue(importClientId.value)
      ? Number(importClientId.value)
      : (isActiveFilterValue(filters.client_id) ? Number(filters.client_id) : null)
    const res = await api.documents.importBatch(importFiles.value, { clientId })
    const r = res.data as Record<string, unknown>
    const status = String(r.status || '')
    const batchId = String(r.public_id || r.id || '')
    const imported = Number(r.imported_count ?? 0)
    const failed = Number(r.failed_count ?? 0) + Number(r.unmatched_count ?? 0)
    toast.add({
      title: status
        ? `Lote ${batchId.slice(0, 8)}… · ${status}`
        : `Importação: ${imported} ok, ${failed} falhas`,
      description: 'Progresso continua após fechar este modal. Acompanhe em Importações.',
      color: failed && !imported ? 'error' : 'success',
      actions: batchId
        ? [{
            label: 'Abrir lote',
            onClick: async () => {
              await navigateTo(`/docs/imports/${encodeURIComponent(batchId)}`)
            }
          }]
        : undefined
    })
    importOpen.value = false
    importFiles.value = []
    if (batchId && !r.is_terminal) {
      await navigateTo(`/docs/imports/${encodeURIComponent(batchId)}`)
    } else {
      await reloadActive()
    }
  } catch (caught) {
    try {
      const clientId = isActiveFilterValue(importClientId.value)
        ? Number(importClientId.value)
        : (isActiveFilterValue(filters.client_id) ? Number(filters.client_id) : null)
      const res = await api.documents.import(importFiles.value, clientId)
      const r = res.data
      toast.add({
        title: `Importação: ${r.imported} ok, ${r.skipped} duplicados, ${r.errors} erros`,
        color: r.errors && !r.imported ? 'error' : 'success'
      })
      importOpen.value = false
      importFiles.value = []
      await reloadActive()
    } catch {
      toast.add({ title: apiErrorMessage(caught, 'Falha ao importar XML.'), color: 'error' })
    }
  } finally {
    importing.value = false
  }
}

async function exportCurrentFilter() {
  if (!canCreateExport.value || exporting.value) return
  if (!kindExportAvailable.value) {
    toast.add({
      title: 'Exportação indisponível para este tipo',
      description: `A exportação de ${documentKindLabel(filters.kind)} ainda não está disponível. Use NFS-e ou Todos.`,
      color: 'warning'
    })
    return
  }
  if (!hasExportableCatalogFilters(filters)) {
    toast.add({
      title: 'Defina ao menos um filtro exportável',
      description: 'Use cliente, competência, período, papel, situação ou CNPJ — ou selecione linhas. A busca livre sozinha não gera ZIP.',
      color: 'warning'
    })
    return
  }
  exporting.value = true
  try {
    const job = (await api.exports.create({
      filters: buildExportFiltersFromCatalog(),
      include_events: false
    })).data
    toast.add({
      title: 'Exportação solicitada',
      description: `Job #${job.id} em processamento. Acompanhe em Exportações.`,
      color: 'success',
      actions: [{
        label: 'Abrir Exportações',
        onClick: async () => {
          await navigateTo('/exports')
        }
      }]
    })
  } catch (caught) {
    toast.add({ title: apiErrorMessage(caught, 'Não foi possível exportar.'), color: 'error' })
  } finally {
    exporting.value = false
  }
}

async function exportSelection() {
  if (!canCreateExport.value || exporting.value) return
  const keys = [...selectedKeys.value]
  if (!keys.length) {
    toast.add({ title: 'Nenhuma nota selecionada', color: 'warning' })
    return
  }
  if (keys.length > MAX_EXPORT_KEYS) {
    toast.add({
      title: `Máximo de ${MAX_EXPORT_KEYS} notas por seleção`,
      description: 'Reduza a seleção ou use Exportar filtro.',
      color: 'warning'
    })
    return
  }
  exporting.value = true
  try {
    const job = (await api.exports.create({
      filters: { access_keys: keys },
      include_events: false
    })).data
    toast.add({
      title: 'Exportação da seleção solicitada',
      description: `${keys.length} nota(s) · job #${job.id}.`,
      color: 'success',
      actions: [{
        label: 'Abrir Exportações',
        onClick: async () => {
          await navigateTo('/exports')
        }
      }]
    })
    selectedKeys.value = []
  } catch (caught) {
    toast.add({ title: apiErrorMessage(caught, 'Não foi possível exportar a seleção.'), color: 'error' })
  } finally {
    exporting.value = false
  }
}

onMounted(async () => {
  const legacyDocumentView = route.query.view === 'document' || route.query.view === 'nfs'
  if (Object.keys(route.query).length) {
    const currentPath = route.path
    const cleanPath = selectedAccessKey.value
      ? `/docs/${selectedAccessKey.value}`
      : legacyDocumentView
        ? '/docs/catalog'
        : currentPath
    await router.replace(cleanPath)
    if (cleanPath !== currentPath) return
  }
  // Deep link de detalhe: o chrome do modal hidrata via GET da nota.
  // Filtros de sessão continuam para /docs e /docs/catalog (estado local).
  if (isActiveFilterValue(filters.client_id)) {
    await onClientChange()
  }
  await loadClients()
  await reloadActive()
})
</script>

<template>
  <!--
    Arquétipo customers.vue. Views por cliente | catálogo no sidebar (Documentos).
    Tabelas com :ui do template.
  -->
  <UDashboardPanel id="docs" class="min-w-0">
    <template #header>
      <UDashboardNavbar data-testid="page-navbar" title="Documentos">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
        <template #trailing>
          <UBadge
            :label="view === 'client' ? String(byClientTotal) : String(listTotal || notes.length)"
            variant="subtle"
          />
        </template>
        <template #right>
          <UTooltip text="Atualizar">
            <UButton
              icon="i-lucide-refresh-cw"
              color="neutral"
              variant="ghost"
              square
              aria-label="Atualizar catálogo de documentos"
              :loading="loading"
              @click="reloadActive"
            />
          </UTooltip>
          <UButton
            to="/docs/imports"
            icon="i-lucide-history"
            label="Histórico"
            color="neutral"
            variant="ghost"
            size="sm"
            aria-label="Histórico de lotes de importação"
          />
          <UButton
            v-if="canImportDocuments && view === 'document'"
            icon="i-lucide-upload"
            label="Importar saídas"
            color="neutral"
            variant="outline"
            aria-label="Importar XML ou ZIP de saídas"
            @click="() => { importOpen = true }"
          />
          <UButton
            v-if="canCreateExport && view === 'document'"
            icon="i-lucide-download"
            label="Exportar filtro"
            color="primary"
            :loading="exporting"
            :disabled="exporting || !kindExportAvailable"
            @click="exportCurrentFilter"
          />
        </template>
      </UDashboardNavbar>

      <UModal
        v-model:open="importOpen"
        title="Importar XML de saídas"
        description="NF-e 55 e NFC-e 65 · associação automática por emitente · lote assíncrono"
      >
        <template #body>
          <div class="space-y-4">
            <p id="import-limits-desc" class="text-sm text-muted">
              Envie um ou mais XML/ZIP. Sem cliente, cada item associa pelo CNPJ do emitente.
              Cliente selecionado só restringe (divergência = CLIENT_MISMATCH).
              Limites: até 50 arquivos e 20&nbsp;MiB compactados. Após o envio, o progresso
              sobrevive ao fechar este modal — use Importações.
            </p>
            <UFormField label="Cliente (restrição opcional)">
              <USelect
                v-model="importClientId"
                :items="[
                  { label: 'Associação automática por emitente', value: FILTER_ALL },
                  ...clients.map(c => ({
                    label: c.display_name || c.legal_name || c.name,
                    value: String(c.id)
                  }))
                ]"
                class="w-full"
                aria-label="Restringir lote a um cliente (opcional)"
              />
            </UFormField>
            <div
              class="rounded-lg border border-dashed p-4 transition-colors"
              :class="importDragOver ? 'border-primary bg-primary/5' : 'border-default'"
              role="group"
              aria-labelledby="import-drop-label"
              aria-describedby="import-limits-desc"
              @dragover.prevent="importDragOver = true"
              @dragleave.prevent="importDragOver = false"
              @drop="onImportDrop"
            >
              <p id="import-drop-label" class="mb-2 text-sm font-medium text-highlighted">
                Arraste XML/ZIP ou selecione pelo teclado
              </p>
              <input
                type="file"
                multiple
                accept=".xml,.zip,application/xml,application/zip"
                class="block w-full text-sm"
                aria-label="Selecionar arquivos XML ou ZIP para importar"
                @change="onImportFileChange"
              >
            </div>
            <ul
              v-if="importFiles.length"
              class="max-h-32 space-y-1 overflow-y-auto text-xs text-muted"
              aria-live="polite"
              aria-label="Arquivos selecionados"
            >
              <li
                v-for="(file, idx) in importFiles"
                :key="`${file.name}-${idx}`"
                class="flex items-center justify-between gap-2"
              >
                <span class="truncate">{{ file.name }} ({{ Math.round(file.size / 1024) }} KiB)</span>
                <UButton
                  size="xs"
                  color="neutral"
                  variant="ghost"
                  icon="i-lucide-x"
                  square
                  :aria-label="`Remover ${file.name}`"
                  @click="removeImportFile(idx)"
                />
              </li>
            </ul>
            <p class="text-xs" :class="importLimitExceeded ? 'text-error' : 'text-muted'">
              {{ importFiles.length }} arquivo(s) ·
              {{ (importTotalBytes / (1024 * 1024)).toFixed(2) }} MiB
              <span v-if="importLimitExceeded"> — limite excedido</span>
            </p>
            <UButton
              to="/docs/imports"
              color="neutral"
              variant="link"
              size="sm"
              label="Histórico de lotes"
              class="px-0"
            />
          </div>
        </template>
        <template #footer>
          <div class="flex justify-end gap-2">
            <UButton
              color="neutral"
              variant="ghost"
              label="Cancelar"
              @click="() => { importOpen = false }"
            />
            <UButton
              color="primary"
              label="Enviar lote"
              :loading="importing"
              :disabled="!importFiles.length || importLimitExceeded"
              aria-label="Enviar lote de importação"
              @click="submitImport"
            />
          </div>
        </template>
      </UModal>
    </template>

    <template #body>
      <!--
        Body: insights (HomeStats-like) → toolbar busca → tabela (customers).
      -->
      <div class="flex w-full flex-col gap-4 sm:gap-5">
        <NotesInsightsBar
          :insights="insights"
          :loading="insightsLoading"
          :active-queue="triageQueue"
          @select="onTriageSelect"
        />

        <NotesFilters
          v-model:filters="filters"
          v-model:operational-filter="clientOperationalFilter"
          :clients="clients"
          :establishments="establishments"
          :loading-filters="loadingFilters"
          :view="view"
          :selected-count="selectedKeys.length"
          :can-export="canCreateExport && view === 'document' && kindExportAvailable"
          :exporting="exporting"
          @apply="applyFilters"
          @reset="resetFilters"
          @client-change="onClientChange"
          @export-selection="exportSelection"
        />

        <NotesByClient
          v-if="view === 'client'"
          :rows="byClientRows"
          :loading="loading"
          :error="loadError"
          :page="byClientPage"
          :per-page="byClientPerPage"
          :total="byClientTotal"
          :last-page="byClientLastPage"
          @open-client="openClientNotes"
          @open-client-detail="openClientDetail"
          @update:page="onByClientPageChange"
          @update:per-page="onByClientPerPageChange"
          @retry="loadByClient"
        />

        <template v-else>
          <UAlert
            v-if="!kindCaptureAvailable && !loading"
            color="info"
            variant="subtle"
            icon="i-lucide-info"
            :title="`Captura de ${documentKindLabel(filters.kind)} ainda não disponível`"
            :description="kindCaptureUnavailableHint"
            class="shrink-0"
          />

          <NotesCatalog
            v-model:selected-keys="selectedKeys"
            :notes="notes"
            :loading="loading"
            :error="loadError"
            :selected-access-key="selectedAccessKey"
            :page-size="pageSize"
            :total="listTotal"
            :has-more="!!nextCursor"
            :selectable="canCreateExport && kindExportAvailable"
            @select="selectNote"
            @load-more="load(false)"
            @update:page-size="onPageSizeChange"
            @retry="load(true)"
          />
        </template>
      </div>
    </template>
  </UDashboardPanel>

  <NotesDetailModal
    v-model:open="isDetailOpen"
    :access-key="selectedAccessKey"
    :preview="selectedPreview"
  />
</template>

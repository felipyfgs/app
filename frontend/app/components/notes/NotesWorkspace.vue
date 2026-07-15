<script setup lang="ts">
/**
 * Posto operacional Documentos (`/docs`) — tabela densa (customers) + tabs
 * + detalhe em NotesDetailModal + export. Base da antiga tela de notas.
 * Fonte: .reference/nuxt-dashboard-template/app/pages/customers.vue + settings.vue
 */
import { documentKindLabel, isDocumentKindCaptureAvailable } from '~/utils/documentKinds'
import type { NavigationMenuItem } from '@nuxt/ui'
import type { Client, Establishment, ExportFilters, NfseNote, NoteClientAggregate, NotesInsights } from '~/types/api'
import type { NoteListParams } from '~/composables/useApi'
import {
  activeTriageQueue,
  applyTriageQueue,
  catalogToExportFilters,
  emptyNotesFilters,
  FILTER_ALL,
  filtersFromQuery,
  filtersToQuery,
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
const { canCreateExport, canImportDocuments } = useDashboard()

const notes = ref<NfseNote[]>([])
const byClientRows = ref<NoteClientAggregate[]>([])
const clients = ref<Client[]>([])
const establishments = ref<Establishment[]>([])
const insights = ref<NotesInsights | null>(null)
const insightsLoading = ref(false)
const nextCursor = ref<string | null>(null)
/** Total no escopo dos filtros (meta.total da API). */
const listTotal = ref(0)
/** Linhas por página (enviado como `limit`; API 1–100). */
const pageSize = ref(25)
/** Página atual (1-based) — UPagination do template. */
const currentPage = ref(1)
/**
 * Cursor de início de cada página conhecida.
 * Página 1 = null; página N+1 = next_cursor retornado ao carregar N.
 */
const pageCursors = ref<Record<number, string | null>>({ 1: null })
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

const filters = reactive<NotesFilterState>(emptyNotesFilters())
const initial = filtersFromQuery(route.query as Record<string, unknown>)
Object.assign(filters, initial.filters)
const view = ref<NotesViewMode>(initial.view)

const selectedAccessKey = computed(() =>
  typeof route.params.accessKey === 'string' ? route.params.accessKey : null
)

const selectedPreview = computed(() =>
  notes.value.find(n => n.access_key === selectedAccessKey.value) || null
)

/** Modal de detalhe (substitui painel lateral / slideover). */
const isDetailOpen = computed({
  get: () => !!selectedAccessKey.value,
  set: (open: boolean) => {
    if (!open) closeDetail()
  }
})

/** Tabs: 1º Clientes · 2º NFS-e nacional (default = Clientes). */
const viewLinks = computed((): NavigationMenuItem[][] => [[
  {
    label: 'Clientes',
    icon: 'i-lucide-building-2',
    active: view.value === 'client',
    onSelect: () => setView('client')
  },
  {
    label: 'Documentos',
    icon: 'i-lucide-file-stack',
    active: view.value === 'document',
    onSelect: () => setView('document')
  }
]])

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
  currentPage.value = 1
  pageCursors.value = { 1: null }
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

async function syncRouteQuery() {
  const query = filtersToQuery(filters, undefined, view.value)
  const path = selectedAccessKey.value ? `/docs/${selectedAccessKey.value}` : '/docs'
  await router.replace({ path, query })
}

async function setView(next: NotesViewMode) {
  if (view.value === next) return
  view.value = next
  selectedKeys.value = []
  await syncRouteQuery()
  await reloadActive()
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
  // Fila de documento força aba NFS-e (exceto "all" na aba clientes)
  if (nextQueue !== 'all' && view.value !== 'document') {
    view.value = 'document'
  }
  await reloadActive()
}

/**
 * Carrega uma página do catálogo (substitui a grade — não acumula).
 * API é cursor-based; ao pular páginas, descobre cursores intermediários.
 */
async function loadPage(page: number): Promise<void> {
  const target = Math.max(1, page)
  loading.value = true
  try {
    // Preenche pageCursors até a página alvo (salto na UPagination).
    let guard = 0
    while (pageCursors.value[target] === undefined && guard < 200) {
      guard += 1
      const known = Object.keys(pageCursors.value).map(Number).sort((a, b) => a - b)
      const last = known[known.length - 1] ?? 1
      if (last >= target) break
      if (pageCursors.value[last + 1] !== undefined) continue

      const startCursor = last === 1 ? null : pageCursors.value[last]
      if (last !== 1 && !startCursor) break

      const probe = await api.documents.list(queryParams(startCursor))
      listTotal.value = probe.meta.total ?? listTotal.value
      if (probe.meta.next_cursor) {
        pageCursors.value = { ...pageCursors.value, [last + 1]: probe.meta.next_cursor }
      } else {
        notes.value = probe.data
        nextCursor.value = null
        currentPage.value = last
        loadError.value = null
        await syncRouteQuery()
        return
      }
    }

    const cursorForRequest = target === 1 ? null : pageCursors.value[target]
    if (target !== 1 && !cursorForRequest) {
      // Página fora do alcance — recua para a última conhecida.
      const known = Object.keys(pageCursors.value).map(Number)
      const fallback = known.length ? Math.max(...known) : 1
      if (fallback !== target) {
        await loadPage(fallback)
      }
      return
    }

    const response = await api.documents.list(queryParams(cursorForRequest))
    notes.value = response.data
    nextCursor.value = response.meta.next_cursor
    listTotal.value = response.meta.total
      ?? ((target - 1) * pageSize.value + response.data.length)
    if (response.meta.next_cursor) {
      pageCursors.value = {
        ...pageCursors.value,
        [target + 1]: response.meta.next_cursor
      }
    }
    currentPage.value = target
    loadError.value = null
    await syncRouteQuery()
  } catch (caught) {
    loadError.value = apiErrorMessage(caught, 'Erro ao listar documentos.')
    toast.add({ title: loadError.value, color: 'error' })
  } finally {
    loading.value = false
  }
}

async function load(reset = false) {
  if (reset) {
    resetPagination()
    selectedKeys.value = []
  }
  await loadPage(reset ? 1 : currentPage.value)
}

async function onPageChange(page: number) {
  if (page === currentPage.value || loading.value) return
  selectedKeys.value = []
  await loadPage(page)
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
    const response = await api.documents.byClient(queryParams())
    byClientRows.value = response.data
    await syncRouteQuery()
  } catch (caught) {
    loadError.value = apiErrorMessage(caught, 'Erro ao agregar por empresa.')
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
  reloadActive()
}

async function applyFilters() {
  selectedKeys.value = []
  await reloadActive()
}

async function selectNote(note: NfseNote) {
  const query = filtersToQuery(filters, undefined, view.value)
  await router.push({ path: `/docs/${note.access_key}`, query })
}

async function closeDetail() {
  const query = filtersToQuery(filters, undefined, view.value)
  await router.replace({ path: '/docs', query })
}

async function openClientNotes(row: NoteClientAggregate) {
  filters.client_id = String(row.client_id)
  filters.establishment_id = FILTER_ALL
  view.value = 'document'
  selectedKeys.value = []
  await onClientChange()
  await load(true)
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
  // Deep-link de nota força aba NFS-e nacional
  if (selectedAccessKey.value && view.value !== 'document') {
    view.value = 'document'
  }
  if (isActiveFilterValue(filters.client_id)) {
    await onClientChange()
  }
  await loadClients()
  await reloadActive()
})
</script>

<template>
  <!--
    Arquétipo customers.vue + tabs settings (clients.vue).
    Tabs: 1º Clientes · 2º NFS-e nacional. Tabelas com :ui do template.
  -->
  <UDashboardPanel id="docs" class="min-w-0">
    <template #header>
      <UDashboardNavbar data-testid="page-navbar" title="Documentos">
        <template #leading>
          <UDashboardSidebarCollapse />
        </template>
        <template #trailing>
          <UBadge
            :label="view === 'client' ? String(byClientRows.length) : String(notes.length)"
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

      <UDashboardToolbar data-testid="docs-view-tabs">
        <UNavigationMenu
          :items="viewLinks"
          highlight
          class="-mx-1 flex-1"
        />
      </UDashboardToolbar>
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
          @open-client="openClientNotes"
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
            :page="currentPage"
            :page-size="pageSize"
            :total="listTotal"
            :selectable="canCreateExport && kindExportAvailable"
            @select="selectNote"
            @update:page="onPageChange"
            @update:page-size="onPageSizeChange"
            @retry="load(true)"
          />
        </template>
      </div>
    </template>
  </UDashboardPanel>

  <!--
    Detalhe em NotesDetailModal (slots canônicos UModal + footer de ações).
    URL canônica /docs/:accessKey; /notes redireciona.
  -->
  <NotesDetailModal
    v-model:open="isDetailOpen"
    :access-key="selectedAccessKey"
    :preview="selectedPreview"
  />
</template>

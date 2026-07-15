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

const kindCaptureAvailable = computed(() => {
  if (filters.kind === FILTER_ALL) return true
  const runtimeValue = notes.value.find(note => note.kind === filters.kind)?.capture_available
  return runtimeValue ?? isDocumentKindCaptureAvailable(filters.kind)
})
const kindExportAvailable = computed(() => filters.kind === FILTER_ALL || filters.kind === 'NFSE')

function queryParams(cursor?: string | null): NoteListParams {
  return {
    limit: 25,
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

async function load(reset = false) {
  const cursorForRequest = reset ? null : nextCursor.value
  if (reset) {
    nextCursor.value = null
    selectedKeys.value = []
  }
  loading.value = true
  try {
    const response = await api.documents.list(queryParams(cursorForRequest))
    notes.value = reset ? response.data : [...notes.value, ...response.data]
    nextCursor.value = response.meta.next_cursor
    loadError.value = null
    await syncRouteQuery()
  } catch (caught) {
    loadError.value = apiErrorMessage(caught, 'Erro ao listar documentos.')
    toast.add({ title: loadError.value, color: 'error' })
  } finally {
    loading.value = false
  }
}

async function loadMore() {
  if (!nextCursor.value) return
  loading.value = true
  try {
    const response = await api.documents.list(queryParams(nextCursor.value))
    notes.value = [...notes.value, ...response.data]
    nextCursor.value = response.meta.next_cursor
  } catch (caught) {
    toast.add({ title: apiErrorMessage(caught, 'Erro ao carregar mais documentos.'), color: 'error' })
  } finally {
    loading.value = false
  }
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

async function submitImport() {
  if (!canImportDocuments.value || importing.value) return
  if (!importFiles.value.length) {
    toast.add({ title: 'Selecione ao menos um XML ou ZIP', color: 'warning' })
    return
  }
  importing.value = true
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
  } catch (caught) {
    toast.add({ title: apiErrorMessage(caught, 'Falha ao importar XML.'), color: 'error' })
  } finally {
    importing.value = false
  }
}

function onImportFileChange(event: Event) {
  const input = event.target as HTMLInputElement
  importFiles.value = input.files ? Array.from(input.files) : []
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
            v-if="canImportDocuments && view === 'document'"
            icon="i-lucide-upload"
            label="Importar saídas"
            color="neutral"
            variant="outline"
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

      <UModal v-model:open="importOpen" title="Importar XML de saídas">
        <template #body>
          <div class="space-y-4">
            <p class="text-sm text-muted">
              Envie XML (procNFe) ou ZIP de NF-e/NFC-e emitidas pelo cliente.
              O DistDFe não entrega a própria nota ao emitente — o import fecha essa lacuna.
            </p>
            <UFormField label="Cliente (opcional, valida emitente)">
              <USelect
                v-model="importClientId"
                :items="[
                  { label: 'Sem vínculo de cliente', value: FILTER_ALL },
                  ...clients.map(c => ({
                    label: c.display_name || c.legal_name || c.name,
                    value: String(c.id)
                  }))
                ]"
                class="w-full"
              />
            </UFormField>
            <UFormField label="Arquivos XML ou ZIP">
              <input
                type="file"
                multiple
                accept=".xml,.zip,application/xml,application/zip"
                class="block w-full text-sm"
                @change="onImportFileChange"
              >
            </UFormField>
            <p v-if="importFiles.length" class="text-xs text-muted">
              {{ importFiles.length }} arquivo(s) selecionado(s)
            </p>
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
              label="Importar"
              :loading="importing"
              :disabled="!importFiles.length"
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
            description="Hoje o catálogo captura NFS-e via ADN. NF-e, NFC-e, CT-e e MDF-e entram na mesma tela quando a fonte SEFAZ for ligada."
            class="shrink-0"
          />

          <NotesCatalog
            v-model:selected-keys="selectedKeys"
            :notes="notes"
            :loading="loading"
            :error="loadError"
            :selected-access-key="selectedAccessKey"
            :next-cursor="nextCursor"
            :selectable="canCreateExport && kindExportAvailable"
            @select="selectNote"
            @load-more="loadMore"
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

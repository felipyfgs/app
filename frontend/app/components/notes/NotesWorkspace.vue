<script setup lang="ts">
import { breakpointsTailwind } from '@vueuse/core'
import type { Client, Establishment, NfseNote } from '~/types/api'
import type { NoteListParams } from '~/composables/useApi'
import {
  emptyNotesFilters,
  FILTER_ALL,
  filtersFromQuery,
  filtersToQuery,
  isActiveFilterValue,
  type NotesFilterState
} from '~/utils/notes-filters'

const api = useApi()
const route = useRoute()
const router = useRouter()
const toast = useToast()
const notes = ref<NfseNote[]>([])
const clients = ref<Client[]>([])
const establishments = ref<Establishment[]>([])
const nextCursor = ref<string | null>(null)
const loading = ref(false)
const loadError = ref<string | null>(null)
const loadingFilters = ref(false)
const filters = reactive<NotesFilterState>(emptyNotesFilters())
const initial = filtersFromQuery(route.query as Record<string, unknown>)
Object.assign(filters, initial.filters)

const selectedAccessKey = computed(() =>
  typeof route.params.accessKey === 'string' ? route.params.accessKey : null
)

const selectedPreview = computed(() =>
  notes.value.find(n => n.access_key === selectedAccessKey.value) || null
)

const breakpoints = useBreakpoints(breakpointsTailwind)
const isMobile = breakpoints.smaller('lg')

const isDetailOpen = computed({
  get: () => !!selectedAccessKey.value,
  set: (open: boolean) => {
    if (!open) {
      closeDetail()
    }
  }
})

function queryParams(cursor?: string | null): NoteListParams {
  return {
    limit: 25,
    ...(isActiveFilterValue(filters.access_key) ? { access_key: filters.access_key } : {}),
    ...(isActiveFilterValue(filters.client_id) ? { client_id: Number(filters.client_id) } : {}),
    ...(isActiveFilterValue(filters.establishment_id) ? { establishment_id: Number(filters.establishment_id) } : {}),
    ...(isActiveFilterValue(filters.issuer_cnpj) ? { issuer_cnpj: filters.issuer_cnpj } : {}),
    ...(isActiveFilterValue(filters.taker_cnpj) ? { taker_cnpj: filters.taker_cnpj } : {}),
    ...(isActiveFilterValue(filters.fiscal_role) ? { fiscal_role: filters.fiscal_role as NoteListParams['fiscal_role'] } : {}),
    ...(isActiveFilterValue(filters.competence) ? { competence: filters.competence } : {}),
    ...(isActiveFilterValue(filters.issued_from) ? { issued_from: filters.issued_from } : {}),
    ...(isActiveFilterValue(filters.issued_to) ? { issued_to: filters.issued_to } : {}),
    ...(isActiveFilterValue(filters.status) ? { status: filters.status } : {}),
    ...(cursor ? { cursor } : {})
  }
}

async function syncRouteQuery(cursor?: string | null) {
  const query = filtersToQuery(filters, cursor || undefined)
  await router.replace({ path: selectedAccessKey.value ? `/notes/${selectedAccessKey.value}` : '/notes', query })
}

async function load(reset = false) {
  const cursorForRequest = reset ? null : nextCursor.value
  if (reset) {
    nextCursor.value = null
  }
  loading.value = true
  try {
    const response = await api.notes.list(queryParams(cursorForRequest))
    notes.value = reset ? response.data : [...notes.value, ...response.data]
    nextCursor.value = response.meta.next_cursor
    loadError.value = null
    if (reset) {
      await syncRouteQuery(null)
    } else if (response.meta.next_cursor) {
      // Não sobrescreve a seleção; só mantém filtros.
      await syncRouteQuery(null)
    }
  } catch (caught) {
    loadError.value = apiErrorMessage(caught, 'Erro ao listar notas.')
    toast.add({ title: loadError.value, color: 'error' })
  } finally {
    loading.value = false
  }
}

async function loadMore() {
  if (!nextCursor.value) {
    return
  }
  loading.value = true
  try {
    const response = await api.notes.list(queryParams(nextCursor.value))
    notes.value = [...notes.value, ...response.data]
    nextCursor.value = response.meta.next_cursor
  } catch (caught) {
    toast.add({ title: apiErrorMessage(caught, 'Erro ao carregar mais notas.'), color: 'error' })
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
  if (!isActiveFilterValue(filters.client_id)) {
    return
  }
  try {
    establishments.value = (await api.clients.get(Number(filters.client_id))).data.establishments || []
  } catch {
    establishments.value = []
  }
}

function resetFilters() {
  Object.assign(filters, emptyNotesFilters())
  establishments.value = []
  load(true)
}

async function selectNote(note: NfseNote) {
  const query = filtersToQuery(filters)
  await router.push({ path: `/notes/${note.access_key}`, query })
}

async function closeDetail() {
  const query = filtersToQuery(filters)
  await router.push({ path: '/notes', query })
}

onMounted(async () => {
  if (isActiveFilterValue(filters.client_id)) {
    await onClientChange()
  }
  await Promise.all([loadClients(), load(true)])
})
</script>

<template>
  <!--
    Mestre–detalhe como inbox.vue do template:
    painel esquerdo ~25–30% (lista) + detalhe flex-1 (ou empty state).
  -->
  <UDashboardPanel
    id="notes-master"
    :default-size="28"
    :min-size="22"
    :max-size="34"
    resizable
  >
    <UDashboardNavbar data-testid="page-navbar" title="Notas fiscais">
      <template #leading>
        <UDashboardSidebarCollapse />
      </template>
      <template #trailing>
        <UBadge :label="String(notes.length)" variant="subtle" />
      </template>
      <template #right>
        <UTooltip text="Atualizar catálogo">
          <UButton
            icon="i-lucide-refresh-cw"
            color="neutral"
            variant="ghost"
            square
            aria-label="Atualizar catálogo de notas"
            :loading="loading"
            @click="load(true)"
          />
        </UTooltip>
      </template>
    </UDashboardNavbar>

    <NotesFilters
      v-model:filters="filters"
      :clients="clients"
      :establishments="establishments"
      :loading-filters="loadingFilters"
      @apply="load(true)"
      @reset="resetFilters"
      @client-change="onClientChange"
    />

    <NotesCatalog
      :notes="notes"
      :loading="loading"
      :error="loadError"
      :selected-access-key="selectedAccessKey"
      :next-cursor="nextCursor"
      @select="selectNote"
      @load-more="loadMore"
      @retry="load(true)"
    />
  </UDashboardPanel>

  <!-- Detalhe desktop (irmão do painel, como InboxMail) -->
  <NotesDetail
    v-if="selectedAccessKey && !isMobile"
    :access-key="selectedAccessKey"
    :preview="selectedPreview"
    show-close
    class="hidden min-h-0 flex-1 lg:flex"
    @close="closeDetail"
  />
  <div
    v-else-if="!selectedAccessKey"
    class="hidden flex-1 items-center justify-center lg:flex"
  >
    <div class="text-center">
      <UIcon name="i-lucide-inbox" class="mx-auto size-32 text-dimmed" aria-hidden="true" />
      <p class="mt-4 text-sm text-muted">
        Selecione uma nota no catálogo
      </p>
    </div>
  </div>

  <!-- Mobile: slideover (como inbox) -->
  <ClientOnly>
    <USlideover
      v-if="isMobile"
      v-model:open="isDetailOpen"
      :ui="{ content: 'max-w-full' }"
    >
      <template #content>
        <NotesDetail
          v-if="selectedAccessKey"
          :access-key="selectedAccessKey"
          :preview="selectedPreview"
          show-close
          @close="closeDetail"
        />
      </template>
    </USlideover>
  </ClientOnly>
</template>

/**
 * Carteira fiscal tenant-aware: overview + lista incremental do read model.
 * - Filtros e ordenação na URL; paginação numérica fica encapsulada na API
 * - Sem fallback sintético em erro/vazio
 * - Aborta/descarta requests quando o office ou módulo muda
 * - Ordenação é sempre server-side para não reordenar apenas o lote carregado
 */
import type { InjectionKey, Ref } from 'vue'
import type { LocationQueryRaw } from 'vue-router'
import type {
  FiscalKpiKey,
  FiscalModuleClientRowFor,
  FiscalModuleOverview,
  FiscalModulePortfolioFilters,
  FiscalPortfolioModuleKey
} from '~/types/fiscal-modules'
import { fiscalKpiSituationFilter, isSyntheticFiscalOrigin } from '~/types/fiscal-modules'
import { laravelPageBatch, usePagedTable } from '~/composables/usePagedTable'

export interface UseFiscalModulePortfolioOptions {
  /** Submódulo controlado pela página (tabs). */
  submodule?: Ref<string>
  /** delivery_status (declarações). */
  deliveryStatus?: Ref<string>
  perPage?: number
  /** Se false, não carrega overview (default true). */
  loadOverview?: boolean
  /** Auto-fetch no mount (default true). */
  immediate?: boolean
}

export interface FiscalModuleSortEntry {
  id: string
  desc: boolean
}

export type FiscalModuleSortingState = FiscalModuleSortEntry[]

export interface MonitoringModuleTableContext {
  sorting: Ref<FiscalModuleSortingState>
  hasMore: Ref<boolean>
  pending: Ref<boolean>
  pendingMore: Ref<boolean>
  error: Ref<string | null>
  loadMore: () => Promise<void>
  retry: () => Promise<void>
}

/** Contexto privado entre a carteira e sua casca de tabela, sem repetir props em seis páginas. */
export const FISCAL_MODULE_TABLE_CONTEXT: InjectionKey<MonitoringModuleTableContext>
  = Symbol('fiscal-module-table-context')

const SORT_COLUMN_TO_API = Object.freeze<Record<string, NonNullable<FiscalModulePortfolioFilters['sort']>>>({
  client: 'legal_name',
  competence: 'competence',
  situation: 'situation',
  consulted: 'last_consulted_at',
  observed: 'last_consulted_at',
  synced: 'last_consulted_at',
  id: 'id'
})

const SORT_API_TO_COLUMN = Object.freeze<Record<string, string>>({
  legal_name: 'client',
  display_name: 'client',
  competence: 'competence',
  situation: 'situation',
  last_consulted_at: 'consulted',
  id: 'id'
})

export function fiscalModuleSortKey(columnId: string | null | undefined) {
  return columnId ? SORT_COLUMN_TO_API[columnId] : undefined
}

function readQueryString(value: unknown): string {
  if (Array.isArray(value)) return String(value[0] ?? '')
  return value == null ? '' : String(value)
}

function sortingFromQuery(sort: unknown, direction: unknown): FiscalModuleSortingState {
  const querySort = readQueryString(sort)
  // A URL preserva o id visual (ex.: `observed`); o request o traduz depois.
  // Também aceitamos chaves antigas da API para links já compartilhados.
  const id = fiscalModuleSortKey(querySort)
    ? querySort
    : SORT_API_TO_COLUMN[querySort] ?? 'client'
  return [{ id, desc: readQueryString(direction).toLowerCase() === 'desc' }]
}

function sameSorting(a: FiscalModuleSortingState, b: FiscalModuleSortingState): boolean {
  return a[0]?.id === b[0]?.id && Boolean(a[0]?.desc) === Boolean(b[0]?.desc)
}

export function useFiscalModulePortfolio<M extends FiscalPortfolioModuleKey>(
  moduleKey: MaybeRefOrGetter<M>,
  options: UseFiscalModulePortfolioOptions = {}
) {
  const api = useApi()
  const route = useRoute()
  const router = useRouter()
  const { sessionEpoch } = useDashboard()

  // `page`/`lastPage` permanecem no retorno por compatibilidade das páginas,
  // mas não são estado de navegação nem reaparecem na URL.
  const page = ref(1)
  /** pageSize alinhado ao template customers (10). */
  const perPage = ref(options.perPage ?? 10)
  const lastPage = ref(1)
  const q = ref(readQueryString(route.query.q))
  const situation = ref(readQueryString(route.query.situation) || 'all')
  const competence = ref(readQueryString(route.query.competence))
  const submodule = options.submodule ?? ref(readQueryString(route.query.submodule))
  const deliveryStatus = options.deliveryStatus
    ?? ref(readQueryString(route.query.delivery_status) || 'all')
  const clientId = ref(readQueryString(route.query.client_id))
  const sorting = ref<FiscalModuleSortingState>(
    sortingFromQuery(route.query.sort, route.query.sort_direction)
  )

  const overviewLoading = ref(false)
  const overviewError = ref<string | null>(null)
  const overview = shallowRef<FiscalModuleOverview<M> | null>(null)
  const lastValidAt = ref<string | null>(null)
  const hasLoadedOnce = ref(false)
  const manualRefreshing = ref(false)

  let overviewSeq = 0
  let clientsLoadSeq = 0

  function currentSort() {
    const selected = sorting.value[0]
    return {
      sort: fiscalModuleSortKey(selected?.id) ?? 'legal_name',
      sort_direction: selected?.desc ? 'desc' as const : 'asc' as const
    }
  }

  function buildFilters(requestPage = page.value): FiscalModulePortfolioFilters {
    const clientIdNum = Number(clientId.value)
    return {
      page: requestPage,
      per_page: perPage.value,
      q: q.value.trim() || undefined,
      situation: situation.value && situation.value !== 'all' ? situation.value : undefined,
      competence: competence.value.trim() || undefined,
      submodule:
        submodule.value && submodule.value !== 'all' && submodule.value.trim()
          ? submodule.value
          : undefined,
      delivery_status:
        deliveryStatus.value && deliveryStatus.value !== 'all'
          ? deliveryStatus.value
          : undefined,
      client_id:
        Number.isFinite(clientIdNum) && clientIdNum >= 1
          ? Math.floor(clientIdNum)
          : undefined,
      ...currentSort()
    }
  }

  const clientsFeed = usePagedTable<FiscalModuleClientRowFor<M>>({
    getKey: row => row.client_id,
    load: async ({ page: requestPage, signal }) => {
      const mod = toValue(moduleKey)
      const epoch = sessionEpoch.value
      const response = await api.fiscal.modules.clients(
        mod,
        buildFilters(requestPage),
        { signal }
      )

      if (signal.aborted || epoch !== sessionEpoch.value || mod !== toValue(moduleKey)) {
        throw new Error('Requisição da carteira cancelada após troca de contexto.')
      }

      const data = response.data ?? []
      if (data.some(row => row.module_key !== mod)) {
        throw new Error('Contrato incompatível: module_key da carteira não corresponde ao módulo.')
      }

      const meta = response.meta ?? response
      page.value = Number(meta.current_page ?? requestPage) || requestPage
      lastPage.value = Number(meta.last_page ?? page.value) || page.value
      if (typeof meta.per_page === 'number') perPage.value = meta.per_page
      lastValidAt.value = new Date().toISOString()
      hasLoadedOnce.value = true

      return laravelPageBatch(response)
    }
  })

  const rows = clientsFeed.rows
  const loading = clientsFeed.pendingInitial
  const refreshing = computed(() => manualRefreshing.value || clientsFeed.pendingMore.value)
  const total = computed(() => clientsFeed.total.value ?? rows.value.length)
  const loadError = computed(() => clientsFeed.error.value
    ? apiErrorMessage(clientsFeed.error.value, 'Falha ao carregar carteira do módulo.')
    : null)

  provide(FISCAL_MODULE_TABLE_CONTEXT, {
    sorting,
    hasMore: clientsFeed.hasMore,
    pending: clientsFeed.pending,
    pendingMore: clientsFeed.pendingMore,
    error: loadError,
    loadMore: clientsFeed.loadMore,
    retry: retryClients
  })

  const isSynthetic = computed(() =>
    isSyntheticFiscalOrigin(overview.value?.data_origin)
    || rows.value.some(row => isSyntheticFiscalOrigin(row.data_origin))
  )

  const dataOrigin = computed(() => overview.value?.data_origin ?? null)
  const counters = computed(() => overview.value?.counters ?? null)
  const totalClients = computed(() => overview.value?.total_clients ?? total.value)
  const hasRows = computed(() => rows.value.length > 0)
  const hasPreviousData = computed(() =>
    hasLoadedOnce.value && (hasRows.value || overview.value != null)
  )
  const isFiltered = computed(() => Boolean(
    q.value.trim()
    || (situation.value && situation.value !== 'all')
    || competence.value.trim()
    || (submodule.value && submodule.value !== 'all' && submodule.value.trim())
    || (deliveryStatus.value && deliveryStatus.value !== 'all')
    || clientId.value
  ))

  async function syncUrl(extra: LocationQueryRaw = {}) {
    const query: LocationQueryRaw = { ...route.query, ...extra }

    // Scroll incremental: página é detalhe interno da API, não navegação pública.
    delete query.page

    if (q.value.trim()) query.q = q.value.trim()
    else delete query.q

    if (situation.value && situation.value !== 'all') query.situation = situation.value
    else delete query.situation

    if (competence.value.trim()) query.competence = competence.value.trim()
    else delete query.competence

    if (submodule.value && submodule.value !== 'all' && submodule.value.trim()) {
      query.submodule = submodule.value
    } else {
      delete query.submodule
    }

    if (deliveryStatus.value && deliveryStatus.value !== 'all') {
      query.delivery_status = deliveryStatus.value
    } else {
      delete query.delivery_status
    }

    if (clientId.value) query.client_id = clientId.value
    else delete query.client_id

    const selected = sorting.value[0]
    const sort = fiscalModuleSortKey(selected?.id) ?? 'legal_name'
    const direction = selected?.desc ? 'desc' : 'asc'
    if (sort === 'legal_name' && direction === 'asc') {
      delete query.sort
      delete query.sort_direction
    } else {
      query.sort = selected?.id ?? 'client'
      query.sort_direction = direction
    }

    // Nunca confiar em office_id na URL da carteira.
    delete query.office_id

    await router.replace({ query })
  }

  function overviewStillCurrent(seq: number, epoch: number) {
    return epoch === sessionEpoch.value && seq === overviewSeq
  }

  async function loadOverview() {
    if (options.loadOverview === false) return
    const seq = ++overviewSeq
    const epoch = sessionEpoch.value
    overviewLoading.value = true
    overviewError.value = null
    try {
      const mod = toValue(moduleKey)
      const filters = buildFilters(1)
      const response = await api.fiscal.modules.overview(mod, {
        q: filters.q,
        situation: filters.situation,
        competence: filters.competence,
        submodule: filters.submodule,
        delivery_status: filters.delivery_status
      })
      if (!overviewStillCurrent(seq, epoch)) return

      const data = response.data
      if (data?.module_key && data.module_key !== mod) {
        throw new Error('Contrato incompatível: module_key do overview não corresponde ao módulo.')
      }
      overview.value = data
    } catch (caught) {
      if (!overviewStillCurrent(seq, epoch)) return
      overviewError.value = apiErrorMessage(caught, 'Falha ao carregar overview do módulo.')
      // Mantém overview anterior se já houver.
    } finally {
      if (overviewStillCurrent(seq, epoch)) overviewLoading.value = false
    }
  }

  async function loadClients(opts?: { silent?: boolean }) {
    const seq = ++clientsLoadSeq
    await syncUrl()
    if (seq !== clientsLoadSeq) return

    const preservePrevious = opts?.silent === true && hasLoadedOnce.value
    const previous = preservePrevious
      ? {
          rows: [...rows.value],
          total: clientsFeed.total.value,
          hasMore: clientsFeed.hasMore.value,
          page: page.value,
          lastPage: lastPage.value
        }
      : null

    await clientsFeed.resetAndLoad()

    // Refresh manual: se falhar e havia carteira válida, restaura rows locais.
    // total/page do feed permanecem os da última carga bem-sucedida no retry.
    if (previous && clientsFeed.error.value) {
      clientsFeed.rows.value = previous.rows
      clientsFeed.total.value = previous.total ?? previous.rows.length
      clientsFeed.page.value = previous.page
      page.value = previous.page
      lastPage.value = previous.lastPage
    }
  }

  async function retryClients() {
    if (hasLoadedOnce.value && rows.value.length) {
      await loadClients({ silent: true })
      return
    }

    await clientsFeed.retry()
  }

  /** Paginação template: troca de página recarrega o lote (não só o ref local). */
  async function setPage(next: number) {
    const target = Math.max(1, Math.floor(Number(next) || 1))
    page.value = target
    await clientsFeed.setPage(target)
    page.value = clientsFeed.page.value
    lastPage.value = clientsFeed.lastPage.value
    if (typeof clientsFeed.total.value === 'number') {
      // total já reativo via computed `total`
    }
  }

  async function load(opts?: { silent?: boolean }) {
    await Promise.all([loadOverview(), loadClients(opts)])
  }

  async function refresh() {
    manualRefreshing.value = true
    try {
      await load({ silent: true })
    } finally {
      manualRefreshing.value = false
    }
  }

  function resetPage() {
    page.value = 1
    lastPage.value = 1
  }

  function setSituationFromKpi(value: string | null) {
    situation.value = value || 'all'
    resetPage()
  }

  function selectKpi(key: FiscalKpiKey) {
    setSituationFromKpi(fiscalKpiSituationFilter(key))
  }

  function hydrateFromRoute() {
    q.value = readQueryString(route.query.q)
    situation.value = readQueryString(route.query.situation) || 'all'
    competence.value = readQueryString(route.query.competence)
    if (!options.submodule) submodule.value = readQueryString(route.query.submodule)
    if (!options.deliveryStatus) {
      deliveryStatus.value = readQueryString(route.query.delivery_status) || 'all'
    }
    clientId.value = readQueryString(route.query.client_id)
    sorting.value = sortingFromQuery(route.query.sort, route.query.sort_direction)
    resetPage()
  }

  let ready = false
  let syncingFromRoute = false

  watch(
    [q, situation, competence, submodule, deliveryStatus, clientId, sorting],
    () => {
      if (!ready || syncingFromRoute) return
      resetPage()
      void load()
    },
    { deep: true }
  )

  function clearForContextChange() {
    overviewSeq += 1
    clientsLoadSeq += 1
    clientsFeed.reset()
    overview.value = null
    page.value = 1
    lastPage.value = 1
    lastValidAt.value = null
    hasLoadedOnce.value = false
    manualRefreshing.value = false
    overviewError.value = null
  }

  watch(sessionEpoch, () => {
    // Troca de office aborta o request e limpa antes de recarregar: tenants não se misturam.
    clearForContextChange()
    if (ready) void load()
  })

  watch(
    () => toValue(moduleKey),
    (next, prev) => {
      if (next === prev) return
      clearForContextChange()
      if (ready) void load()
    }
  )

  // Browser back/forward: reidrata quando a query diverge do estado local.
  watch(
    () => route.query,
    () => {
      if (!ready) return
      const nextSorting = sortingFromQuery(route.query.sort, route.query.sort_direction)
      const nextQ = readQueryString(route.query.q)
      const nextSituation = readQueryString(route.query.situation) || 'all'
      const nextCompetence = readQueryString(route.query.competence)
      const nextSubmodule = readQueryString(route.query.submodule)
      const nextDelivery = readQueryString(route.query.delivery_status) || 'all'
      const nextClient = readQueryString(route.query.client_id)

      const diverged = nextQ !== q.value
        || nextSituation !== situation.value
        || nextCompetence !== competence.value
        || (!options.submodule && nextSubmodule !== submodule.value)
        || (!options.deliveryStatus && nextDelivery !== deliveryStatus.value)
        || nextClient !== clientId.value
        || !sameSorting(nextSorting, sorting.value)

      if (!diverged) return
      syncingFromRoute = true
      hydrateFromRoute()
      syncingFromRoute = false
      void load({ silent: true })
    },
    { deep: true }
  )

  if (options.immediate !== false) {
    onMounted(async () => {
      syncingFromRoute = true
      hydrateFromRoute()
      syncingFromRoute = false
      // Ativa os watchers antes do request para uma troca de office durante
      // o carregamento inicial abortar e já iniciar a carteira do novo tenant.
      ready = true
      await load()
    })
  } else {
    ready = true
  }

  return {
    page,
    perPage,
    total,
    lastPage,
    q,
    situation,
    competence,
    submodule,
    deliveryStatus,
    clientId,
    sorting,
    loading,
    refreshing,
    overviewLoading,
    loadError,
    overviewError,
    overview,
    rows,
    counters,
    totalClients,
    dataOrigin,
    isSynthetic,
    lastValidAt,
    hasLoadedOnce,
    hasRows,
    hasPreviousData,
    isFiltered,
    hasMore: clientsFeed.hasMore,
    pendingMore: clientsFeed.pendingMore,
    loadMore: clientsFeed.loadMore,
    setPage,
    retry: retryClients,
    load,
    loadClients,
    loadOverview,
    refresh,
    resetPage,
    setSituationFromKpi,
    selectKpi,
    syncUrl,
    buildFilters,
    hydrateFromRoute
  }
}

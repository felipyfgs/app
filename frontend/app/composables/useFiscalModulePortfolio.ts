/**
 * Carteira fiscal tenant-aware: overview + clients do read model.
 * - Filtros e página na URL
 * - Sem fallback sintético em erro/vazio
 * - Descarta resposta se o office mudar durante o request (sessionEpoch)
 * - Preserva última resposta válida em falhas de atualização
 */
import type { LocationQueryRaw } from 'vue-router'
import type {
  FiscalKpiKey,
  FiscalModuleClientRowFor,
  FiscalModuleOverview,
  FiscalPortfolioModuleKey
} from '~/types/fiscal-modules'
import { fiscalKpiSituationFilter, isSyntheticFiscalOrigin } from '~/types/fiscal-modules'

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

function asPositiveInt(value: unknown, fallback: number): number {
  const n = Number(value)
  return Number.isFinite(n) && n >= 1 ? Math.floor(n) : fallback
}

function readQueryString(value: unknown): string {
  if (Array.isArray(value)) return String(value[0] ?? '')
  return value == null ? '' : String(value)
}

export function useFiscalModulePortfolio<M extends FiscalPortfolioModuleKey>(
  moduleKey: MaybeRefOrGetter<M>,
  options: UseFiscalModulePortfolioOptions = {}
) {
  const api = useApi()
  const route = useRoute()
  const router = useRouter()
  const { sessionEpoch } = useDashboard()

  const page = ref(asPositiveInt(route.query.page, 1))
  const perPage = ref(options.perPage ?? 15)
  const total = ref(0)
  const lastPage = ref(1)
  const q = ref(readQueryString(route.query.q))
  const situation = ref(readQueryString(route.query.situation) || 'all')
  const competence = ref(readQueryString(route.query.competence))
  const submodule = options.submodule ?? ref(readQueryString(route.query.submodule))
  const deliveryStatus = options.deliveryStatus
    ?? ref(readQueryString(route.query.delivery_status) || 'all')
  const clientId = ref(readQueryString(route.query.client_id))

  const loading = ref(false)
  const refreshing = ref(false)
  const overviewLoading = ref(false)
  const loadError = ref<string | null>(null)
  const overviewError = ref<string | null>(null)

  const overview = shallowRef<FiscalModuleOverview<M> | null>(null)
  const rows = shallowRef<FiscalModuleClientRowFor<M>[]>([])
  const lastValidAt = ref<string | null>(null)
  const hasLoadedOnce = ref(false)

  let overviewSeq = 0
  let clientsSeq = 0

  const isSynthetic = computed(() =>
    isSyntheticFiscalOrigin(overview.value?.data_origin)
    || rows.value.some(r => isSyntheticFiscalOrigin(r.data_origin))
  )

  const dataOrigin = computed(() => overview.value?.data_origin ?? null)

  const counters = computed(() => overview.value?.counters ?? null)

  const totalClients = computed(() =>
    overview.value?.total_clients ?? total.value
  )

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

  function applyPaginator(payload: {
    meta?: { current_page?: number, last_page?: number, total?: number, per_page?: number }
    current_page?: number
    last_page?: number
    total?: number
    per_page?: number
  } | null | undefined) {
    if (!payload) return
    const meta = payload.meta || payload
    if (meta.current_page != null) {
      page.value = Number(meta.current_page) || page.value
    }
    if (meta.last_page != null) lastPage.value = Number(meta.last_page) || 1
    if (meta.total != null) total.value = Number(meta.total) || 0
    if (meta.per_page != null) perPage.value = Number(meta.per_page) || perPage.value
  }

  async function syncUrl(extra: LocationQueryRaw = {}) {
    const query: LocationQueryRaw = { ...route.query, ...extra }

    if (page.value > 1) query.page = String(page.value)
    else delete query.page

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

    // Nunca confiar em office_id na URL da carteira.
    delete query.office_id

    await router.replace({ query })
  }

  function buildFilters() {
    const clientIdNum = Number(clientId.value)
    return {
      page: page.value,
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
          : undefined
    }
  }

  function stillCurrent(seq: number, kind: 'overview' | 'clients', epoch: number) {
    if (epoch !== sessionEpoch.value) return false
    return kind === 'overview' ? seq === overviewSeq : seq === clientsSeq
  }

  async function loadOverview() {
    if (options.loadOverview === false) return
    const seq = ++overviewSeq
    const epoch = sessionEpoch.value
    overviewLoading.value = true
    overviewError.value = null
    try {
      const mod = toValue(moduleKey)
      const filters = buildFilters()
      const res = await api.fiscal.modules.overview(mod, {
        q: filters.q,
        situation: filters.situation,
        competence: filters.competence,
        submodule: filters.submodule,
        delivery_status: filters.delivery_status
      })
      if (!stillCurrent(seq, 'overview', epoch)) return

      const data = res.data
      if (data?.module_key && data.module_key !== mod) {
        throw new Error('Contrato incompatível: module_key do overview não corresponde ao módulo.')
      }
      overview.value = data
    } catch (caught) {
      if (!stillCurrent(seq, 'overview', epoch)) return
      overviewError.value = apiErrorMessage(caught, 'Falha ao carregar overview do módulo.')
      // Mantém overview anterior se já houver.
    } finally {
      if (stillCurrent(seq, 'overview', epoch)) overviewLoading.value = false
    }
  }

  async function loadClients(opts?: { silent?: boolean }) {
    const seq = ++clientsSeq
    const epoch = sessionEpoch.value
    const silent = opts?.silent === true && hasLoadedOnce.value

    if (silent) refreshing.value = true
    else loading.value = true
    loadError.value = null

    try {
      await syncUrl()
      const mod = toValue(moduleKey)
      const res = await api.fiscal.modules.clients(mod, buildFilters())
      if (!stillCurrent(seq, 'clients', epoch)) return

      const data = res.data || []
      if (data.some(r => r.module_key !== mod)) {
        throw new Error('Contrato incompatível: module_key da carteira não corresponde ao módulo.')
      }

      rows.value = data
      applyPaginator(res)
      lastValidAt.value = new Date().toISOString()
      hasLoadedOnce.value = true
    } catch (caught) {
      if (!stillCurrent(seq, 'clients', epoch)) return
      loadError.value = apiErrorMessage(caught, 'Falha ao carregar carteira do módulo.')
      // Keep last good: só zera se nunca houve sucesso.
      if (!hasLoadedOnce.value) {
        rows.value = []
        total.value = 0
        lastPage.value = 1
      }
    } finally {
      if (stillCurrent(seq, 'clients', epoch)) {
        loading.value = false
        refreshing.value = false
      }
    }
  }

  async function load(opts?: { silent?: boolean }) {
    await Promise.all([loadOverview(), loadClients(opts)])
  }

  async function refresh() {
    await load({ silent: true })
  }

  function resetPage() {
    page.value = 1
  }

  function setSituationFromKpi(value: string | null) {
    situation.value = value || 'all'
    resetPage()
  }

  function selectKpi(key: FiscalKpiKey) {
    setSituationFromKpi(fiscalKpiSituationFilter(key))
  }

  function hydrateFromRoute() {
    page.value = asPositiveInt(route.query.page, 1)
    q.value = readQueryString(route.query.q)
    situation.value = readQueryString(route.query.situation) || 'all'
    competence.value = readQueryString(route.query.competence)
    if (!options.submodule) {
      submodule.value = readQueryString(route.query.submodule)
    }
    if (!options.deliveryStatus) {
      deliveryStatus.value = readQueryString(route.query.delivery_status) || 'all'
    }
    clientId.value = readQueryString(route.query.client_id)
  }

  /** Evita double-fetch no mount e loops page↔paginator. */
  let ready = false
  let syncingFromRoute = false

  watch(page, () => {
    if (!ready || syncingFromRoute) return
    void loadClients()
  })

  watch([q, situation, competence, submodule, deliveryStatus, clientId], () => {
    if (!ready || syncingFromRoute) return
    resetPage()
    void load()
  })

  watch(sessionEpoch, () => {
    // Troca de office: descarta tudo e recarrega (sem misturar tenants).
    overviewSeq += 1
    clientsSeq += 1
    rows.value = []
    overview.value = null
    total.value = 0
    lastPage.value = 1
    lastValidAt.value = null
    hasLoadedOnce.value = false
    loadError.value = null
    overviewError.value = null
    if (!ready) return
    void load()
  })

  watch(
    () => toValue(moduleKey),
    (next, prev) => {
      if (next === prev) return
      overviewSeq += 1
      clientsSeq += 1
      rows.value = []
      overview.value = null
      total.value = 0
      lastPage.value = 1
      lastValidAt.value = null
      hasLoadedOnce.value = false
      loadError.value = null
      overviewError.value = null
      resetPage()
      if (!ready) return
      void load()
    }
  )

  // Browser back/forward: re-hidrata quando a query diverge do estado local.
  watch(
    () => route.query,
    () => {
      if (!ready) return
      const nextPage = asPositiveInt(route.query.page, 1)
      const nextQ = readQueryString(route.query.q)
      const nextSit = readQueryString(route.query.situation) || 'all'
      const nextComp = readQueryString(route.query.competence)
      const nextSub = readQueryString(route.query.submodule)
      const nextDelivery = readQueryString(route.query.delivery_status) || 'all'
      const nextClient = readQueryString(route.query.client_id)

      const diverged = nextPage !== page.value
        || nextQ !== q.value
        || nextSit !== situation.value
        || nextComp !== competence.value
        || nextSub !== submodule.value
        || nextDelivery !== deliveryStatus.value
        || nextClient !== clientId.value

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
      await load()
      ready = true
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

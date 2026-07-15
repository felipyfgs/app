/**
 * Paginação server-side + filtros na URL (padrão lista admin 15.6).
 * Nunca inventa dados: o caller preenche rows a partir da API.
 */
import type { LocationQueryRaw } from 'vue-router'

export interface ServerPageState {
  page: number
  perPage: number
  total: number
  lastPage: number
  q: string
  situation: string
  clientId: string
  competence: string
}

function asPositiveInt(value: unknown, fallback: number): number {
  const n = Number(value)
  return Number.isFinite(n) && n >= 1 ? Math.floor(n) : fallback
}

export function useServerPage(defaults?: Partial<ServerPageState>) {
  const route = useRoute()
  const router = useRouter()

  const page = ref(asPositiveInt(route.query.page, defaults?.page ?? 1))
  const perPage = ref(defaults?.perPage ?? 20)
  const total = ref(0)
  const lastPage = ref(1)
  const q = ref(String(route.query.q || defaults?.q || ''))
  const situation = ref(String(route.query.situation || defaults?.situation || 'all'))
  const clientId = ref(String(route.query.client_id || defaults?.clientId || ''))
  const competence = ref(String(route.query.competence || defaults?.competence || ''))

  const loading = ref(false)
  const loadError = ref<string | null>(null)

  function applyMeta(meta?: {
    current_page?: number
    last_page?: number
    total?: number
    per_page?: number
  } | null) {
    if (!meta) return
    if (typeof meta.current_page === 'number') page.value = meta.current_page
    if (typeof meta.last_page === 'number') lastPage.value = meta.last_page
    if (typeof meta.total === 'number') total.value = meta.total
    if (typeof meta.per_page === 'number') perPage.value = meta.per_page
  }

  /** Laravel LengthAwarePaginator no root ou em meta. */
  function applyPaginator(payload: Record<string, unknown> | null | undefined) {
    if (!payload) return
    const meta = (payload.meta as Record<string, unknown> | undefined) || payload
    applyMeta({
      current_page: Number(meta.current_page ?? page.value),
      last_page: Number(meta.last_page ?? lastPage.value),
      total: Number(meta.total ?? total.value),
      per_page: Number(meta.per_page ?? perPage.value)
    })
  }

  async function syncUrl(extra: LocationQueryRaw = {}) {
    const query: LocationQueryRaw = { ...route.query, ...extra }

    if (page.value > 1) query.page = String(page.value)
    else delete query.page

    if (q.value.trim()) query.q = q.value.trim()
    else delete query.q

    if (situation.value && situation.value !== 'all') query.situation = situation.value
    else delete query.situation

    if (clientId.value) query.client_id = clientId.value
    else delete query.client_id

    if (competence.value.trim()) query.competence = competence.value.trim()
    else delete query.competence

    // Nunca confiar em office_id na URL (tenant vem da sessão).
    delete query.office_id

    await router.replace({ query })
  }

  function resetPage() {
    page.value = 1
  }

  return {
    page,
    perPage,
    total,
    lastPage,
    q,
    situation,
    clientId,
    competence,
    loading,
    loadError,
    applyMeta,
    applyPaginator,
    syncUrl,
    resetPage
  }
}

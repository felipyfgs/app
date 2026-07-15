/** Valor sentinela para USelect: Reka UI proíbe SelectItem com value "". */
export const FILTER_ALL = 'all'

export interface NotesFilterState {
  /** Busca textual de triagem (número, nome, CNPJ ou chave). */
  q: string
  /** Tipo DF-e (NFSE, NFE, CTE, …) ou FILTER_ALL. */
  kind: string
  /** Direção: IN (entrada) / OUT (saída) ou FILTER_ALL. */
  direction: string
  client_id: string
  establishment_id: string
  issuer_cnpj: string
  taker_cnpj: string
  fiscal_role: string
  competence: string
  issued_from: string
  issued_to: string
  status: string
  /** '1' quando fila "sem nome de parte" está ativa. */
  missing_party_name: string
}

export type NotesTriageQueue = 'all' | 'review' | 'cancelled' | 'competence' | 'missing_party'

const SELECT_KEYS = new Set<keyof NotesFilterState>([
  'kind',
  'direction',
  'client_id',
  'establishment_id',
  'fiscal_role',
  'status'
])

export function emptyNotesFilters(): NotesFilterState {
  return {
    q: '',
    kind: FILTER_ALL,
    direction: FILTER_ALL,
    client_id: FILTER_ALL,
    establishment_id: FILTER_ALL,
    issuer_cnpj: '',
    taker_cnpj: '',
    fiscal_role: FILTER_ALL,
    competence: '',
    issued_from: '',
    issued_to: '',
    status: FILTER_ALL,
    missing_party_name: ''
  }
}

/** True se o valor do filtro deve ir para API/query. */
export function isActiveFilterValue(value: string | undefined | null): boolean {
  return !!value && value !== FILTER_ALL
}

const FILTER_KEYS: (keyof NotesFilterState)[] = [
  'q',
  'kind',
  'direction',
  'client_id',
  'establishment_id',
  'issuer_cnpj',
  'taker_cnpj',
  'fiscal_role',
  'competence',
  'issued_from',
  'issued_to',
  'status',
  'missing_party_name'
]

/** Aplica fila de triagem (substitui status/competência/missing conforme a fila). */
export function applyTriageQueue(
  filters: NotesFilterState,
  queue: NotesTriageQueue,
  competenceCurrentLabel?: string
): NotesFilterState {
  const next = { ...filters }
  // Limpa facetas de fila antes de aplicar
  next.status = FILTER_ALL
  next.competence = ''
  next.missing_party_name = ''
  if (queue === 'review') {
    next.status = 'UNKNOWN'
  } else if (queue === 'cancelled') {
    // Grupo operacional: backend expande CANCELLED + SUPERSEDED
    next.status = 'CANCELLED'
  } else if (queue === 'competence' && competenceCurrentLabel) {
    next.competence = competenceCurrentLabel
  } else if (queue === 'missing_party') {
    next.missing_party_name = '1'
  }
  // "all" limpa todas as facetas controladas pelos chips de triagem.
  return next
}

export function activeTriageQueue(filters: NotesFilterState): NotesTriageQueue {
  if (filters.missing_party_name === '1') return 'missing_party'
  // Grupo cancelada (CANCELLED na URL = grupo operacional)
  if (filters.status === 'CANCELLED') return 'cancelled'
  if (filters.status === 'UNKNOWN' || filters.status === 'REVIEW') return 'review'
  if (filters.status === 'AUTHORIZED') return 'all'
  // competence isolada sem outros sinais fortes → fila competência (heurística)
  if (filters.competence && filters.status === FILTER_ALL && !filters.missing_party_name) {
    // não forçar highlight se o usuário só escolheu competência manualmente — ok marcar
    return filters.competence === currentCompetenceLabel() ? 'competence' : 'all'
  }
  return 'all'
}

export function currentCompetenceLabel(): string {
  const d = new Date()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  return `${d.getFullYear()}-${m}`
}

export type NotesViewMode = 'document' | 'client'

/** Lê filtros, cursor e aba de visualização da query da rota. */
export function filtersFromQuery(query: Record<string, unknown>): {
  filters: NotesFilterState
  cursor: string | null
  view: NotesViewMode
} {
  const filters = emptyNotesFilters()
  for (const key of FILTER_KEYS) {
    const value = query[key]
    if (typeof value === 'string' && value && value !== FILTER_ALL) {
      filters[key] = value
    }
  }
  // Compat: ?access_key= legado vira busca q
  if (!filters.q && typeof query.access_key === 'string' && query.access_key) {
    filters.q = query.access_key
  }
  const cursor = typeof query.cursor === 'string' && query.cursor ? query.cursor : null
  // Default: Clientes (por empresa). NFS-e nacional = view=document|nfs
  const view: NotesViewMode
    = query.view === 'document' || query.view === 'nfs'
      ? 'document'
      : 'client'
  return { filters, cursor, view }
}

/** Serializa filtros, cursor e aba (sem chaves vazias / "all"). */
export function filtersToQuery(
  filters: NotesFilterState,
  cursor?: string | null,
  view?: NotesViewMode | null
): Record<string, string> {
  const query: Record<string, string> = {}
  for (const key of FILTER_KEYS) {
    const value = filters[key]
    if (isActiveFilterValue(value)) {
      query[key] = value
    }
  }
  if (cursor) {
    query.cursor = cursor
  }
  // Default é client — só grava na URL quando for NFS-e nacional
  if (view === 'document') {
    query.view = 'document'
  }
  return query
}

/** Export filters espelhando o catálogo (campos que o ZIP job entende). */
export function catalogToExportFilters(filters: NotesFilterState): {
  access_key?: string
  issuer_cnpj?: string
  taker_cnpj?: string
  competence?: string
  status?: string
  fiscal_role?: string
  direction?: string
  issued_from?: string
  issued_to?: string
  client_id?: number
  establishment_id?: number
} {
  const out: Record<string, string | number> = {}
  if (isActiveFilterValue(filters.issuer_cnpj)) out.issuer_cnpj = filters.issuer_cnpj
  if (isActiveFilterValue(filters.taker_cnpj)) out.taker_cnpj = filters.taker_cnpj
  if (isActiveFilterValue(filters.competence)) out.competence = filters.competence
  if (isActiveFilterValue(filters.status)) out.status = filters.status
  if (isActiveFilterValue(filters.fiscal_role)) out.fiscal_role = filters.fiscal_role
  if (isActiveFilterValue(filters.direction)) out.direction = filters.direction
  if (isActiveFilterValue(filters.issued_from)) out.issued_from = filters.issued_from
  if (isActiveFilterValue(filters.issued_to)) out.issued_to = filters.issued_to
  if (isActiveFilterValue(filters.client_id)) out.client_id = Number(filters.client_id)
  if (isActiveFilterValue(filters.establishment_id)) out.establishment_id = Number(filters.establishment_id)
  // q só vira access_key se parecer chave (evita export “todas as notas” com busca textual).
  if (isActiveFilterValue(filters.q) && filters.q.length >= 40) {
    out.access_key = filters.q
  }
  return out as ReturnType<typeof catalogToExportFilters>
}

/** Critérios que o ZIP job consegue aplicar (não conta busca textual curta). */
export function hasExportableCatalogFilters(filters: NotesFilterState): boolean {
  return Object.keys(catalogToExportFilters(filters)).length > 0
}

export function hasActiveCatalogFilters(filters: NotesFilterState): boolean {
  return FILTER_KEYS.some(key => isActiveFilterValue(filters[key]))
}

export function selectAllItem(label: string) {
  return { label, value: FILTER_ALL }
}

export { SELECT_KEYS }

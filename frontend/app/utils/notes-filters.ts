/** Valor sentinela para USelect: Reka UI proíbe SelectItem com value "". */
export const FILTER_ALL = 'all'

export interface NotesFilterState {
  access_key: string
  client_id: string
  establishment_id: string
  issuer_cnpj: string
  taker_cnpj: string
  fiscal_role: string
  competence: string
  issued_from: string
  issued_to: string
  status: string
}

const SELECT_KEYS = new Set<keyof NotesFilterState>([
  'client_id',
  'establishment_id',
  'fiscal_role',
  'status'
])

export function emptyNotesFilters(): NotesFilterState {
  return {
    access_key: '',
    client_id: FILTER_ALL,
    establishment_id: FILTER_ALL,
    issuer_cnpj: '',
    taker_cnpj: '',
    fiscal_role: FILTER_ALL,
    competence: '',
    issued_from: '',
    issued_to: '',
    status: FILTER_ALL
  }
}

/** True se o valor do filtro deve ir para API/query. */
export function isActiveFilterValue(value: string | undefined | null): boolean {
  return !!value && value !== FILTER_ALL
}

const FILTER_KEYS: (keyof NotesFilterState)[] = [
  'access_key',
  'client_id',
  'establishment_id',
  'issuer_cnpj',
  'taker_cnpj',
  'fiscal_role',
  'competence',
  'issued_from',
  'issued_to',
  'status'
]

/** Lê filtros e cursor da query da rota (sem valores vazios). */
export function filtersFromQuery(query: Record<string, unknown>): {
  filters: NotesFilterState
  cursor: string | null
} {
  const filters = emptyNotesFilters()
  for (const key of FILTER_KEYS) {
    const value = query[key]
    if (typeof value === 'string' && value && value !== FILTER_ALL) {
      filters[key] = value
    }
  }
  const cursor = typeof query.cursor === 'string' && query.cursor ? query.cursor : null
  return { filters, cursor }
}

/** Serializa filtros e cursor para a query (sem chaves vazias / "all"). */
export function filtersToQuery(
  filters: NotesFilterState,
  cursor?: string | null
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
  return query
}

export function selectAllItem(label: string) {
  return { label, value: FILTER_ALL }
}

export { SELECT_KEYS }

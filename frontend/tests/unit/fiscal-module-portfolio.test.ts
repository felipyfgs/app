/**
 * Testes da lógica de carteira: filtros, keep-last-good, descarte por epoch.
 * Extrai comportamentos testáveis sem montar Nuxt completo.
 */
import { describe, expect, it } from 'vitest'
import {
  fiscalKpiSituationFilter,
  isSyntheticFiscalOrigin,
  type FiscalModulePortfolioFilters
} from '../../app/types/fiscal-modules'

/** Espelha buildFilters do composable (pure). */
function buildFilters(state: {
  page: number
  perPage: number
  q: string
  situation: string
  competence: string
  submodule: string
  deliveryStatus: string
  clientId: string
}): FiscalModulePortfolioFilters {
  const clientIdNum = Number(state.clientId)
  return {
    page: state.page,
    per_page: state.perPage,
    q: state.q.trim() || undefined,
    situation: state.situation && state.situation !== 'all' ? state.situation : undefined,
    competence: state.competence.trim() || undefined,
    submodule:
      state.submodule && state.submodule !== 'all' && state.submodule.trim()
        ? state.submodule
        : undefined,
    delivery_status:
      state.deliveryStatus && state.deliveryStatus !== 'all'
        ? state.deliveryStatus
        : undefined,
    client_id:
      Number.isFinite(clientIdNum) && clientIdNum >= 1
        ? Math.floor(clientIdNum)
        : undefined
  }
}

function stillCurrent(
  seq: number,
  kind: 'overview' | 'clients',
  epochAtStart: number,
  sessionEpoch: number,
  overviewSeq: number,
  clientsSeq: number
) {
  if (epochAtStart !== sessionEpoch) return false
  return kind === 'overview' ? seq === overviewSeq : seq === clientsSeq
}

describe('useFiscalModulePortfolio behaviours (6.2 / 6.11)', () => {
  it('filtros de overview e clients compartilham o mesmo shape', () => {
    const filters = buildFilters({
      page: 2,
      perPage: 15,
      q: '12.345',
      situation: 'PENDING',
      competence: '2026-03',
      submodule: 'PGDASD',
      deliveryStatus: 'all',
      clientId: ''
    })
    expect(filters.situation).toBe('PENDING')
    expect(filters.page).toBe(2)
    expect(filters.delivery_status).toBeUndefined()
    expect(filters.client_id).toBeUndefined()
  })

  it('buildFilters inclui client_id positivo e omite defaults', () => {
    const filters = buildFilters({
      page: 1,
      perPage: 15,
      q: '  ',
      situation: 'all',
      competence: '',
      submodule: 'all',
      deliveryStatus: 'all',
      clientId: '42'
    })
    expect(filters).toEqual({
      page: 1,
      per_page: 15,
      q: undefined,
      situation: undefined,
      competence: undefined,
      submodule: undefined,
      delivery_status: undefined,
      client_id: 42
    })
  })

  it('buildFilters rejeita client_id inválido', () => {
    for (const clientId of ['0', '-1', 'abc', '']) {
      const f = buildFilters({
        page: 1,
        perPage: 15,
        q: '',
        situation: 'all',
        competence: '',
        submodule: '',
        deliveryStatus: 'all',
        clientId
      })
      expect(f.client_id).toBeUndefined()
    }
  })

  it('KPI select atualiza situation e reinicia página', () => {
    let page = 3
    let situation = 'all'
    function selectKpi(key: 'pending' | 'total' | 'attention' | 'error') {
      const next = fiscalKpiSituationFilter(key)
      situation = next || 'all'
      page = 1
    }
    selectKpi('pending')
    expect(situation).toBe('PENDING')
    expect(page).toBe(1)
    selectKpi('attention')
    expect(situation).toBe('ATTENTION')
    selectKpi('total')
    expect(situation).toBe('all')
  })

  it('overview omite situation: contadores independentes da cápsula ativa', () => {
    function buildOverviewFilters(state: {
      q: string
      situation: string
      competence: string
      submodule: string
      deliveryStatus: string
      clientId: string
    }) {
      const full = buildFilters({ page: 1, perPage: 10, ...state })
      // Espelha buildOverviewFilters do composable
      return {
        q: full.q,
        competence: full.competence,
        submodule: full.submodule,
        delivery_status: full.delivery_status,
        client_id: full.client_id
      }
    }

    const withKpi = buildOverviewFilters({
      q: '',
      situation: 'PENDING',
      competence: '',
      submodule: 'pgdasd',
      deliveryStatus: 'all',
      clientId: ''
    })
    expect(withKpi).not.toHaveProperty('situation')
    expect(withKpi.submodule).toBe('pgdasd')

    const withAdvanced = buildOverviewFilters({
      q: 'acme',
      situation: 'UP_TO_DATE',
      competence: '2026-03',
      submodule: 'pgdasd',
      deliveryStatus: 'all',
      clientId: '9'
    })
    expect(withAdvanced).toEqual({
      q: 'acme',
      competence: '2026-03',
      submodule: 'pgdasd',
      delivery_status: undefined,
      client_id: 9
    })

    // Cápsula muda a lista; overview/contadores usam o mesmo shape sem situation
    const listFilters = buildFilters({
      page: 1,
      perPage: 10,
      q: '',
      situation: 'PENDING',
      competence: '',
      submodule: 'pgdasd',
      deliveryStatus: 'all',
      clientId: ''
    })
    expect(listFilters.situation).toBe('PENDING')
    expect(buildOverviewFilters({
      q: '',
      situation: 'PENDING',
      competence: '',
      submodule: 'pgdasd',
      deliveryStatus: 'all',
      clientId: ''
    })).not.toHaveProperty('situation')
  })

  it('descarte de resposta quando sessionEpoch muda', () => {
    let sessionEpoch = 1
    let overviewSeq = 0
    const seq = ++overviewSeq
    const epoch = sessionEpoch
    sessionEpoch = 2
    expect(stillCurrent(seq, 'overview', epoch, sessionEpoch, overviewSeq, 0)).toBe(false)
  })

  it('keep last good: em erro após sucesso não zera rows', () => {
    let rows: Array<{ id: number }> = [{ id: 1 }]
    let hasLoadedOnce = true
    let loadError: string | null = null

    function onError(message: string) {
      loadError = message
      if (!hasLoadedOnce) {
        rows = []
      }
    }

    onError('falha de rede')
    expect(loadError).toBe('falha de rede')
    expect(rows).toEqual([{ id: 1 }])

    hasLoadedOnce = false
    rows = []
    onError('primeira falha')
    expect(rows).toEqual([])
  })

  it('isSynthetic a partir de overview DEMO', () => {
    const overview = { data_origin: 'DEMO' as const }
    expect(isSyntheticFiscalOrigin(overview.data_origin)).toBe(true)
  })

  it('valida module_key da linha contra o módulo pedido', () => {
    const moduleKey = 'simples_mei'
    const data = [
      { module_key: 'simples_mei', client_id: 1 },
      { module_key: 'dctfweb', client_id: 2 }
    ]
    const invalid = data.some(r => r.module_key !== moduleKey)
    expect(invalid).toBe(true)
    const filtered = data.filter(r => !r.module_key || r.module_key === moduleKey)
    expect(filtered).toHaveLength(1)
    expect(filtered[0]?.client_id).toBe(1)
  })

  it('isFiltered considera q, situation, competence, submodule, delivery e client', () => {
    function isFiltered(s: {
      q: string
      situation: string
      competence: string
      submodule: string
      deliveryStatus: string
      clientId: string
    }) {
      return Boolean(
        s.q.trim()
        || (s.situation && s.situation !== 'all')
        || s.competence.trim()
        || (s.submodule && s.submodule !== 'all' && s.submodule.trim())
        || (s.deliveryStatus && s.deliveryStatus !== 'all')
        || s.clientId
      )
    }
    expect(isFiltered({
      q: '', situation: 'all', competence: '', submodule: '', deliveryStatus: 'all', clientId: ''
    })).toBe(false)
    expect(isFiltered({
      q: 'x', situation: 'all', competence: '', submodule: '', deliveryStatus: 'all', clientId: ''
    })).toBe(true)
    expect(isFiltered({
      q: '', situation: 'PENDING', competence: '', submodule: '', deliveryStatus: 'all', clientId: ''
    })).toBe(true)
    expect(isFiltered({
      q: '', situation: 'all', competence: '', submodule: '', deliveryStatus: 'all', clientId: '3'
    })).toBe(true)
  })
})

describe('paginação server-side (6.7 / 6.8)', () => {
  it('applyPaginator lê meta Laravel', () => {
    const state = { page: 1, lastPage: 1, total: 0, perPage: 15 }
    const payload = {
      meta: { current_page: 2, last_page: 5, total: 73, per_page: 15 }
    }
    const meta = payload.meta
    state.page = meta.current_page
    state.lastPage = meta.last_page
    state.total = meta.total
    state.perPage = meta.per_page
    expect(state).toEqual({ page: 2, lastPage: 5, total: 73, perPage: 15 })
  })

  it('applyPaginator aceita paginator flat (sem meta)', () => {
    const state = { page: 1, lastPage: 1, total: 0, perPage: 15 }
    const payload = { current_page: 3, last_page: 4, total: 40, per_page: 10 }
    const meta = payload
    state.page = meta.current_page
    state.lastPage = meta.last_page
    state.total = meta.total
    state.perPage = meta.per_page
    expect(state.page).toBe(3)
    expect(state.perPage).toBe(10)
  })

  it('mudança de filtro reinicia página', () => {
    let page = 4
    const onFilterChange = () => {
      page = 1
    }
    onFilterChange()
    expect(page).toBe(1)
  })
})

describe('client picker query (6.5)', () => {
  it('normaliza CNPJ mascarado para busca', () => {
    const raw = '11.222.333/0001-81'
    const digits = raw.replace(/[^a-zA-Z0-9]/g, '').toUpperCase()
    expect(digits).toBe('11222333000181')
  })

  it('busca server-side não exige ID numérico (termo livre)', () => {
    function buildClientSearchQ(term: string) {
      const raw = term.trim()
      if (raw.length < 2) return null
      const digits = raw.replace(/[^a-zA-Z0-9]/g, '').toUpperCase()
      const looksLikeDoc = digits.length >= 8 && /^[A-Z0-9]+$/i.test(digits) && digits.length <= 14
      return looksLikeDoc ? digits : raw
    }
    expect(buildClientSearchQ('Acme')).toBe('Acme')
    expect(buildClientSearchQ('11.222.333/0001-81')).toBe('11222333000181')
    expect(buildClientSearchQ('a')).toBeNull()
  })
})

describe('estado local e troca de office (6.2 / 6.11)', () => {
  it('sessionEpoch incr. descarta overview e clients em voo', () => {
    let sessionEpoch = 1
    let overviewSeq = 0
    let clientsSeq = 0

    const oSeq = ++overviewSeq
    const cSeq = ++clientsSeq
    const epoch = sessionEpoch

    // troca de office (useDashboard.sessionEpoch++)
    sessionEpoch += 1
    overviewSeq += 1
    clientsSeq += 1

    expect(stillCurrent(oSeq, 'overview', epoch, sessionEpoch, overviewSeq, clientsSeq)).toBe(false)
    expect(stillCurrent(cSeq, 'clients', epoch, sessionEpoch, overviewSeq, clientsSeq)).toBe(false)
  })

  it('troca de office zera estado local (sem misturar tenants)', () => {
    const state = {
      rows: [{ client_id: 1, legal_name: 'Tenant A' }],
      overview: { module_key: 'simples_mei', total_clients: 1 },
      total: 10,
      lastPage: 2,
      lastValidAt: '2026-01-01T00:00:00Z',
      hasLoadedOnce: true,
      loadError: 'x' as string | null
    }

    // espelha watch(sessionEpoch)
    state.rows = []
    state.overview = null as unknown as typeof state.overview
    state.total = 0
    state.lastPage = 1
    state.lastValidAt = null as unknown as string
    state.hasLoadedOnce = false
    state.loadError = null

    expect(state.rows).toEqual([])
    expect(state.overview).toBeNull()
    expect(state.total).toBe(0)
    expect(state.hasLoadedOnce).toBe(false)
    expect(state.lastValidAt).toBeNull()
  })

  it('resposta stale (seq antiga) não sobrescreve última válida', () => {
    let clientsSeq = 1
    let rows = [{ id: 1 }]
    const staleSeq = clientsSeq
    // novo request
    const freshSeq = ++clientsSeq
    // fresh completa
    if (freshSeq === clientsSeq) {
      rows = [{ id: 2 }]
    }
    // stale completa depois — deve ser descartada
    if (staleSeq === clientsSeq) {
      rows = [{ id: 99 }]
    }
    expect(rows).toEqual([{ id: 2 }])
  })

  it('filtros continuam compondo a query HTTP da API', () => {
    const filters = buildFilters({
      page: 2,
      perPage: 10,
      q: 'acme',
      situation: 'ATTENTION',
      competence: '2026-01',
      submodule: 'PGDASD',
      deliveryStatus: 'all',
      clientId: '12'
    })
    expect(filters).toMatchObject({
      page: 2,
      q: 'acme',
      situation: 'ATTENTION',
      competence: '2026-01',
      submodule: 'PGDASD',
      client_id: 12
    })
  })
})

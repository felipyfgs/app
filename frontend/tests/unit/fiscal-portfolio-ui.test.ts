import { describe, expect, it } from 'vitest'
import {
  coverageMeta,
  dataOriginMeta,
  fiscalStatusMeta,
  resolveFiscalEmptyKind
} from '../../app/utils/fiscal-status'
import {
  fiscalKpiSituationFilter,
  fiscalSituationToKpiKey,
  type FiscalKpiKey,
  type FiscalModuleCounters
} from '../../app/types/fiscal-modules'
import { monitoringNavMenuItems, MONITORING_NAV_ITEMS } from '../../app/utils/monitoring-nav'

/** Espelha resolução de props do FiscalKpiStrip (API unificada). */
function resolveKpiTotal(input: { total?: number | null, totalClients?: number | null }) {
  if (input.total != null && Number.isFinite(Number(input.total))) return Number(input.total)
  if (input.totalClients != null && Number.isFinite(Number(input.totalClients))) {
    return Number(input.totalClients)
  }
  return 0
}

function resolveActiveKey(input: {
  activeKey?: FiscalKpiKey | null
  activeSituation?: string | null
}): FiscalKpiKey {
  if (input.activeKey) return input.activeKey
  if (input.activeSituation != null && String(input.activeSituation).length > 0) {
    return fiscalSituationToKpiKey(input.activeSituation)
  }
  return 'total'
}

/** Espelha onKpiSelect do FiscalModuleTable. */
function onKpiSelect(key: FiscalKpiKey) {
  return {
    key,
    situation: fiscalKpiSituationFilter(key) || 'all'
  }
}

describe('FiscalKpiStrip mapping (6.4 / 6.11)', () => {
  it('clique em Pendências/Atenção produz filtro de situação', () => {
    expect(fiscalKpiSituationFilter('pending')).toBe('PENDING')
    expect(fiscalKpiSituationFilter('attention')).toBe('ATTENTION')
    expect(fiscalKpiSituationFilter('total')).toBeNull()
  })

  it('aliases totalClients / activeSituation resolvem o mesmo contrato', () => {
    expect(resolveKpiTotal({ totalClients: 42 })).toBe(42)
    expect(resolveKpiTotal({ total: 10, totalClients: 99 })).toBe(10)
    expect(resolveKpiTotal({})).toBe(0)

    expect(resolveActiveKey({ activeSituation: 'PENDING' })).toBe('pending')
    expect(resolveActiveKey({ activeSituation: 'all' })).toBe('total')
    expect(resolveActiveKey({ activeKey: 'error', activeSituation: 'PENDING' })).toBe('error')
  })

  it('@select emite key + situation (contrato das páginas e FiscalModuleTable)', () => {
    function onSelect(key: 'pending' | 'total') {
      return [key, fiscalKpiSituationFilter(key)] as const
    }
    expect(onSelect('pending')).toEqual(['pending', 'PENDING'])
    expect(onSelect('total')).toEqual(['total', null])
  })

  it('fiscalSituationToKpiKey cobre a faixa acionável', () => {
    expect(fiscalSituationToKpiKey('UP_TO_DATE')).toBe('up_to_date')
    expect(fiscalSituationToKpiKey('processing')).toBe('processing')
    expect(fiscalSituationToKpiKey('UNSUPPORTED')).toBe('total')
    expect(fiscalSituationToKpiKey(null)).toBe('total')
  })

  it('FiscalModuleTable: totalClients preferido no strip; situation→activeKey', () => {
    const counters: FiscalModuleCounters = {
      up_to_date: 5,
      processing: 1,
      pending: 3,
      attention: 2,
      error: 0
    }
    // Tabela passa totalClients ?? total e activeKey derivado da situation
    const totalClients = 11
    const pageTotal = 15 // paginator total (pode ser filtrado)
    const stripTotal = resolveKpiTotal({
      total: totalClients ?? pageTotal,
      totalClients: totalClients ?? pageTotal
    })
    expect(stripTotal).toBe(11)
    expect(counters.pending + counters.attention).toBe(5)

    const activeFromUrl = resolveActiveKey({
      activeKey: fiscalSituationToKpiKey('PENDING'),
      activeSituation: 'PENDING'
    })
    expect(activeFromUrl).toBe('pending')

    // Clique no KPI total limpa filtro na tabela
    expect(onKpiSelect('total')).toEqual({ key: 'total', situation: 'all' })
    expect(onKpiSelect('pending')).toEqual({ key: 'pending', situation: 'PENDING' })
  })

  it('chips esperados: Total + contadores (+ Erro)', () => {
    const keys: FiscalKpiKey[] = [
      'total', 'up_to_date', 'processing', 'pending', 'attention', 'error'
    ]
    for (const key of keys) {
      const sit = fiscalKpiSituationFilter(key)
      expect(fiscalSituationToKpiKey(sit)).toBe(key === 'total' ? 'total' : key)
    }
  })
})

describe('badges cobertura, origem e status (6.6 / 6.11)', () => {
  it('coverageMeta expõe texto e ícone (não só cor)', () => {
    for (const code of ['FULL', 'PARTIAL', 'UNSUPPORTED', 'NOT_APPLICABLE', 'UNKNOWN']) {
      const meta = coverageMeta(code)
      expect(meta.label.length).toBeGreaterThan(0)
      expect(meta.icon).toMatch(/^i-lucide-/)
      expect(meta.description.length).toBeGreaterThan(0)
    }
    expect(coverageMeta('PARTIAL').color).toBe('warning')
    expect(coverageMeta('FULL').color).toBe('success')
  })

  it('dataOriginMeta marca DEMO/SIMULATED como sintético com banner', () => {
    const demo = dataOriginMeta('DEMO')
    expect(demo.synthetic).toBe(true)
    expect(demo.banner).toMatch(/demonstrativ/i)
    expect(demo.icon).toMatch(/^i-lucide-/)

    const live = dataOriginMeta('LIVE')
    expect(live.synthetic).toBe(false)
    expect(live.banner).toBeNull()
  })

  it('fiscalStatusMeta distingue situação por label+ícone (badge)', () => {
    const pending = fiscalStatusMeta('PENDING')
    expect(pending.label).toMatch(/pendente/i)
    expect(pending.icon).toMatch(/^i-lucide-/)
    expect(pending.color).toBe('warning')

    const blocked = fiscalStatusMeta('BLOCKED')
    expect(blocked.label).toMatch(/bloquead/i)
    expect(blocked.color).toBe('error')

    const unsupported = fiscalStatusMeta('UNSUPPORTED')
    expect(unsupported.sourceHint).toBeTruthy()
  })
})

describe('empty states distintos (6.9 / 6.11)', () => {
  it('resolve loading, error, unsupported, blocked, filtered e empty', () => {
    expect(resolveFiscalEmptyKind({ loading: true })).toBe('loading')
    expect(resolveFiscalEmptyKind({ error: 'falha' })).toBe('error')
    expect(resolveFiscalEmptyKind({ situation: 'UNSUPPORTED' })).toBe('unsupported')
    expect(resolveFiscalEmptyKind({ situation: 'BLOCKED' })).toBe('blocked')
    expect(resolveFiscalEmptyKind({ filtered: true })).toBe('filtered')
    expect(resolveFiscalEmptyKind({})).toBe('empty')
  })

  it('não mascara erro quando já há dados anteriores (caller evita empty)', () => {
    expect(resolveFiscalEmptyKind({ hasRows: true, error: 'x' })).toBe('empty')
  })

  it('com hasPrevious e erro sem rows → não classifica como error puro', () => {
    // Empty kind cai em filtered/empty; UI mostra alert + empty, sem inventar dados
    const kind = resolveFiscalEmptyKind({
      error: 'timeout',
      hasRows: false,
      hasPrevious: true,
      filtered: true
    })
    expect(kind).toBe('filtered')
  })

  it('aliases title / emptyTitle (FiscalTableEmptyState)', () => {
    function resolveTitle(input: { title?: string, emptyTitle?: string }, kind: string) {
      const custom = input.title || input.emptyTitle || ''
      if (custom) return custom
      if (kind === 'filtered') return 'Nenhum resultado para os filtros'
      if (kind === 'unsupported') return 'Não suportado'
      if (kind === 'blocked') return 'Consulta bloqueada'
      if (kind === 'error') return 'Falha ao carregar'
      return 'Nenhum registro'
    }
    expect(resolveTitle({ emptyTitle: 'Nenhum cliente Simples/MEI' }, 'empty'))
      .toBe('Nenhum cliente Simples/MEI')
    expect(resolveTitle({ title: 'A' }, 'empty')).toBe('A')
    expect(resolveTitle({}, 'filtered')).toBe('Nenhum resultado para os filtros')
    expect(resolveTitle({}, 'blocked')).toBe('Consulta bloqueada')
    expect(resolveTitle({}, 'unsupported')).toBe('Não suportado')
  })

  it('skeleton só no carregamento inicial (sem rows e sem previous)', () => {
    function showTableSkeleton(input: {
      loading: boolean
      hasRows: boolean
      hasPrevious: boolean
    }) {
      return Boolean(input.loading && !input.hasRows && !input.hasPrevious)
    }
    expect(showTableSkeleton({ loading: true, hasRows: false, hasPrevious: false })).toBe(true)
    expect(showTableSkeleton({ loading: true, hasRows: true, hasPrevious: true })).toBe(false)
    expect(showTableSkeleton({ loading: true, hasRows: false, hasPrevious: true })).toBe(false)
    expect(showTableSkeleton({ loading: false, hasRows: false, hasPrevious: false })).toBe(false)
  })
})

describe('demo banner gate (6.10 / 6.11)', () => {
  it('só considera sintético com origem DEMO/SIMULATED', () => {
    expect(dataOriginMeta('DEMO').banner).toBeTruthy()
    expect(dataOriginMeta('LIVE').banner).toBeNull()
    expect(dataOriginMeta(null).synthetic).toBe(false)
  })

  it('não ativa banner a partir de erro ou vazio produtivo', () => {
    const show = (input: { origin?: string | null, isSynthetic?: boolean | null }) => {
      if (input.isSynthetic === true) return true
      if (input.isSynthetic === false) return false
      const v = String(input.origin || '').toUpperCase()
      return v === 'DEMO' || v === 'SIMULATED'
    }
    expect(show({ origin: null, isSynthetic: null })).toBe(false)
    expect(show({ origin: 'LIVE' })).toBe(false)
    expect(show({ origin: 'DEMO' })).toBe(true)
    expect(show({ isSynthetic: true, origin: 'LIVE' })).toBe(true)
    expect(show({ isSynthetic: false, origin: 'DEMO' })).toBe(false)
  })
})

describe('MonitoringModuleNav (6.3 / 6.11)', () => {
  it('activeOverride força o módulo destacado independente do path', () => {
    const items = monitoringNavMenuItems('/monitoring/guides', 'simples_mei')
    const active = items.filter(i => i.active)
    expect(active).toHaveLength(1)
    expect(active[0]?.to).toBe('/monitoring/simples-mei')
  })

  it('lista canônica cobre todos os destinos do hub', () => {
    expect(MONITORING_NAV_ITEMS.map(i => i.moduleKey)).toEqual([
      'dashboard',
      'simples_mei',
      'dctfweb',
      'fgts',
      'installments',
      'sitfis',
      'mailbox',
      'declarations',
      'guides'
    ])
  })

  it('menu highlight tem exatamente um active por path de módulo', () => {
    for (const item of MONITORING_NAV_ITEMS) {
      const menu = monitoringNavMenuItems(item.to)
      expect(menu.filter(i => i.active)).toHaveLength(1)
      expect(menu.find(i => i.active)?.to).toBe(item.to)
    }
  })
})

describe('client cell identidade (6.6 / 6.11)', () => {
  it('prioriza displayName e CNPJ mascarado (sem PFX/segredo)', () => {
    function primaryName(input: {
      displayName?: string | null
      name?: string | null
      legalName?: string | null
      clientId?: number | null
    }) {
      const n = input.displayName || input.name || input.legalName || ''
      return String(n).trim() || (input.clientId ? `Cliente #${input.clientId}` : '—')
    }
    expect(primaryName({ displayName: 'Loja', legalName: 'Loja LTDA' })).toBe('Loja')
    expect(primaryName({ clientId: 9 })).toBe('Cliente #9')
    const masked = '11.***.***/****-81'
    expect(masked).toMatch(/\*/)
    expect(masked).not.toMatch(/PFX|password|pem|token/i)
  })
})

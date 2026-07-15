import { describe, expect, it } from 'vitest'
import {
  FISCAL_MODULE_KEYS,
  FISCAL_MODULE_LABELS,
  FISCAL_MODULE_PATHS,
  FISCAL_PORTFOLIO_MODULE_KEYS,
  fiscalDataOriginLabel,
  fiscalKpiSituationFilter,
  fiscalSituationToKpiKey,
  isFiscalPortfolioModule,
  isSyntheticFiscalOrigin,
  type FiscalModuleClientRow,
  type FiscalModuleOverview,
  type SimplesMeiClientRow
} from '../../app/types/fiscal-modules'

describe('fiscal-modules types e helpers (6.1)', () => {
  it('expõe todos os module keys e paths de monitoring', () => {
    expect(FISCAL_MODULE_KEYS).toContain('dashboard')
    expect(FISCAL_PORTFOLIO_MODULE_KEYS).not.toContain('dashboard')
    for (const key of FISCAL_PORTFOLIO_MODULE_KEYS) {
      expect(FISCAL_MODULE_LABELS[key].length).toBeGreaterThan(0)
      expect(FISCAL_MODULE_PATHS[key]).toMatch(/^\/monitoring/)
    }
  })

  it('isFiscalPortfolioModule distingue dashboard', () => {
    expect(isFiscalPortfolioModule('simples_mei')).toBe(true)
    expect(isFiscalPortfolioModule('dashboard')).toBe(false)
    expect(isFiscalPortfolioModule('unknown')).toBe(false)
  })

  it('isSyntheticFiscalOrigin cobre DEMO e SIMULATED', () => {
    expect(isSyntheticFiscalOrigin('DEMO')).toBe(true)
    expect(isSyntheticFiscalOrigin('SIMULATED')).toBe(true)
    expect(isSyntheticFiscalOrigin('LIVE')).toBe(false)
    expect(isSyntheticFiscalOrigin(null)).toBe(false)
  })

  it('KPI acionável mapeia para situation da API', () => {
    expect(fiscalKpiSituationFilter('total')).toBeNull()
    expect(fiscalKpiSituationFilter('up_to_date')).toBe('UP_TO_DATE')
    expect(fiscalKpiSituationFilter('processing')).toBe('PROCESSING')
    expect(fiscalKpiSituationFilter('pending')).toBe('PENDING')
    expect(fiscalKpiSituationFilter('attention')).toBe('ATTENTION')
    expect(fiscalKpiSituationFilter('error')).toBe('ERROR')
  })

  it('rótulos de origem alinha ao backend', () => {
    expect(fiscalDataOriginLabel('DEMO')).toMatch(/demonstrativ/i)
    expect(fiscalDataOriginLabel('LIVE')).toMatch(/produtiv/i)
  })

  it('fiscalSituationToKpiKey é inverso de fiscalKpiSituationFilter', () => {
    for (const key of ['total', 'up_to_date', 'processing', 'pending', 'attention', 'error'] as const) {
      const sit = fiscalKpiSituationFilter(key)
      expect(fiscalSituationToKpiKey(sit)).toBe(key === 'total' ? 'total' : key)
    }
  })

  it('tipos discriminados de overview/row não usam Record solto no fluxo principal', () => {
    const overview: FiscalModuleOverview<'simples_mei'> = {
      module_key: 'simples_mei',
      total_clients: 3,
      counters: {
        up_to_date: 1,
        processing: 0,
        pending: 1,
        attention: 1,
        error: 0
      },
      data_origin: 'DEMO'
    }
    expect(overview.module_key).toBe('simples_mei')

    const row: SimplesMeiClientRow = {
      module_key: 'simples_mei',
      client_id: 1,
      legal_name: 'Demo LTDA',
      cnpj_masked: '11.***.***/****-81',
      situation: 'PENDING',
      coverage: 'FULL',
      detail: { submodule: 'PGDASD', period_key: '2026-03' }
    }
    const asUnion: FiscalModuleClientRow = row
    expect(asUnion.module_key).toBe('simples_mei')
    if (asUnion.module_key === 'simples_mei') {
      expect(asUnion.detail.period_key).toBe('2026-03')
    }
  })

  it('FiscalModulePortfolioFilters inclui client_id opcional (6.2)', () => {
    const filters: import('../../app/types/fiscal-modules').FiscalModulePortfolioFilters = {
      page: 1,
      per_page: 15,
      client_id: 12,
      situation: 'PENDING'
    }
    expect(filters.client_id).toBe(12)
  })
})

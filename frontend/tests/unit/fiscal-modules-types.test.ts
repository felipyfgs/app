import { describe, expect, it } from 'vitest'
import {
  FISCAL_MODULE_KEYS,
  FISCAL_MODULE_LABELS,
  FISCAL_MODULE_PATHS,
  FISCAL_PORTFOLIO_MODULE_KEYS,
  documentActionVisible,
  documentUnavailableLabel,
  fiscalDataOriginLabel,
  fiscalKpiSituationFilter,
  fiscalSituationToKpiKey,
  isFiscalPortfolioModule,
  isSurfaceUnavailable,
  isSyntheticFiscalOrigin,
  surfaceAllowsDocument,
  type FiscalDocumentDescriptor,
  type FiscalModuleClientRow,
  type FiscalModuleOverview,
  type FiscalMonitoringSurfaceSummary,
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

  it('KPI acionável mapeia para situation da API (nove estados)', () => {
    expect(fiscalKpiSituationFilter('total')).toBeNull()
    expect(fiscalKpiSituationFilter('up_to_date')).toBe('UP_TO_DATE')
    expect(fiscalKpiSituationFilter('processing')).toBe('PROCESSING')
    expect(fiscalKpiSituationFilter('pending')).toBe('PENDING')
    expect(fiscalKpiSituationFilter('attention')).toBe('ATTENTION')
    expect(fiscalKpiSituationFilter('error')).toBe('ERROR')
    expect(fiscalKpiSituationFilter('blocked')).toBe('BLOCKED')
    expect(fiscalKpiSituationFilter('unknown')).toBe('UNKNOWN')
    expect(fiscalKpiSituationFilter('unsupported')).toBe('UNSUPPORTED')
    expect(fiscalKpiSituationFilter('not_applicable')).toBe('NOT_APPLICABLE')
  })

  it('rótulos de origem alinha ao backend e fail-closed', () => {
    expect(fiscalDataOriginLabel('DEMO')).toMatch(/demonstrativ/i)
    expect(fiscalDataOriginLabel('LIVE')).toMatch(/produtiv/i)
    expect(fiscalDataOriginLabel(null)).toBe('Origem não informada')
    expect(fiscalDataOriginLabel('')).toBe('Origem não informada')
    expect(fiscalDataOriginLabel('WEIRD')).toBe('Origem não informada')
  })

  it('fiscalSituationToKpiKey é inverso de fiscalKpiSituationFilter', () => {
    const keys = [
      'total', 'up_to_date', 'processing', 'pending', 'attention', 'error',
      'blocked', 'unknown', 'unsupported', 'not_applicable'
    ] as const
    for (const key of keys) {
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
        error: 0,
        blocked: 0,
        unknown: 0,
        unsupported: 0,
        not_applicable: 0
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

  it('documentActionVisible exige available=true e href não vazio', () => {
    const available: FiscalDocumentDescriptor = {
      available: true,
      kind: 'PDF',
      label: 'Ver recibo',
      content_type: 'application/pdf',
      observed_at: '2026-07-01T00:00:00Z',
      source_surface: 'sitfis',
      source_label: 'Situação fiscal',
      href: '/api/v1/fiscal/evidence/9/download',
      unavailable_reason: null
    }
    expect(documentActionVisible(available)).toBe(true)
    expect(documentActionVisible({ ...available, available: false, href: null })).toBe(false)
    expect(documentActionVisible({ ...available, href: null })).toBe(false)
    expect(documentActionVisible({ ...available, href: '   ' })).toBe(false)
    expect(documentActionVisible(null)).toBe(false)
    expect(documentActionVisible(undefined)).toBe(false)
  })

  it('documentUnavailableLabel cobre motivos públicos e fail-closed', () => {
    expect(documentUnavailableLabel('STRUCTURED_ONLY')).toMatch(/estruturados/i)
    expect(documentUnavailableLabel('PROCESSING')).toMatch(/processando/i)
    expect(documentUnavailableLabel('NOT_SUPPORTED')).toMatch(/não suportado/i)
    expect(documentUnavailableLabel('NOT_PRODUCTION')).toMatch(/não produtiva/i)
    expect(documentUnavailableLabel('NOT_COLLECTED')).toMatch(/não coletado/i)
    expect(documentUnavailableLabel(null)).toBeNull()
    expect(documentUnavailableLabel('WEIRD')).toBeNull()
  })

  it('surface helpers: UNAVAILABLE e allows_document', () => {
    const unavailable: FiscalMonitoringSurfaceSummary = {
      surface_key: 'simples_mei_dasn',
      route: '/monitoring/simples-mei',
      responsibility: 'DASN',
      result_kind: 'UNAVAILABLE',
      allows_document: false,
      official_state_label: 'Prospecção',
      channel_label: 'Integra Contador'
    }
    expect(isSurfaceUnavailable(unavailable)).toBe(true)
    expect(surfaceAllowsDocument(unavailable)).toBe(false)

    const pdf: FiscalMonitoringSurfaceSummary = {
      ...unavailable,
      surface_key: 'sitfis',
      result_kind: 'ASYNC_PDF',
      allows_document: true
    }
    expect(isSurfaceUnavailable(pdf)).toBe(false)
    expect(surfaceAllowsDocument(pdf)).toBe(true)
    expect(surfaceAllowsDocument(null)).toBe(true)
  })

  it('overview e row aceitam surface e document aditivos', () => {
    const overview: FiscalModuleOverview<'sitfis'> = {
      module_key: 'sitfis',
      total_clients: 1,
      counters: {
        up_to_date: 1,
        processing: 0,
        pending: 0,
        attention: 0,
        error: 0,
        blocked: 0,
        unknown: 0,
        unsupported: 0,
        not_applicable: 0
      },
      surface: {
        surface_key: 'sitfis',
        route: '/monitoring/sitfis',
        responsibility: 'SITFIS',
        result_kind: 'ASYNC_PDF',
        allows_document: true,
        official_state_label: 'Produção',
        channel_label: 'Integra Contador'
      }
    }
    expect(overview.surface?.result_kind).toBe('ASYNC_PDF')

    const row: SimplesMeiClientRow = {
      module_key: 'simples_mei',
      client_id: 1,
      legal_name: 'Demo',
      cnpj_masked: '**',
      situation: 'PENDING',
      coverage: 'FULL',
      detail: {},
      document: {
        available: true,
        kind: 'PDF',
        label: 'Ver declaração/recibo',
        content_type: 'application/pdf',
        observed_at: null,
        source_surface: 'simples_mei_pgdasd',
        source_label: 'PGDAS-D',
        href: '/api/v1/fiscal/evidence/1/download',
        unavailable_reason: null
      }
    }
    expect(documentActionVisible(row.document)).toBe(true)
  })
})

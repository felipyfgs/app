import { describe, expect, it } from 'vitest'
import {
  applyTriageQueue,
  catalogToExportFilters,
  emptyDocsFilters,
  hasActiveCatalogFilters,
  hasExportableCatalogFilters
} from '../../app/utils/notes-filters'

describe('notes-filters', () => {
  it('inicia filtros locais com sentinelas adequadas', () => {
    const filters = emptyDocsFilters()
    expect(filters.q).toBe('')
    expect(filters.kind).toBe('all')
    expect(filters.client_id).toBe('all')
    expect(filters.acquisition_source).toBe('all')
    expect(filters.artifact_quality).toBe('all')
    expect(filters.coverage_status).toBe('all')
    expect(filters.competence).toBe('')
  })

  it('reconhece filtros específicos de CT-e como ativos', () => {
    const filters = emptyDocsFilters()
    filters.kind = 'CTE'
    filters.fiscal_role = 'SENDER'
    filters.acquisition_source = 'CTE_AUTXML_DIST_NSU'
    filters.artifact_quality = 'AUTXML_REDACTED'
    filters.coverage_status = 'PENDING_IMPORT'
    expect(hasActiveCatalogFilters(filters)).toBe(true)
  })

  it('aceita estados de cobertura CT-e do backend (não legados)', () => {
    const allowed = [
      'CAPTURED_ORIGINAL',
      'CAPTURED_AUTXML_REDACTED',
      'PENDING_IMPORT',
      'HISTORICAL_GAP',
      'BLOCKED',
      'NO_ACTIVITY'
    ] as const
    const legacy = [
      'NO_ACTIVITY_CONFIRMED',
      'NOT_COVERED_RETROACTIVE',
      'DEGRADED_CHANNEL'
    ] as const
    for (const status of allowed) {
      const filters = emptyDocsFilters()
      filters.coverage_status = status
      expect(hasActiveCatalogFilters(filters)).toBe(true)
    }
    for (const status of legacy) {
      // Legados não devem ser o valor default do empty state.
      expect(emptyDocsFilters().coverage_status).not.toBe(status)
    }
  })

  it('catalogToExportFilters e hasExportableCatalogFilters', () => {
    const empty = emptyDocsFilters()
    expect(hasActiveCatalogFilters(empty)).toBe(false)
    expect(hasExportableCatalogFilters(empty)).toBe(false)
    empty.q = 'Acme'
    expect(hasActiveCatalogFilters(empty)).toBe(true)
    expect(hasExportableCatalogFilters(empty)).toBe(false)
    empty.client_id = '8'
    empty.competence = '2026-07'
    expect(hasExportableCatalogFilters(empty)).toBe(true)
    expect(catalogToExportFilters(empty)).toEqual({
      client_id: 8,
      competence: '2026-07'
    })
  })

  it('applyTriageQueue define filas de status e missing_party', () => {
    const base = emptyDocsFilters()
    expect(applyTriageQueue(base, 'cancelled').status).toBe('CANCELLED')
    expect(applyTriageQueue(base, 'review').status).toBe('UNKNOWN')
    expect(applyTriageQueue(base, 'missing_party').missing_party_name).toBe('1')
    expect(applyTriageQueue(base, 'competence', '2026-07').competence).toBe('2026-07')
    expect(applyTriageQueue(base, 'all').status).toBe('all')
  })

  it('trocar ou desligar fila limpa a competência controlada pela triagem', () => {
    const competence = applyTriageQueue(emptyDocsFilters(), 'competence', '2026-07')
    expect(competence.competence).toBe('2026-07')

    const cancelled = applyTriageQueue(competence, 'cancelled')
    expect(cancelled.competence).toBe('')
    expect(cancelled.status).toBe('CANCELLED')

    expect(applyTriageQueue(competence, 'all').competence).toBe('')
  })
})

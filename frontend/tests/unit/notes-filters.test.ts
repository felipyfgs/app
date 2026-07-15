import { describe, expect, it } from 'vitest'
import {
  applyTriageQueue,
  catalogToExportFilters,
  emptyNotesFilters,
  hasActiveCatalogFilters,
  hasExportableCatalogFilters
} from '../../app/utils/notes-filters'

describe('notes-filters', () => {
  it('inicia filtros locais com sentinelas adequadas', () => {
    const filters = emptyNotesFilters()
    expect(filters.q).toBe('')
    expect(filters.kind).toBe('all')
    expect(filters.client_id).toBe('all')
    expect(filters.competence).toBe('')
  })

  it('catalogToExportFilters e hasExportableCatalogFilters', () => {
    const empty = emptyNotesFilters()
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
    const base = emptyNotesFilters()
    expect(applyTriageQueue(base, 'cancelled').status).toBe('CANCELLED')
    expect(applyTriageQueue(base, 'review').status).toBe('UNKNOWN')
    expect(applyTriageQueue(base, 'missing_party').missing_party_name).toBe('1')
    expect(applyTriageQueue(base, 'competence', '2026-07').competence).toBe('2026-07')
    expect(applyTriageQueue(base, 'all').status).toBe('all')
  })

  it('trocar ou desligar fila limpa a competência controlada pela triagem', () => {
    const competence = applyTriageQueue(emptyNotesFilters(), 'competence', '2026-07')
    expect(competence.competence).toBe('2026-07')

    const cancelled = applyTriageQueue(competence, 'cancelled')
    expect(cancelled.competence).toBe('')
    expect(cancelled.status).toBe('CANCELLED')

    expect(applyTriageQueue(competence, 'all').competence).toBe('')
  })
})

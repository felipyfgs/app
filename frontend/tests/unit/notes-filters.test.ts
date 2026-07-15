import { describe, expect, it } from 'vitest'
import {
  applyTriageQueue,
  catalogToExportFilters,
  emptyNotesFilters,
  filtersFromQuery,
  filtersToQuery,
  hasActiveCatalogFilters,
  hasExportableCatalogFilters
} from '../../app/utils/notes-filters'

describe('notes-filters', () => {
  it('remove parâmetros vazios na serialização', () => {
    const filters = emptyNotesFilters()
    filters.status = 'ACTIVE'
    filters.competence = '2026-07'
    expect(filtersToQuery(filters)).toEqual({
      status: 'ACTIVE',
      competence: '2026-07'
    })
  })

  it('serializa cursor e view document (default client não grava view)', () => {
    const filters = emptyNotesFilters()
    filters.q = 'Acme'
    expect(filtersToQuery(filters, '42', 'client')).toEqual({
      q: 'Acme',
      cursor: '42'
    })
    expect(filtersToQuery(filters, null, 'document')).toEqual({
      q: 'Acme',
      view: 'document'
    })
  })

  it('restaura filtros; default view é client; document via query', () => {
    const base = filtersFromQuery({
      status: 'ACTIVE',
      q: 'NFS-e',
      cursor: '99',
      empty: '',
      ignored: 1
    })
    expect(base.filters.status).toBe('ACTIVE')
    expect(base.filters.q).toBe('NFS-e')
    expect(base.cursor).toBe('99')
    expect(base.view).toBe('client')

    const doc = filtersFromQuery({ view: 'document' })
    expect(doc.view).toBe('document')
  })

  it('mapeia access_key legado para q', () => {
    const { filters } = filtersFromQuery({ access_key: 'CHAVE123' })
    expect(filters.q).toBe('CHAVE123')
  })

  it('não serializa sentinela all dos USelect', () => {
    const filters = emptyNotesFilters()
    filters.client_id = 'all'
    filters.fiscal_role = 'ISSUER'
    expect(filtersToQuery(filters)).toEqual({ fiscal_role: 'ISSUER' })
  })

  it('serializa e restaura filtro direction', () => {
    const filters = emptyNotesFilters()
    filters.direction = 'OUT'
    expect(filtersToQuery(filters)).toEqual({ direction: 'OUT' })
    const restored = filtersFromQuery({ direction: 'IN' })
    expect(restored.filters.direction).toBe('IN')
  })

  it('cursor nulo e view client por padrão', () => {
    const { cursor, view } = filtersFromQuery({})
    expect(cursor).toBeNull()
    expect(view).toBe('client')
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

import { describe, expect, it } from 'vitest'
import {
  emptyNotesFilters,
  filtersFromQuery,
  filtersToQuery
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

  it('serializa cursor quando presente', () => {
    const filters = emptyNotesFilters()
    expect(filtersToQuery(filters, '42')).toEqual({ cursor: '42' })
  })

  it('restaura filtros e cursor da query', () => {
    const { filters, cursor } = filtersFromQuery({
      status: 'ACTIVE',
      cursor: '99',
      empty: '',
      ignored: 1
    })
    expect(filters.status).toBe('ACTIVE')
    expect(filters.access_key).toBe('')
    expect(filters.client_id).toBe('all')
    expect(cursor).toBe('99')
  })

  it('não serializa sentinela all dos USelect', () => {
    const filters = emptyNotesFilters()
    filters.client_id = 'all'
    filters.fiscal_role = 'ISSUER'
    expect(filtersToQuery(filters)).toEqual({ fiscal_role: 'ISSUER' })
  })

  it('cursor nulo quando ausente', () => {
    const { cursor } = filtersFromQuery({})
    expect(cursor).toBeNull()
  })
})

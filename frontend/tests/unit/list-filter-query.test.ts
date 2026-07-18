import { describe, expect, it } from 'vitest'
import {
  CLIENTS_LIST_QUERY_SCHEMA,
  MONITORING_LIST_QUERY_SCHEMA,
  parseListFilterQuery,
  resolveSortDirection,
  serializeListFilterQuery
} from '../../app/composables/useListFilterQuery'

describe('useListFilterQuery helpers', () => {
  it('omite defaults na serialização e restaura no parse', () => {
    const serialized = serializeListFilterQuery({
      q: 'acme',
      situation: 'UNKNOWN',
      competence: '',
      client_id: '',
      delivery_status: 'all',
      coverage: 'all',
      modality: 'all',
      page: 2,
      per_page: 10,
      sort: 'legal_name',
      sort_direction: 'asc'
    }, MONITORING_LIST_QUERY_SCHEMA)

    expect(serialized).toEqual({
      q: 'acme',
      situation: 'UNKNOWN',
      page: '2'
    })

    const parsed = parseListFilterQuery(serialized as Record<string, unknown>, MONITORING_LIST_QUERY_SCHEMA)
    expect(parsed.q).toBe('acme')
    expect(parsed.situation).toBe('UNKNOWN')
    expect(parsed.page).toBe(2)
    expect(parsed.per_page).toBe(10)
    expect(parsed.sort_direction).toBe('asc')
  })

  it('resolveSortDirection aceita direction e sort_direction', () => {
    expect(resolveSortDirection({ direction: 'desc' })).toBe('desc')
    expect(resolveSortDirection({ sort_direction: 'asc' })).toBe('asc')
    expect(resolveSortDirection({ sort_direction: 'desc', direction: 'asc' })).toBe('desc')
    expect(resolveSortDirection({})).toBe('asc')
  })

  it('clients schema serializa operational_filter', () => {
    const query = serializeListFilterQuery({
      q: '',
      status: 'active',
      operational_filter: 'with_credential',
      page: 1,
      per_page: 20,
      sort: 'legal_name',
      sort_direction: 'asc'
    }, CLIENTS_LIST_QUERY_SCHEMA)

    expect(query).toEqual({
      status: 'active',
      operational_filter: 'with_credential'
    })
  })
})

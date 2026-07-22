import { describe, expect, it } from 'vitest'
import {
  parseWorkQueueQuery,
  parseWorkQueueView,
  serializeWorkQueueQuery,
  type WorkQueueFilters
} from '../../app/composables/useWorkQueueFilters'

const base: WorkQueueFilters = {
  tab: 'open',
  q: '',
  department_id: null,
  assignee_membership_id: null,
  client_id: null,
  scope: 'default',
  page: 1,
  per_page: 10,
  view: 'fila',
  sort: null,
  direction: null
}

describe('work-queue-filters view', () => {
  it('parseWorkQueueView trata lista e default fila', () => {
    expect(parseWorkQueueView('lista')).toBe('lista')
    expect(parseWorkQueueView(['lista'])).toBe('lista')
    expect(parseWorkQueueView('fila')).toBe('fila')
    expect(parseWorkQueueView(undefined)).toBe('fila')
    expect(parseWorkQueueView('outro')).toBe('fila')
  })

  it('serializa view=lista e omite na Fila', () => {
    expect(serializeWorkQueueQuery({ ...base, view: 'fila' }).view).toBeUndefined()
    expect(serializeWorkQueueQuery({ ...base, view: 'lista' }).view).toBe('lista')
  })

  it('preserva filtros ao round-trip com view=lista', () => {
    const query = serializeWorkQueueQuery({
      ...base,
      tab: 'atrasadas',
      q: 'xml',
      department_id: 3,
      client_id: 9,
      scope: 'mine',
      page: 2,
      per_page: 20,
      view: 'lista'
    })

    expect(query).toMatchObject({
      tab: 'atrasadas',
      q: 'xml',
      department_id: '3',
      client_id: '9',
      scope: 'mine',
      page: '2',
      per_page: '20',
      view: 'lista'
    })

    expect(parseWorkQueueQuery(query as Record<string, unknown>)).toEqual({
      tab: 'atrasadas',
      q: 'xml',
      department_id: 3,
      assignee_membership_id: null,
      client_id: 9,
      scope: 'mine',
      page: 2,
      per_page: 20,
      view: 'lista',
      sort: null,
      direction: null
    })
  })

  it('serializa sort e direction na Lista', () => {
    const query = serializeWorkQueueQuery({
      ...base,
      view: 'lista',
      sort: 'title',
      direction: 'desc'
    })
    expect(query.sort).toBe('title')
    expect(query.direction).toBe('desc')
    expect(parseWorkQueueQuery(query as Record<string, unknown>)).toMatchObject({
      sort: 'title',
      direction: 'desc'
    })
  })
})

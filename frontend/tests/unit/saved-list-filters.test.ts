import { describe, expect, it } from 'vitest'
import type { MonitoringFilterConfig } from '../../app/types/fiscal-modules'
import {
  MONITORING_MODULE_SURFACES,
  SAVED_LIST_SCHEMA_VERSION,
  SAVED_LIST_SURFACES,
  isMonitoringSurface,
  resolveMonitoringSurface
} from '../../app/types/saved-list-filters'
import {
  clientsFiltersToPayload,
  clientsPayloadToFilters,
  closingFiltersToPayload,
  closingPayloadToFilters,
  docsFiltersToPayload,
  docsPayloadToFilters,
  emptyClientsFilters,
  hasActiveClientsFiltersForSave,
  hasActiveClosingFiltersForSave,
  hasActiveDocsFiltersForSave,
  hasActiveMonitoringFiltersForSave,
  hasActiveWorkProcessesFiltersForSave,
  hasActiveWorkQueueFiltersForSave,
  hasMonitoringPayloadContent,
  monitoringFiltersToPayload,
  monitoringPayloadToFilters,
  stripEmptyMonitoringPayload,
  workProcessesFiltersToPayload,
  workProcessesPayloadToFilters,
  workQueueFiltersToPayload,
  workQueuePayloadToFilters
} from '../../app/utils/saved-list-filters'
import {
  normalizeMonitoringFilters,
  resetMonitoringFilters
} from '../../app/utils/monitoring-filters'
import { emptyDocsFilters, FILTER_ALL } from '../../app/utils/notes-filters'

const config: MonitoringFilterConfig = {
  fields: [
    { key: 'situation', kind: 'option', label: 'Situação' },
    { key: 'competence', kind: 'month', label: 'Competência' },
    { key: 'clientId', kind: 'client', label: 'Cliente' },
    {
      key: 'status',
      kind: 'option',
      label: 'Status',
      items: [{ label: 'Todos', value: 'all' }, { label: 'Ativo', value: 'ACTIVE' }]
    }
  ]
}

describe('saved-list-filters surfaces', () => {
  it('declara as 15 surfaces estáveis', () => {
    expect(SAVED_LIST_SURFACES).toContain('monitoring.simples_mei')
    expect(SAVED_LIST_SURFACES).toContain('monitoring.mailbox')
    expect(SAVED_LIST_SURFACES).toContain('clients.index')
    expect(SAVED_LIST_SURFACES).toContain('docs.catalog')
    expect(SAVED_LIST_SURFACES).toContain('work.queue')
    expect(SAVED_LIST_SURFACES).toContain('closing.list')
    expect(SAVED_LIST_SURFACES).toHaveLength(15)
  })

  it('mapeia moduleKey → surface de monitoring', () => {
    expect(resolveMonitoringSurface('installments')).toBe('monitoring.installments')
    expect(resolveMonitoringSurface('tax_processes')).toBe('monitoring.tax_processes')
    expect(resolveMonitoringSurface(null)).toBeNull()
    expect(isMonitoringSurface('monitoring.fgts')).toBe(true)
    expect(isMonitoringSurface('docs.catalog')).toBe(false)
    expect(Object.keys(MONITORING_MODULE_SURFACES).length).toBeGreaterThanOrEqual(9)
  })
})

describe('saved-list-filters monitoring adapters', () => {
  it('serializa MonitoringFilterValue → payload schema_version 1 omitindo defaults', () => {
    const filters = normalizeMonitoringFilters({
      q: '  ACME  ',
      situation: 'PENDING',
      competence: '2026-07',
      clientIds: [7],
      status: 'all'
    })
    const payload = monitoringFiltersToPayload(filters, config, 'Cliente 7')
    expect(payload.schema_version).toBe(SAVED_LIST_SCHEMA_VERSION)
    expect(payload.q).toBe('ACME')
    expect(payload.filters.map(f => f.key)).toEqual(['situation', 'competence', 'clientId'])
    expect(payload.filters.find(f => f.key === 'clientId')).toMatchObject({
      operator: 'in',
      value: '7',
      label: 'Cliente 7'
    })
    expect(payload.filters.some(f => f.key === 'status')).toBe(false)
  })

  it('stripEmpty omite q vazio e filters vazios', () => {
    const stripped = stripEmptyMonitoringPayload({
      schema_version: 1,
      q: '  ',
      filters: [
        { key: 'situation', operator: 'eq', value: '' },
        { key: 'competence', operator: 'eq', value: '2026-01' }
      ]
    })
    expect(stripped.q).toBe('')
    expect(stripped.filters).toHaveLength(1)
    expect(stripped.filters[0]?.key).toBe('competence')
  })

  it('hidrata payload → MonitoringFilterValue com uma volta estável', () => {
    const original = normalizeMonitoringFilters({
      q: 'busca',
      situation: 'PENDING',
      competence: '2026-07',
      clientIds: [3]
    })
    const payload = monitoringFiltersToPayload(original, config, 'Cli')
    const back = monitoringPayloadToFilters(payload, config)
    expect(back).toMatchObject({
      q: 'busca',
      situation: 'PENDING',
      competence: '2026-07',
      clientIds: [3],
      status: 'all',
      deliveryStatus: 'all',
      paymentStatus: 'all',
      coverage: 'all',
      modality: 'all'
    })
  })

  it('preset multi situação (CSV + operator in) round-trip estável', () => {
    const original = normalizeMonitoringFilters({
      situation: 'ATTENTION,PENDING',
      q: 'multi'
    })
    const payload = monitoringFiltersToPayload(original, config)
    const sit = payload.filters.find(f => f.key === 'situation')
    expect(sit).toMatchObject({
      operator: 'in',
      value: 'ATTENTION,PENDING'
    })
    const back = monitoringPayloadToFilters(payload, config)
    expect(back.situation).toBe('ATTENTION,PENDING')
    expect(back.q).toBe('multi')
  })

  it('payload inválido ou vazio devolve defaults', () => {
    expect(monitoringPayloadToFilters(null, config)).toEqual(resetMonitoringFilters())
    expect(monitoringPayloadToFilters({}, config)).toEqual(resetMonitoringFilters())
    expect(monitoringPayloadToFilters({
      schema_version: 1,
      q: '',
      filters: [{ key: 'unknown_axis', operator: 'eq', value: 'x' }]
    }, config)).toEqual(resetMonitoringFilters())
  })

  it('detecta conteúdo útil para salvar', () => {
    expect(hasMonitoringPayloadContent({
      schema_version: 1,
      q: '',
      filters: []
    })).toBe(false)
    expect(hasActiveMonitoringFiltersForSave(
      normalizeMonitoringFilters({ q: 'x' }),
      config
    )).toBe(true)
    expect(hasActiveMonitoringFiltersForSave(
      resetMonitoringFilters(),
      config
    )).toBe(false)
  })
})

describe('saved-list-filters clients adapters', () => {
  it('serializa e hidrata roundtrip estável', () => {
    const state = {
      q: '  ACME  ',
      status: 'active',
      operational_filter: 'with_credential'
    }
    const payload = clientsFiltersToPayload(state)
    expect(payload).toMatchObject({
      schema_version: SAVED_LIST_SCHEMA_VERSION,
      q: 'ACME',
      status: 'active',
      operational_filter: 'with_credential'
    })
    expect(clientsPayloadToFilters(payload)).toEqual({
      q: 'ACME',
      status: 'active',
      operational_filter: 'with_credential'
    })
  })

  it('ignora status/operational inválidos', () => {
    expect(clientsPayloadToFilters({
      schema_version: 1,
      q: 'x',
      status: 'nope',
      operational_filter: 'zzz'
    })).toEqual({
      q: 'x',
      status: 'all',
      operational_filter: 'total'
    })
    expect(hasActiveClientsFiltersForSave(emptyClientsFilters())).toBe(false)
    expect(hasActiveClientsFiltersForSave({
      q: '',
      status: 'inactive',
      operational_filter: 'total'
    })).toBe(true)
  })
})

describe('saved-list-filters docs adapters', () => {
  it('omite defaults FILTER_ALL e hidrata de volta', () => {
    const filters = {
      ...emptyDocsFilters(),
      q: 'chave',
      kind: 'NFE',
      status: 'AUTHORIZED',
      client_id: '12',
      competence: '2026-07'
    }
    const payload = docsFiltersToPayload(filters)
    expect(payload.schema_version).toBe(SAVED_LIST_SCHEMA_VERSION)
    expect(payload.q).toBe('chave')
    expect(payload.kind).toBe('NFE')
    expect(payload.status).toBe('AUTHORIZED')
    expect(payload.client_id).toBe('12')
    expect(payload.direction).toBeUndefined()
    expect(payload.coverage_status).toBeUndefined()

    const back = docsPayloadToFilters(payload)
    expect(back.q).toBe('chave')
    expect(back.kind).toBe('NFE')
    expect(back.status).toBe('AUTHORIZED')
    expect(back.client_id).toBe('12')
    expect(back.direction).toBe(FILTER_ALL)
    expect(back.establishment_id).toBe(FILTER_ALL)
  })

  it('payload vazio / inválido devolve defaults', () => {
    expect(docsPayloadToFilters(null)).toEqual(emptyDocsFilters())
    expect(docsPayloadToFilters({ schema_version: 1, kind: 'XYZ' }).kind).toBe(FILTER_ALL)
    expect(hasActiveDocsFiltersForSave(emptyDocsFilters())).toBe(false)
    expect(hasActiveDocsFiltersForSave({
      ...emptyDocsFilters(),
      missing_party_name: '1'
    })).toBe(true)
  })
})

describe('saved-list-filters work adapters', () => {
  it('work.queue: roundtrip e page sempre 1 no apply', () => {
    const filters = {
      tab: 'atrasadas',
      q: '  IRPF  ',
      department_id: 3,
      assignee_membership_id: null,
      client_id: 9,
      scope: 'default',
      page: 4,
      per_page: 25
    }
    const payload = workQueueFiltersToPayload(filters)
    expect(payload).toMatchObject({
      schema_version: SAVED_LIST_SCHEMA_VERSION,
      tab: 'atrasadas',
      q: 'IRPF',
      department_id: 3,
      client_id: 9,
      per_page: 25
    })
    // page não entra no payload
    expect((payload as { page?: number }).page).toBeUndefined()

    const back = workQueuePayloadToFilters(payload)
    expect(back.page).toBe(1)
    expect(back).toMatchObject({
      tab: 'atrasadas',
      q: 'IRPF',
      department_id: 3,
      client_id: 9,
      scope: 'default',
      per_page: 25
    })
    expect(hasActiveWorkQueueFiltersForSave({
      tab: 'open',
      q: '',
      department_id: null,
      assignee_membership_id: null,
      client_id: null,
      scope: 'default',
      page: 1,
      per_page: 10
    })).toBe(false)
    expect(hasActiveWorkQueueFiltersForSave(filters)).toBe(true)
  })

  it('work.processes: roundtrip status all omitido como default', () => {
    const payload = workProcessesFiltersToPayload({
      q: 'folha',
      competence: '2026-06',
      status: 'EM_PROGRESSO'
    })
    expect(payload).toEqual({
      schema_version: SAVED_LIST_SCHEMA_VERSION,
      q: 'folha',
      competence: '2026-06',
      status: 'EM_PROGRESSO'
    })
    expect(workProcessesPayloadToFilters(payload)).toEqual({
      q: 'folha',
      competence: '2026-06',
      status: 'EM_PROGRESSO'
    })
    expect(workProcessesPayloadToFilters({
      schema_version: 1,
      q: '',
      competence: '',
      status: 'all'
    })).toEqual({ q: '', competence: '', status: 'all' })
    expect(hasActiveWorkProcessesFiltersForSave({
      q: '',
      competence: '',
      status: 'all'
    })).toBe(false)
  })
})

describe('saved-list-filters closing adapters', () => {
  it('serializa eixos e sanitiza root/client', () => {
    const payload = closingFiltersToPayload({
      competence: '2026-07',
      band: 'OVERDUE',
      model: '55',
      root: '12.345.678/0001-90',
      source: 'SVRS',
      client_id: '42'
    })
    expect(payload).toMatchObject({
      schema_version: SAVED_LIST_SCHEMA_VERSION,
      competence: '2026-07',
      band: 'OVERDUE',
      model: '55',
      root: '12345678',
      source: 'SVRS',
      client_id: '42'
    })
    const back = closingPayloadToFilters(payload, '2026-01')
    expect(back).toMatchObject({
      competence: '2026-07',
      band: 'OVERDUE',
      model: '55',
      root: '12345678',
      source: 'SVRS',
      client_id: '42'
    })
  })

  it('fallback de competência inválida e detecção de conteúdo', () => {
    expect(closingPayloadToFilters({
      schema_version: 1,
      competence: 'bad',
      band: 'all',
      model: 'all',
      root: '',
      source: 'all',
      client_id: ''
    }, '2026-03').competence).toBe('2026-03')
    expect(hasActiveClosingFiltersForSave({
      competence: '2026-07',
      band: FILTER_ALL,
      model: FILTER_ALL,
      root: '',
      source: FILTER_ALL,
      client_id: ''
    })).toBe(true)
    expect(hasActiveClosingFiltersForSave({
      competence: '',
      band: FILTER_ALL,
      model: FILTER_ALL,
      root: '',
      source: FILTER_ALL,
      client_id: ''
    })).toBe(false)
  })
})

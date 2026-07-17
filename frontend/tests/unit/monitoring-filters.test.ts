import { describe, expect, it } from 'vitest'
import {
  countActiveMonitoringFilters,
  EMPTY_MONITORING_FILTERS,
  modelsToMonitoringFilters,
  monitoringFieldsToDefinitions,
  monitoringFilterSignature,
  monitoringFiltersToModels,
  normalizeMonitoringFilters,
  resetMonitoringFilters,
  resolveMonitoringFilterFields
} from '../../app/utils/monitoring-filters'
import type { MonitoringFilterConfig } from '../../app/types/fiscal-modules'

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
    },
    {
      key: 'coverage',
      kind: 'option',
      label: 'Cobertura',
      items: [
        { label: 'Todas', value: 'all' },
        { label: 'Plena', value: 'FULL' },
        { label: 'Parcial', value: 'PARTIAL' }
      ]
    },
    {
      key: 'modality',
      kind: 'option',
      label: 'Modalidade',
      items: [
        { label: 'Todas', value: 'all' },
        { label: 'PARCSN', value: 'PARCSN' }
      ]
    }
  ]
}

describe('monitoring filters', () => {
  it('EMPTY inclui coverage e modality default all', () => {
    expect(EMPTY_MONITORING_FILTERS).toMatchObject({
      coverage: 'all',
      modality: 'all',
      q: '',
      situation: 'all',
      clientIds: []
    })
  })

  it('normaliza todos os campos em um único valor controlado', () => {
    expect(normalizeMonitoringFilters({
      q: ' ACME ',
      clientIds: [12],
      status: '',
      coverage: '',
      modality: undefined
    })).toEqual({
      ...EMPTY_MONITORING_FILTERS,
      q: 'ACME',
      clientIds: [12],
      status: 'all',
      coverage: 'all',
      modality: 'all'
    })
  })

  it('conta somente filtros estruturados configurados e ativos', () => {
    const filters = normalizeMonitoringFilters({
      q: 'fora do contador',
      situation: 'PENDING',
      competence: '2026-07',
      clientIds: [42],
      status: 'ACTIVE',
      coverage: 'FULL',
      modality: 'PARCSN'
    })
    expect(countActiveMonitoringFilters(filters, config)).toBe(6)
  })

  it('reseta todos os filtros e gera uma assinatura estável', () => {
    const reset = resetMonitoringFilters()
    expect(reset).toEqual(EMPTY_MONITORING_FILTERS)
    expect(reset).not.toBe(EMPTY_MONITORING_FILTERS)
    expect(monitoringFilterSignature(reset)).toBe(monitoringFilterSignature({ ...reset }))
    expect(monitoringFilterSignature({ ...reset, coverage: 'FULL' }))
      .not.toBe(monitoringFilterSignature(reset))
  })

  it('multi só em eixos portfolio com IN; status/coverage single; override field.multiple', () => {
    const defs = monitoringFieldsToDefinitions(resolveMonitoringFilterFields(config))
    expect(defs.find(d => d.key === 'situation')).toMatchObject({ kind: 'option', multiple: true })
    // status/paymentStatus: API de listagem ainda é equality — single até whereIn.
    expect(defs.find(d => d.key === 'status')).toMatchObject({ kind: 'option', multiple: false })
    expect(defs.find(d => d.key === 'modality')).toMatchObject({ kind: 'option', multiple: true })
    expect(defs.find(d => d.key === 'coverage')).toMatchObject({ kind: 'option', multiple: false })

    const withOverride = monitoringFieldsToDefinitions([
      {
        key: 'modality',
        kind: 'option',
        label: 'Modalidade',
        items: [{ label: 'Todas', value: 'all' }, { label: 'PARCSN', value: 'PARCSN' }],
        multiple: false
      },
      {
        key: 'status',
        kind: 'option',
        label: 'Status',
        items: [{ label: 'Todos', value: 'all' }, { label: 'Ativo', value: 'ACTIVE' }],
        multiple: true
      }
    ])
    expect(withOverride.find(d => d.key === 'modality')?.multiple).toBe(false)
    expect(withOverride.find(d => d.key === 'status')?.multiple).toBe(true)
  })

  it('round-trip multi situação no adapter monitoring', () => {
    const filters = normalizeMonitoringFilters({
      situation: 'ATTENTION,PENDING',
      q: 'x'
    })
    const models = monitoringFiltersToModels(filters, config)
    expect(models.find(m => m.key === 'situation')).toMatchObject({
      operator: 'in',
      value: 'ATTENTION,PENDING'
    })
    const back = modelsToMonitoringFilters(models, config, { q: 'x' })
    expect(back.situation).toBe('ATTENTION,PENDING')
    expect(back.q).toBe('x')
  })

  it('converte chips ↔ MonitoringFilterValue omitindo defaults', () => {
    const filters = normalizeMonitoringFilters({
      q: 'busca',
      situation: 'PENDING',
      competence: '2026-07',
      clientIds: [7],
      status: 'all',
      coverage: 'PARTIAL',
      modality: 'all'
    })
    const models = monitoringFiltersToModels(filters, config, 'Cliente 7')
    expect(models.map(m => m.key)).toEqual(['situation', 'competence', 'clientId', 'coverage'])
    expect(models.find(m => m.key === 'clientId')?.label).toBe('Cliente 7')

    const back = modelsToMonitoringFilters(models, config, { q: 'busca' })
    expect(back).toMatchObject({
      q: 'busca',
      situation: 'PENDING',
      competence: '2026-07',
      clientIds: [7],
      status: 'all',
      coverage: 'PARTIAL',
      modality: 'all'
    })
  })

  it('resolve fields a partir de config legada advanced', () => {
    const legacy: MonitoringFilterConfig = {
      advanced: [
        { key: 'clientId', kind: 'client', label: 'Cliente' },
        { key: 'competence', kind: 'month', label: 'Competência' }
      ]
    }
    const fields = resolveMonitoringFilterFields(legacy)
    expect(fields.map(f => f.key)).toEqual(['clientId', 'competence'])
    expect(monitoringFieldsToDefinitions(fields)).toHaveLength(2)
  })
})

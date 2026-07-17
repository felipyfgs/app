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
    }
  ]
}

describe('monitoring filters', () => {
  it('normaliza todos os campos em um único valor controlado', () => {
    expect(normalizeMonitoringFilters({ q: ' ACME ', clientId: 12.8, status: '' })).toEqual({
      ...EMPTY_MONITORING_FILTERS,
      q: 'ACME',
      clientId: 12,
      status: 'all'
    })
  })

  it('conta somente filtros estruturados configurados e ativos', () => {
    const filters = normalizeMonitoringFilters({
      q: 'fora do contador',
      situation: 'PENDING',
      competence: '2026-07',
      clientId: 42,
      status: 'ACTIVE'
    })
    expect(countActiveMonitoringFilters(filters, config)).toBe(4)
  })

  it('reseta todos os filtros e gera uma assinatura estável', () => {
    const reset = resetMonitoringFilters()
    expect(reset).toEqual(EMPTY_MONITORING_FILTERS)
    expect(reset).not.toBe(EMPTY_MONITORING_FILTERS)
    expect(monitoringFilterSignature(reset)).toBe(monitoringFilterSignature({ ...reset }))
  })

  it('converte chips ↔ MonitoringFilterValue omitindo defaults', () => {
    const filters = normalizeMonitoringFilters({
      q: 'busca',
      situation: 'PENDING',
      competence: '2026-07',
      clientId: 7,
      status: 'all'
    })
    const models = monitoringFiltersToModels(filters, config, 'Cliente 7')
    expect(models.map(m => m.key)).toEqual(['situation', 'competence', 'clientId'])
    expect(models.find(m => m.key === 'clientId')?.label).toBe('Cliente 7')

    const back = modelsToMonitoringFilters(models, config, { q: 'busca' })
    expect(back).toMatchObject({
      q: 'busca',
      situation: 'PENDING',
      competence: '2026-07',
      clientId: 7,
      status: 'all'
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

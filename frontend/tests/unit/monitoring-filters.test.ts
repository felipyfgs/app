import { describe, expect, it } from 'vitest'
import {
  countActiveMonitoringFilters,
  EMPTY_MONITORING_FILTERS,
  monitoringFilterSignature,
  normalizeMonitoringFilters,
  resetMonitoringFilters
} from '../../app/utils/monitoring-filters'
import type { MonitoringFilterConfig } from '../../app/types/fiscal-modules'

const config: MonitoringFilterConfig = {
  advanced: [
    { key: 'competence', kind: 'month', label: 'Competência' },
    { key: 'clientId', kind: 'client', label: 'Cliente' },
    {
      key: 'status',
      kind: 'select',
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

  it('conta somente filtros avançados configurados e ativos', () => {
    const filters = normalizeMonitoringFilters({
      q: 'fora do contador',
      situation: 'PENDING',
      competence: '2026-07',
      clientId: 42,
      status: 'ACTIVE'
    })
    expect(countActiveMonitoringFilters(filters, config)).toBe(3)
  })

  it('reseta todos os filtros e gera uma assinatura estável', () => {
    const reset = resetMonitoringFilters()
    expect(reset).toEqual(EMPTY_MONITORING_FILTERS)
    expect(reset).not.toBe(EMPTY_MONITORING_FILTERS)
    expect(monitoringFilterSignature(reset)).toBe(monitoringFilterSignature({ ...reset }))
  })
})

import { describe, expect, it } from 'vitest'
import {
  MONITORING_NAV_ITEMS,
  monitoringNavActiveModule,
  monitoringPathForModule
} from '../../app/utils/monitoring-nav'

describe('monitoring nav — cadastro e processos', () => {
  it('inclui rotas de registrations e tax-processes', () => {
    const ids = MONITORING_NAV_ITEMS.map(i => i.id)
    expect(ids).toContain('monitoring-registrations')
    expect(ids).toContain('monitoring-tax-processes')
    expect(monitoringPathForModule('registrations')).toBe('/monitoring/registrations')
    expect(monitoringPathForModule('tax_processes')).toBe('/monitoring/tax-processes')
  })

  it('resolve módulo ativo pelas rotas', () => {
    expect(monitoringNavActiveModule('/monitoring/registrations')).toBe('registrations')
    expect(monitoringNavActiveModule('/monitoring/tax-processes')).toBe('tax_processes')
  })

  it('não embute office_id em paths de módulo', () => {
    for (const item of MONITORING_NAV_ITEMS) {
      expect(item.to).not.toMatch(/office_id/i)
      expect(item.to).toMatch(/^\/monitoring/)
    }
  })
})

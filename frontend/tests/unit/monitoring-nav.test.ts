import { describe, expect, it } from 'vitest'
import {
  MONITORING_NAV_ITEMS,
  monitoringNavActiveModule,
  monitoringNavMenuItems,
  monitoringPathForModule
} from '../../app/utils/monitoring-nav'

describe('MonitoringModuleNav items (6.3)', () => {
  it('lista todos os destinos do hub fiscal', () => {
    const ids = MONITORING_NAV_ITEMS.map(i => i.id)
    expect(ids).toEqual([
      'monitoring-dashboard',
      'monitoring-simples-mei',
      'monitoring-dctfweb',
      'monitoring-fgts',
      'monitoring-installments',
      'monitoring-sitfis',
      'monitoring-mailbox',
      'monitoring-declarations',
      'monitoring-guides'
    ])
  })

  it('resolve módulo ativo por path (incluindo detalhe aninhado)', () => {
    expect(monitoringNavActiveModule('/monitoring')).toBe('dashboard')
    expect(monitoringNavActiveModule('/monitoring/simples-mei')).toBe('simples_mei')
    expect(monitoringNavActiveModule('/monitoring/mailbox/42')).toBe('mailbox')
    expect(monitoringNavActiveModule('/monitoring/clients/9')).toBe('dashboard')
  })

  it('marca item ativo no menu highlight', () => {
    const items = monitoringNavMenuItems('/monitoring/fgts')
    const active = items.filter(i => i.active)
    expect(active).toHaveLength(1)
    expect(active[0]?.to).toBe('/monitoring/fgts')
  })

  it('paths batem com o catálogo de módulos', () => {
    expect(monitoringPathForModule('dctfweb')).toBe('/monitoring/dctfweb')
    expect(monitoringPathForModule('dashboard')).toBe('/monitoring')
  })

  it('aceita activeOverride (prop active das páginas)', () => {
    const items = monitoringNavMenuItems('/monitoring', 'mailbox')
    expect(items.find(i => i.active)?.to).toBe('/monitoring/mailbox')
    expect(items.filter(i => i.active)).toHaveLength(1)
  })

  it('cada item tem label e destino /monitoring', () => {
    for (const item of MONITORING_NAV_ITEMS) {
      expect(item.label.length).toBeGreaterThan(0)
      expect(item.to.startsWith('/monitoring')).toBe(true)
      expect(item.icon).toMatch(/^i-lucide-/)
    }
  })

  it('paths públicos batem com moduleKey (sem duplicata de rota)', () => {
    const paths = MONITORING_NAV_ITEMS.map(i => i.to)
    expect(new Set(paths).size).toBe(paths.length)
    const keys = MONITORING_NAV_ITEMS.map(i => i.moduleKey)
    expect(new Set(keys).size).toBe(keys.length)
  })

  it('dashboard é exact; demais aceitam nested path', () => {
    expect(monitoringNavActiveModule('/monitoring/')).toBe('dashboard')
    // path desconhecido sob /monitoring cai no dashboard (fallback)
    expect(monitoringNavActiveModule('/monitoring/extra')).toBe('dashboard')
    // nested de mailbox
    expect(monitoringNavActiveModule('/monitoring/mailbox/99/anexo')).toBe('mailbox')
    // exact: /monitoring/guides-extra não ativa guides
    expect(monitoringNavActiveModule('/monitoring/guides-extra')).toBe('dashboard')
  })
})

import { describe, expect, it } from 'vitest'
import {
  flattenDestinations,
  mainDestinations,
  monitoringDestinations
} from '../../app/utils/navigation'
import type { MeUser } from '../../app/types/api'

function user(role: MeUser['role'], confirmed = true): MeUser {
  return {
    id: 1,
    name: 'Teste',
    email: 't@example.com',
    two_factor_confirmed: confirmed,
    two_factor_required: true,
    requires_two_factor_setup: false,
    office: { id: 1, name: 'Escritório', slug: 'escritorio' },
    role
  }
}

describe('navigation monitoramento (15.4)', () => {
  it('expõe todos os módulos fiscais do catálogo', () => {
    const ids = monitoringDestinations('/monitoring').flatMap((g) => {
      if (g.children) return g.children.map(c => c.id)
      return [g.id]
    })
    expect(ids).toEqual([
      'monitoring-dashboard',
      'monitoring-simples-mei',
      'monitoring-dctfweb',
      'monitoring-installments',
      'monitoring-sitfis',
      'monitoring-mailbox',
      'monitoring-declarations',
      'monitoring-guides',
      'monitoring-fgts',
      'monitoring-registrations',
      'monitoring-tax-processes'
    ])
  })

  it('FGTS é rotulado como parcial na navegação', () => {
    const fgts = monitoringDestinations()[0]?.children?.find(c => c.id === 'monitoring-fgts')
    expect(fgts?.label).toMatch(/parcial/i)
    expect(fgts?.to).toBe('/monitoring/fgts')
  })

  it('mainDestinations inclui Monitoramento para qualquer papel autenticado', () => {
    for (const role of ['VIEWER', 'OPERATOR', 'ADMIN'] as const) {
      const tree = mainDestinations(user(role, role === 'ADMIN'))
      expect(tree.map(d => d.id)).toContain('monitoring')
      const flat = flattenDestinations(tree).map(d => d.id)
      expect(flat).toContain('monitoring-dashboard')
      expect(flat).toContain('monitoring-fgts')
    }
  })

  it('ADMIN vê Configurações (escritório + consumo + equipe + departamentos), sem hub /admin', () => {
    const tree = mainDestinations(user('ADMIN', true))
    const settings = tree.find(d => d.id === 'settings')
    expect(settings?.children?.map(c => c.id)).toEqual([
      'settings-office',
      'settings-usage',
      'settings-subscription',
      'settings-departments',
      'settings-team'
    ])
    expect(settings?.children?.map(c => c.id)).not.toContain('settings-cte')
    expect(settings?.children?.map(c => c.id)).not.toContain('admin-departments')
    expect(settings?.children?.map(c => c.to)).not.toContain('/settings/cte')
    expect(settings?.children?.map(c => c.to)).toContain('/settings/departments')
    // Hub plataforma fica fora da árvore do office ADMIN
    const flat = flattenDestinations(tree).map(d => d.id)
    expect(flat).not.toContain('platform-serpro-console')
  })

  it('VIEWER não vê Configurações/Admin', () => {
    const ids = flattenDestinations(mainDestinations(user('VIEWER'))).map(d => d.id)
    expect(ids).not.toContain('admin')
    expect(ids).not.toContain('settings-office')
    expect(ids).not.toContain('settings-usage')
    expect(ids).not.toContain('settings-cte')
  })
})

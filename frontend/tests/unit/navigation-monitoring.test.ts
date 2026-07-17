import { describe, expect, it } from 'vitest'
import {
  flattenDestinations,
  mainDestinations,
  monitoringDestinations,
  toNavigationItems
} from '../../app/utils/navigation'
import { MONITORING_NAV_ITEMS } from '../../app/utils/monitoring-nav'
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
  it('mantém todos os módulos diretamente acessíveis na sidebar', () => {
    const ids = monitoringDestinations('/monitoring').flatMap((g) => {
      if (g.children) return g.children.map(c => c.id)
      return [g.id]
    })
    expect(ids).toEqual([
      'monitoring-dashboard',
      'monitoring-simples-mei',
      'monitoring-dctfweb',
      'monitoring-fgts',
      'monitoring-installments',
      'monitoring-sitfis',
      'monitoring-mailbox',
      'monitoring-declarations',
      'monitoring-guides',
      'monitoring-registrations',
      'monitoring-tax-processes'
    ])
  })

  it('usa os paths canônicos dos módulos com submódulos', () => {
    const children = monitoringDestinations()[0]?.children || []
    expect(children.find(c => c.id === 'monitoring-simples-mei')?.to)
      .toBe('/monitoring/simples-mei')
    expect(children.find(c => c.id === 'monitoring-dctfweb')?.to)
      .toBe('/monitoring/dctfweb')
  })

  it('mantém ícone no grupo e omite ícones dos módulos no submenu da sidebar', () => {
    const [monitoring] = toNavigationItems(monitoringDestinations('/monitoring'))
    expect(monitoring?.icon).toBe('i-lucide-radar')
    expect(monitoring?.children?.every(item => item.icon == null)).toBe(true)
  })

  it('resume somente os nomes do submenu da sidebar', () => {
    const children = monitoringDestinations('/monitoring')[0]?.children || []
    expect(children.map(item => item.label)).toEqual([
      'Resumo',
      'Simples/MEI',
      'DCTFWeb/MIT',
      'FGTS',
      'Parcelamentos',
      'SITFIS',
      'Caixas',
      'Declarações',
      'Guias',
      'Vínculos',
      'Processos'
    ])
    expect(children.map(item => item.label)).toEqual(
      MONITORING_NAV_ITEMS.map(item => item.sidebarLabel)
    )
    expect(children.map(item => item.label)).not.toEqual(
      MONITORING_NAV_ITEMS.map(item => item.label)
    )
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

  it('ADMIN vê Conta (perfil + escritório + consumo + equipe + departamentos), sem hub /admin', () => {
    const tree = mainDestinations(user('ADMIN', true))
    const account = tree.find(d => d.id === 'settings')
    expect(account?.children?.map(c => c.id)).toEqual([
      'account-profile',
      'account-office',
      'account-usage',
      'account-subscription',
      'account-team',
      'account-departments'
    ])
    expect(account?.children?.map(c => c.id)).not.toContain('settings-cte')
    expect(account?.children?.map(c => c.id)).not.toContain('admin-departments')
    expect(account?.children?.map(c => c.to)).not.toContain('/settings/cte')
    expect(account?.children?.map(c => c.to)).toContain('/conta/departamentos')
    // Hub plataforma fica fora da árvore do office ADMIN
    const flat = flattenDestinations(tree).map(d => d.id)
    expect(flat).not.toContain('platform-serpro-console')
  })

  it('VIEWER não vê configurações administrativas na sidebar', () => {
    const tree = mainDestinations(user('VIEWER'))
    const ids = flattenDestinations(tree).map(d => d.id)
    expect(ids).not.toContain('admin')
    expect(ids).toContain('account-profile')
    expect(ids).not.toContain('account-office')
    expect(ids).not.toContain('account-usage')
    expect(ids).not.toContain('settings-cte')
    expect(tree.find(d => d.id === 'settings')?.children?.map(d => d.id))
      .toEqual(['account-profile'])
  })
})

import { describe, expect, it } from 'vitest'
import {
  flattenDestinations,
  mainDestinations,
  monitoringDestinations,
  searchableDestinations,
  toNavigationItems
} from '../../app/utils/navigation'
import { FISCAL_NAV_ITEMS, fiscalNavigationItems } from '../../app/utils/fiscal-navigation'
import { flattenNavLeaves } from '../../app/utils/navigation-hierarchy'
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

describe('navigation fiscal (grupos canônicos)', () => {
  it('sidebar Fiscal expõe até cinco grupos, não onze módulos', () => {
    const children = monitoringDestinations('/monitoring')[0]?.children || []
    expect(children.map(c => c.id)).toEqual([
      'fiscal-overview',
      'fiscal-obligations',
      'fiscal-regularity',
      'fiscal-finance',
      'fiscal-comms'
    ])
    expect(children).toHaveLength(5)
    expect(monitoringDestinations('/monitoring')[0]?.label).toBe('Fiscal')
  })

  it('grupos apontam ao primeiro destino folha do catálogo', () => {
    const children = monitoringDestinations()[0]?.children || []
    expect(children.find(c => c.id === 'fiscal-obligations')?.to)
      .toBe('/monitoring/simples-mei')
    expect(children.find(c => c.id === 'fiscal-comms')?.to)
      .toBe('/monitoring/mailbox')
  })

  it('mantém ícone no grupo e omite ícones dos itens no submenu da sidebar', () => {
    const [monitoring] = toNavigationItems(monitoringDestinations('/monitoring'))
    expect(monitoring?.icon).toBe('i-lucide-radar')
    expect(monitoring?.children?.every(item => item.icon == null)).toBe(true)
  })

  it('busca global indexa todos os módulos folha', () => {
    const ids = searchableDestinations(user('OPERATOR')).map(d => d.id)
    expect(ids).toContain('monitoring-dashboard')
    expect(ids).toContain('monitoring-simples-mei')
    expect(ids).toContain('monitoring-mailbox')
    expect(ids).toContain('monitoring-tax-processes')
    expect(flattenNavLeaves(FISCAL_NAV_ITEMS)).toHaveLength(11)
  })

  it('fiscalNavigationItems mantém catálogo enquanto me hidrata', () => {
    expect(fiscalNavigationItems(null)).toEqual(FISCAL_NAV_ITEMS)
    expect(fiscalNavigationItems(undefined)).toEqual(FISCAL_NAV_ITEMS)
    expect(flattenNavLeaves(fiscalNavigationItems(user('OPERATOR')))).toHaveLength(11)
    expect(fiscalNavigationItems({
      id: 1,
      name: 'X',
      email: 'x@example.com',
      two_factor_confirmed: false,
      two_factor_required: false,
      requires_two_factor_setup: false,
      office: null,
      role: null
    } as MeUser)).toEqual([])
  })

  it('mainDestinations inclui Fiscal para qualquer papel autenticado', () => {
    for (const role of ['VIEWER', 'OPERATOR', 'ADMIN'] as const) {
      const tree = mainDestinations(user(role, role === 'ADMIN'))
      expect(tree.map(d => d.id)).toContain('monitoring')
      expect(tree.find(d => d.id === 'monitoring')?.label).toBe('Fiscal')
      const flat = flattenDestinations(tree).map(d => d.id)
      expect(flat).toContain('fiscal-overview')
      expect(flat).toContain('fiscal-obligations')
    }
  })

  it('ADMIN vê Conta agrupada em folhas na ordem da taxonomia', () => {
    const tree = mainDestinations(user('ADMIN', true))
    const account = tree.find(d => d.id === 'settings')
    expect(account?.label).toBe('Conta')
    expect(account?.children?.map(c => c.id)).toEqual([
      'account-profile',
      'account-office',
      'account-departments',
      'account-team',
      'account-subscription',
      'account-usage'
    ])
    const flat = flattenDestinations(tree).map(d => d.id)
    expect(flat).not.toContain('platform-serpro-console')
  })

  it('VIEWER não vê configurações administrativas na sidebar', () => {
    const tree = mainDestinations(user('VIEWER'))
    const ids = flattenDestinations(tree).map(d => d.id)
    expect(ids).not.toContain('admin')
    expect(ids).toContain('account-profile')
    expect(ids).not.toContain('account-office')
    expect(tree.find(d => d.id === 'settings')?.children?.map(d => d.id))
      .toEqual(['account-profile'])
  })
})

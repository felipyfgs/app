import { describe, expect, it } from 'vitest'
import {
  flattenDestinations,
  mainDestinations,
  quickActions,
  toNavigationItems
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

describe('navigation', () => {
  it('VIEWER não vê Administração nem ações rápidas de criação', () => {
    const ids = flattenDestinations(mainDestinations(user('VIEWER'))).map(d => d.id)
    expect(ids).not.toContain('admin')
    expect(quickActions(user('VIEWER'))).toEqual([])
  })

  it('OPERATOR tem ações de cliente e exportação, sem admin', () => {
    const ids = flattenDestinations(mainDestinations(user('OPERATOR'))).map(d => d.id)
    expect(ids).not.toContain('admin')
    const actions = quickActions(user('OPERATOR')).map(a => a.id)
    expect(actions).toContain('new-client')
    expect(actions).toContain('new-export')
  })

  it('ADMIN com 2FA vê Administração e ações rápidas', () => {
    const ids = flattenDestinations(mainDestinations(user('ADMIN', true))).map(d => d.id)
    expect(ids).toContain('admin')
    expect(quickActions(user('ADMIN', true)).length).toBeGreaterThan(0)
  })

  it('ADMIN sem 2FA não vê Administração nem ações de mutação', () => {
    const ids = flattenDestinations(mainDestinations(user('ADMIN', false))).map(d => d.id)
    expect(ids).not.toContain('admin')
    expect(quickActions(user('ADMIN', false))).toEqual([])
  })

  it('destinos folha do operador batem com o produto (Clientes e Operações em submenu)', () => {
    const tree = mainDestinations(user('OPERATOR'))
    expect(tree.map(d => d.id)).toEqual(['home', 'clients', 'docs', 'operations'])
    expect(tree.find(d => d.id === 'clients')?.children?.map(c => c.id)).toEqual([
      'clients-list',
      'clients-dashboard'
    ])
    expect(tree.find(d => d.id === 'operations')?.children?.map(c => c.id)).toEqual([
      'health',
      'exports',
      'syncs'
    ])
    expect(flattenDestinations(tree).map(d => d.id)).toEqual([
      'home',
      'clients-list',
      'clients-dashboard',
      'docs',
      'health',
      'exports',
      'syncs'
    ])
  })

  it('inclui destino Saúde para todos os papéis autenticados do escritório', () => {
    for (const role of ['VIEWER', 'OPERATOR', 'ADMIN'] as const) {
      const ids = flattenDestinations(mainDestinations(user(role, role === 'ADMIN'))).map(d => d.id)
      expect(ids).toContain('health')
    }
  })

  it('toNavigationItems gera trigger com children no estilo template Settings', () => {
    const items = toNavigationItems(mainDestinations(user('OPERATOR')), () => {})
    const clients = items.find(i => i.label === 'Clientes')
    expect(clients?.type).toBe('trigger')
    expect(clients?.children?.length).toBe(2)
    expect(clients?.to).toBeUndefined()
    const ops = items.find(i => i.label === 'Operações')
    expect(ops?.type).toBe('trigger')
    expect(ops?.children?.length).toBe(3)
    expect(ops?.to).toBeUndefined()
  })
})

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
    const actions = quickActions(user('VIEWER')).map(a => a.id)
    // Fila é navegação permitida; criação de cliente/export continua bloqueada.
    expect(actions).not.toContain('new-client')
    expect(actions).not.toContain('new-export')
    expect(actions).toContain('work-queue')
  })

  it('OPERATOR tem ações de cliente e exportação, sem admin', () => {
    const ids = flattenDestinations(mainDestinations(user('OPERATOR'))).map(d => d.id)
    expect(ids).not.toContain('admin')
    const actions = quickActions(user('OPERATOR')).map(a => a.id)
    expect(actions).toContain('new-client')
    expect(actions).toContain('new-export')
    expect(actions).toContain('work-queue')
  })

  it('ADMIN com 2FA vê Configurações do escritório (não hub plataforma)', () => {
    const ids = flattenDestinations(mainDestinations(user('ADMIN', true))).map(d => d.id)
    expect(ids).toContain('settings-office')
    expect(ids).toContain('settings-usage')
    expect(ids).not.toContain('admin')
    expect(ids).not.toContain('platform-serpro-console')
    expect(quickActions(user('ADMIN', true)).length).toBeGreaterThan(0)
  })

  it('ADMIN sem 2FA não vê Configurações nem ações de mutação', () => {
    const ids = flattenDestinations(mainDestinations(user('ADMIN', false))).map(d => d.id)
    expect(ids).not.toContain('admin')
    expect(ids).not.toContain('settings-office')
    const actions = quickActions(user('ADMIN', false)).map(a => a.id)
    expect(actions).not.toContain('new-client')
    expect(actions).not.toContain('new-export')
    // Minha fila permanece acessível (consulta).
    expect(actions).toContain('work-queue')
  })

  it('PLATFORM_ADMIN vê hub Plataforma e console SERPRO', () => {
    const plat: MeUser = {
      id: 9,
      name: 'Plat',
      email: 'p@example.com',
      two_factor_confirmed: false,
      two_factor_required: true,
      requires_two_factor_setup: false,
      is_platform_admin: true,
      office: null,
      role: null
    }
    const ids = flattenDestinations(mainDestinations(plat)).map(d => d.id)
    expect(ids).toContain('admin')
    expect(ids).toContain('platform-serpro-console')
  })

  it('destinos folha do operador batem com o produto (Trabalho, Clientes, Monitoramento, Documentos e Operações)', () => {
    const tree = mainDestinations(user('OPERATOR'))
    expect(tree.map(d => d.id)).toEqual(['home', 'work', 'clients', 'monitoring', 'docs', 'operations'])
    expect(tree.find(d => d.id === 'work')?.children?.map(c => c.id)).toEqual([
      'work-queue',
      'work-processes',
      'work-calendar'
    ])
    expect(tree.find(d => d.id === 'clients')?.children?.map(c => c.id)).toEqual([
      'clients-list',
      'clients-dashboard'
    ])
    expect(tree.find(d => d.id === 'docs')?.children?.map(c => c.id)).toEqual([
      'docs-by-client',
      'docs-catalog'
    ])
    expect(tree.find(d => d.id === 'operations')?.children?.map(c => c.id)).toEqual([
      'health',
      'exports',
      'closing',
      'syncs',
      'imports'
    ])
    // CT-e não é destino próprio — vive no catálogo Documentos.
    const flatOps = flattenDestinations(tree.find(d => d.id === 'operations')?.children || [])
    expect(flatOps.map(d => d.to)).not.toContain('/settings/cte')
    expect(flatOps.map(d => d.id)).not.toContain('cte-onboarding')
    const flat = flattenDestinations(tree).map(d => d.id)
    expect(flat).toContain('home')
    expect(flat).toContain('work-queue')
    expect(flat).toContain('clients-list')
    expect(flat).toContain('monitoring-dashboard')
    expect(flat).toContain('monitoring-fgts')
    expect(flat).toContain('docs-catalog')
    expect(flat).toContain('health')
  })

  it('usa paths canônicos para as visões de Documentos', () => {
    const tree = mainDestinations(user('OPERATOR'), {
      path: '/docs/catalog'
    })
    const docs = tree.find(d => d.id === 'docs')
    const byClient = docs?.children?.find(c => c.id === 'docs-by-client')
    const catalog = docs?.children?.find(c => c.id === 'docs-catalog')
    expect(byClient?.active).toBe(false)
    expect(docs?.defaultOpen).toBe(true)
    expect(catalog?.active).toBe(true)
    expect(catalog?.to).toBe('/docs/catalog')
    expect(byClient?.to).toBe('/docs')
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
    // value estável p/ Accordion type=single no sidebar
    expect(clients?.value).toBe('clients')
    const docs = items.find(i => i.label === 'Documentos')
    expect(docs?.type).toBe('trigger')
    expect(docs?.children?.length).toBe(2)
    expect(docs?.to).toBeUndefined()
    expect(docs?.value).toBe('docs')
    const ops = items.find(i => i.label === 'Operações')
    expect(ops?.type).toBe('trigger')
    expect(ops?.children?.length).toBe(5)
    expect(ops?.to).toBeUndefined()
    expect(ops?.value).toBe('operations')
  })

  it('em uma rota, no máximo um grupo trigger fica defaultOpen (acordeão single)', () => {
    for (const path of ['/monitoring/sitfis', '/clients', '/docs/catalog', '/work', '/exports', '/']) {
      const tree = mainDestinations(user('OPERATOR'), { path })
      const openTriggers = tree.filter(d => d.type === 'trigger' && d.defaultOpen)
      expect(openTriggers.length).toBeLessThanOrEqual(1)
      if (path.startsWith('/monitoring')) {
        expect(openTriggers[0]?.id).toBe('monitoring')
      }
      if (path === '/clients' || path.startsWith('/clients/')) {
        expect(openTriggers[0]?.id).toBe('clients')
      }
      if (path === '/') {
        expect(openTriggers).toHaveLength(0)
      }
    }
  })

  it('não expõe destino CT-e separado em sidebar nem Configurações', () => {
    for (const role of ['VIEWER', 'OPERATOR', 'ADMIN'] as const) {
      const flat = flattenDestinations(mainDestinations(user(role, role === 'ADMIN')))
      expect(flat.map(d => d.id)).not.toContain('cte-onboarding')
      expect(flat.map(d => d.id)).not.toContain('settings-cte')
      expect(flat.map(d => d.to)).not.toContain('/settings/cte')
      expect(flat.some(d => d.label === 'CT-e' && d.to === '/settings/cte')).toBe(false)
    }
    const adminFlat = flattenDestinations(mainDestinations(user('ADMIN', true)))
    expect(adminFlat.map(d => d.id)).toContain('docs-catalog')
    expect(adminFlat.find(d => d.id === 'docs-catalog')?.to).toBe('/docs/catalog')
  })
})

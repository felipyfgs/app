import { describe, expect, it } from 'vitest'
import type { NavLayerItem, NavLeafDestination } from '../../app/utils/navigation-hierarchy'
import {
  NAV_LAYER_MAX_ITEMS,
  assertNavLayerLimit,
  filterNavItems,
  flattenNavLeaves,
  groupEntryTo,
  normalizeNavPath,
  pathMatchesLeaf,
  resolveNavSelection,
  resolveResponsiveVariant,
  validateNavCatalog
} from '../../app/utils/navigation-hierarchy'

const sample: NavLayerItem[] = [
  {
    id: 'overview',
    label: 'Visão geral',
    children: [
      { id: 'dashboard', label: 'Dashboard', to: '/monitoring', exact: true }
    ]
  },
  {
    id: 'obligations',
    label: 'Obrigações',
    children: [
      { id: 'simples', label: 'Simples/MEI', to: '/monitoring/simples-mei' },
      { id: 'dctf', label: 'DCTFWeb', to: '/monitoring/dctfweb' },
      { id: 'decl', label: 'Declarações', to: '/monitoring/declarations' }
    ]
  },
  {
    id: 'comms',
    label: 'Comunicações',
    children: [
      { id: 'mailbox', label: 'Caixas', to: '/monitoring/mailbox' }
    ]
  }
]

describe('navigation-hierarchy', () => {
  it('normaliza path sem query, hash ou barra final', () => {
    expect(normalizeNavPath('/a/b/?x=1#y')).toBe('/a/b')
    expect(normalizeNavPath('/')).toBe('/')
  })

  it('faz match exact e prefix', () => {
    const exact: NavLeafDestination = { id: 'd', label: 'D', to: '/monitoring', exact: true }
    const prefix: NavLeafDestination = { id: 'm', label: 'M', to: '/monitoring/mailbox' }
    expect(pathMatchesLeaf('/monitoring', exact)).toBe(true)
    expect(pathMatchesLeaf('/monitoring/clients/1', exact)).toBe(false)
    expect(pathMatchesLeaf('/monitoring/mailbox/9', prefix)).toBe(true)
  })

  it('respeita isActive override para detalhe dinâmico', () => {
    const leaf: NavLeafDestination = {
      id: 'catalog',
      label: 'Catálogo',
      to: '/docs/catalog',
      isActive: p => p === '/docs/catalog' || (p.startsWith('/docs/') && !p.startsWith('/docs/imports'))
    }
    expect(pathMatchesLeaf('/docs/ABC', leaf)).toBe(true)
    expect(pathMatchesLeaf('/docs/imports', leaf)).toBe(false)
  })

  it('flatten preserva ordem e não muta entrada', () => {
    const frozen = structuredClone(sample)
    const leaves = flattenNavLeaves(sample)
    expect(leaves.map(l => l.id)).toEqual([
      'dashboard', 'simples', 'dctf', 'decl', 'mailbox'
    ])
    expect(sample).toEqual(frozen)
    leaves[0]!.label = 'mutado'
    expect((sample[0] as { children: NavLeafDestination[] }).children[0]!.label).toBe('Dashboard')
  })

  it('filtra destino não autorizado e remove grupo vazio', () => {
    const filtered = filterNavItems(sample, leaf => leaf.id !== 'mailbox' && leaf.id !== 'decl')
    expect(flattenNavLeaves(filtered).map(l => l.id)).toEqual([
      'dashboard', 'simples', 'dctf'
    ])
    expect(filtered.find(i => i.id === 'comms')).toBeUndefined()
    expect(sample).toHaveLength(3)
  })

  it('resolve grupo e subtab ativos; oculta subtabs unitárias', () => {
    const onSimples = resolveNavSelection(sample, '/monitoring/simples-mei/x')
    expect(onSimples.group?.id).toBe('obligations')
    expect(onSimples.leaf?.id).toBe('simples')
    expect(onSimples.hideSubtabs).toBe(false)
    expect(onSimples.subtabs).toHaveLength(3)

    const onDash = resolveNavSelection(sample, '/monitoring')
    expect(onDash.group?.id).toBe('overview')
    expect(onDash.leaf?.id).toBe('dashboard')
    expect(onDash.hideSubtabs).toBe(true)
    expect(onDash.subtabs).toHaveLength(0)
  })

  it('groupEntryTo usa o 1º filho por padrão (clique de grupo)', () => {
    const group = sample[1] as Extract<NavLayerItem, { children: NavLeafDestination[] }>
    expect(groupEntryTo(group)).toBe('/monitoring/simples-mei')
    // activeLeafId opcional permanece para callers legados; UI de grupo não deve usá-lo
    expect(groupEntryTo(group, 'decl')).toBe('/monitoring/declarations')
  })

  it('resolve /monitoring/simples-mei → Obrigações + Simples', () => {
    const sel = resolveNavSelection(sample, '/monitoring/simples-mei')
    expect(sel.group?.id).toBe('obligations')
    expect(sel.leaf?.id).toBe('simples')
    expect(groupEntryTo(sel.group!)).toBe('/monitoring/simples-mei')
  })

  it('limita cinco itens por camada', () => {
    expect(NAV_LAYER_MAX_ITEMS).toBe(5)
    expect(() => assertNavLayerLimit([1, 2, 3, 4, 5])).not.toThrow()
    expect(() => assertNavLayerLimit([1, 2, 3, 4, 5, 6], 'tabs')).toThrow(/excede 5/)
    expect(() => validateNavCatalog(sample)).not.toThrow()
  })

  it('variante responsiva usa seletor no viewport compacto', () => {
    expect(resolveResponsiveVariant(3, false)).toBe('tabs')
    expect(resolveResponsiveVariant(1, true)).toBe('tabs')
    expect(resolveResponsiveVariant(2, true)).toBe('select')
  })

  it('ordem estável após filtro parcial', () => {
    const filtered = filterNavItems(sample, leaf => leaf.id !== 'simples')
    expect(flattenNavLeaves(filtered).map(l => l.id)).toEqual([
      'dashboard', 'dctf', 'decl', 'mailbox'
    ])
    const sel = resolveNavSelection(filtered, '/monitoring/dctfweb')
    expect(sel.tabs.map(t => t.id)).toEqual(['overview', 'obligations', 'comms'])
  })
})

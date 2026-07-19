/**
 * Navegação do detalhe fiscal do cliente (`/monitoring/clients/:id/:section?`).
 * Catálogo plano — sidebar interno (UNavigationMenu vertical).
 */
import type { NavigationMenuItem } from '@nuxt/ui'
import type { NavLayerItem, NavLeafDestination } from '~/utils/navigation-hierarchy'
import { flattenNavLeaves, validateNavCatalog } from '~/utils/navigation-hierarchy'

export type ClientFiscalSectionKey
  = | 'overview'
    | 'runs'
    | 'findings'
    | 'pending'
    | 'installments'
    | 'declarations'
    | 'pgdasd'
    | 'guides'
    | 'fgts'
    | 'sitfis'
    | 'registrations'
    | 'ccmei'
    | 'renunciations'
    | 'tax_processes'

export const CLIENT_FISCAL_SECTION_KEYS: ClientFiscalSectionKey[] = [
  'overview',
  'runs',
  'findings',
  'pending',
  'installments',
  'declarations',
  'pgdasd',
  'guides',
  'fgts',
  'sitfis',
  'registrations',
  'ccmei',
  'renunciations',
  'tax_processes'
]

interface FiscalSectionDef {
  key: ClientFiscalSectionKey
  id: string
  label: string
  icon: string
}

/** Ordem estável do sidebar interno (folhas). */
const FISCAL_SECTIONS: FiscalSectionDef[] = [
  { key: 'overview', id: 'cf-overview', label: 'Visão geral', icon: 'i-lucide-layout-dashboard' },
  { key: 'runs', id: 'cf-runs', label: 'Execuções', icon: 'i-lucide-play' },
  { key: 'findings', id: 'cf-findings', label: 'Achados', icon: 'i-lucide-search' },
  { key: 'pending', id: 'cf-pending', label: 'Pendências', icon: 'i-lucide-circle-alert' },
  { key: 'declarations', id: 'cf-declarations', label: 'Declarações', icon: 'i-lucide-file-check-2' },
  { key: 'pgdasd', id: 'cf-pgdasd', label: 'PGDAS-D', icon: 'i-lucide-badge-percent' },
  { key: 'fgts', id: 'cf-fgts', label: 'FGTS', icon: 'i-lucide-landmark' },
  { key: 'installments', id: 'cf-installments', label: 'Parcelamentos', icon: 'i-lucide-calendar-range' },
  { key: 'guides', id: 'cf-guides', label: 'Guias', icon: 'i-lucide-receipt' },
  { key: 'sitfis', id: 'cf-sitfis', label: 'SITFIS', icon: 'i-lucide-clipboard-check' },
  { key: 'registrations', id: 'cf-registrations', label: 'Cadastro e Vínculos', icon: 'i-lucide-link-2' },
  { key: 'ccmei', id: 'cf-ccmei', label: 'CCMEI', icon: 'i-lucide-badge-check' },
  { key: 'renunciations', id: 'cf-renunciations', label: 'Renúncias', icon: 'i-lucide-unlink' },
  { key: 'tax_processes', id: 'cf-tax-processes', label: 'Processos Fiscais', icon: 'i-lucide-scale' }
]

function sectionPath(clientId: string | number, section: ClientFiscalSectionKey): string {
  const base = `/monitoring/clients/${clientId}`
  return section === 'overview' ? base : `${base}/${section}`
}

function sectionActive(section: ClientFiscalSectionKey, clientId: string | number) {
  const target = sectionPath(clientId, section)
  return (path: string) => {
    if (section === 'overview') {
      return path === target || path === `${target}/`
    }
    return path === target || path.startsWith(`${target}/`)
  }
}

function sectionLeaf(
  clientId: string | number,
  def: FiscalSectionDef
): NavLeafDestination {
  return {
    id: def.id,
    label: def.label,
    icon: def.icon,
    to: sectionPath(clientId, def.key),
    exact: def.key === 'overview',
    isActive: sectionActive(def.key, clientId)
  }
}

/** Catálogo plano (folhas) — sidebar interno / busca. */
export function clientFiscalDetailNav(clientId: string | number): NavLayerItem[] {
  const items = FISCAL_SECTIONS.map(def => sectionLeaf(clientId, def))
  validateNavCatalog(items, FISCAL_SECTIONS.length)
  return items
}

export function clientFiscalDetailLeaves(clientId: string | number): NavLeafDestination[] {
  return flattenNavLeaves(clientFiscalDetailNav(clientId))
}

/** Links para UNavigationMenu vertical do sidebar interno (grupo único). */
export function clientFiscalNavigationMenu(
  clientId: string | number,
  currentPath?: string
): NavigationMenuItem[][] {
  const path = currentPath || ''
  const items = FISCAL_SECTIONS.map((def): NavigationMenuItem => {
    const leaf = sectionLeaf(clientId, def)
    return {
      label: leaf.label,
      icon: leaf.icon,
      to: leaf.to,
      exact: leaf.exact === true,
      active: path ? sectionActive(def.key, clientId)(path) : undefined
    }
  })
  return [items]
}

export function sectionKeyFromFiscalPath(path: string): ClientFiscalSectionKey {
  const bare = path.replace(/\/$/, '')
  const match = bare.match(/\/monitoring\/clients\/[^/]+(?:\/([^/]+))?$/)
  const raw = match?.[1] || 'overview'
  return (CLIENT_FISCAL_SECTION_KEYS as string[]).includes(raw)
    ? raw as ClientFiscalSectionKey
    : 'overview'
}

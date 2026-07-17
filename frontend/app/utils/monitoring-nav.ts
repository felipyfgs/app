/**
 * Itens de navegação horizontal do Monitoramento (toolbar Settings-like).
 * Separado de components para testes unitários sem montar Vue.
 */
import type { NavigationMenuItem } from '@nuxt/ui'
import type { FiscalModuleKey } from '~/types/fiscal-modules'
import { FISCAL_MODULE_PATHS } from '~/types/fiscal-modules'

export type MonitoringModuleKey = FiscalModuleKey | 'registrations' | 'tax_processes'
export type RoutedMonitoringModuleKey = 'simples_mei' | 'dctfweb'

const MONITORING_EXTRA_PATHS: Record<Exclude<MonitoringModuleKey, FiscalModuleKey>, string> = {
  registrations: '/monitoring/registrations',
  tax_processes: '/monitoring/tax-processes'
}

export interface MonitoringNavItem {
  id: string
  /** Rótulo preservado nas tabs horizontais do MonitorHub. */
  label: string
  /** Rótulo curto usado somente no submenu Monitoramento da sidebar. */
  sidebarLabel: string
  icon: string
  to: string
  moduleKey: MonitoringModuleKey
  exact?: boolean
  /** Prefixo compartilhado por rotas canônicas com submódulo. */
  pathPrefix?: string
}

export const MONITORING_NAV_ITEMS: readonly MonitoringNavItem[] = [
  {
    id: 'monitoring-dashboard',
    label: 'Dashboard',
    sidebarLabel: 'Resumo',
    icon: 'i-lucide-gauge',
    to: '/monitoring',
    moduleKey: 'dashboard',
    exact: true
  },
  {
    id: 'monitoring-simples-mei',
    label: 'Simples / MEI',
    sidebarLabel: 'Simples/MEI',
    icon: 'i-lucide-badge-percent',
    to: '/monitoring/simples-mei/pgdasd',
    moduleKey: 'simples_mei',
    pathPrefix: '/monitoring/simples-mei'
  },
  {
    id: 'monitoring-dctfweb',
    label: 'DCTFWeb / MIT',
    sidebarLabel: 'DCTFWeb/MIT',
    icon: 'i-lucide-file-input',
    to: '/monitoring/dctfweb/dctfweb',
    moduleKey: 'dctfweb',
    pathPrefix: '/monitoring/dctfweb'
  },
  {
    id: 'monitoring-fgts',
    label: 'FGTS',
    sidebarLabel: 'FGTS',
    icon: 'i-lucide-landmark',
    to: '/monitoring/fgts',
    moduleKey: 'fgts'
  },
  {
    id: 'monitoring-installments',
    label: 'Parcelamentos',
    sidebarLabel: 'Parcelamentos',
    icon: 'i-lucide-calendar-range',
    to: '/monitoring/installments',
    moduleKey: 'installments'
  },
  {
    id: 'monitoring-sitfis',
    label: 'SITFIS',
    sidebarLabel: 'SITFIS',
    icon: 'i-lucide-clipboard-check',
    to: '/monitoring/sitfis',
    moduleKey: 'sitfis'
  },
  {
    id: 'monitoring-mailbox',
    label: 'Caixas Postais',
    sidebarLabel: 'Caixas',
    icon: 'i-lucide-mail',
    to: '/monitoring/mailbox',
    moduleKey: 'mailbox'
  },
  {
    id: 'monitoring-declarations',
    label: 'Declarações',
    sidebarLabel: 'Declarações',
    icon: 'i-lucide-file-check-2',
    to: '/monitoring/declarations',
    moduleKey: 'declarations'
  },
  {
    id: 'monitoring-guides',
    label: 'Guias',
    sidebarLabel: 'Guias',
    icon: 'i-lucide-receipt',
    to: '/monitoring/guides',
    moduleKey: 'guides'
  },
  {
    id: 'monitoring-registrations',
    label: 'Cadastro / Vínculos',
    sidebarLabel: 'Vínculos',
    icon: 'i-lucide-link-2',
    to: '/monitoring/registrations',
    moduleKey: 'registrations'
  },
  {
    id: 'monitoring-tax-processes',
    label: 'Processos fiscais',
    sidebarLabel: 'Processos',
    icon: 'i-lucide-scale',
    to: '/monitoring/tax-processes',
    moduleKey: 'tax_processes'
  }
] as const

const ROUTED_SUBMODULES = {
  simples_mei: {
    defaultValue: 'PGDASD',
    entries: [
      { value: 'PGDASD', slug: 'pgdasd' },
      { value: 'PGMEI', slug: 'pgmei' },
      { value: 'DASN_SIMEI', slug: 'dasn-simei' },
      { value: 'REGIME', slug: 'regime' }
    ]
  },
  dctfweb: {
    defaultValue: 'DCTFWEB',
    entries: [
      { value: 'DCTFWEB', slug: 'dctfweb' },
      { value: 'MIT', slug: 'mit' }
    ]
  }
} as const satisfies Record<RoutedMonitoringModuleKey, {
  defaultValue: string
  entries: readonly { value: string, slug: string }[]
}>

function firstQueryValue(raw: unknown): unknown {
  return Array.isArray(raw) ? raw[0] : raw
}

export function monitoringNavItemForModule(moduleKey: MonitoringModuleKey): MonitoringNavItem {
  return MONITORING_NAV_ITEMS.find(item => item.moduleKey === moduleKey)!
}

export function normalizeMonitoringSubmodule(
  moduleKey: RoutedMonitoringModuleKey,
  raw: unknown
): string {
  const definition = ROUTED_SUBMODULES[moduleKey]
  const candidate = String(firstQueryValue(raw) || '').trim().toLowerCase().replaceAll('_', '-')
  return definition.entries.find(entry =>
    entry.slug === candidate || entry.value.toLowerCase().replaceAll('_', '-') === candidate
  )?.value ?? definition.defaultValue
}

export function monitoringSubmodulePath(
  moduleKey: RoutedMonitoringModuleKey,
  raw: unknown
): string {
  const definition = ROUTED_SUBMODULES[moduleKey]
  const value = normalizeMonitoringSubmodule(moduleKey, raw)
  const slug = definition.entries.find(entry => entry.value === value)!.slug
  const moduleSegment = moduleKey === 'simples_mei' ? 'simples-mei' : 'dctfweb'
  return `/monitoring/${moduleSegment}/${slug}`
}

export function monitoringCanonicalQuery(
  _query: Record<string, unknown> = {}
): Record<string, never> {
  return {}
}

export function monitoringLegacySubmoduleLocation(
  moduleKey: RoutedMonitoringModuleKey,
  query: Record<string, unknown> = {}
) {
  const selected = firstQueryValue(query.submodule) ?? firstQueryValue(query.tab)
  return {
    path: monitoringSubmodulePath(moduleKey, selected),
    query: monitoringCanonicalQuery(query)
  }
}

export function monitoringNavActiveModule(path: string): MonitoringModuleKey {
  const p = path.split('?')[0] || path
  if (p === '/monitoring' || p === '/monitoring/') return 'dashboard'
  // detalhe de cliente fiscal não é item de nav — cai no dashboard
  if (p.startsWith('/monitoring/clients')) return 'dashboard'
  for (const item of MONITORING_NAV_ITEMS) {
    if (item.exact) {
      if (p === item.to) return item.moduleKey
      continue
    }
    const prefix = item.pathPrefix ?? item.to
    if (p === item.to || p === prefix || p.startsWith(`${prefix}/`)) {
      return item.moduleKey
    }
  }
  return 'dashboard'
}

/**
 * Itens do UNavigationMenu.
 * @param path rota atual (fallback se `activeOverride` omitido)
 * @param activeOverride módulo forçado (ex.: prop `active` das páginas)
 */
export function monitoringNavMenuItems(
  path = '',
  activeOverride?: MonitoringModuleKey | string | null
): NavigationMenuItem[] {
  const active = activeOverride
    ? String(activeOverride) as MonitoringModuleKey
    : monitoringNavActiveModule(path)
  return MONITORING_NAV_ITEMS.map(item => ({
    label: item.label,
    to: item.to,
    exact: item.exact === true,
    active: item.moduleKey === active
  }))
}

export function monitoringPathForModule(key: MonitoringModuleKey): string {
  if (key === 'registrations' || key === 'tax_processes') return MONITORING_EXTRA_PATHS[key]
  return FISCAL_MODULE_PATHS[key]
}

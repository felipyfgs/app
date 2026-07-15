/**
 * Itens de navegação horizontal do Monitoramento (toolbar Settings-like).
 * Separado de components para testes unitários sem montar Vue.
 */
import type { NavigationMenuItem } from '@nuxt/ui'
import type { FiscalModuleKey } from '~/types/fiscal-modules'
import { FISCAL_MODULE_PATHS } from '~/types/fiscal-modules'

export interface MonitoringNavItem {
  id: string
  label: string
  icon: string
  to: string
  moduleKey: FiscalModuleKey
  exact?: boolean
}

export const MONITORING_NAV_ITEMS: readonly MonitoringNavItem[] = [
  {
    id: 'monitoring-dashboard',
    label: 'Dashboard',
    icon: 'i-lucide-gauge',
    to: '/monitoring',
    moduleKey: 'dashboard',
    exact: true
  },
  {
    id: 'monitoring-simples-mei',
    label: 'Simples / MEI',
    icon: 'i-lucide-badge-percent',
    to: '/monitoring/simples-mei',
    moduleKey: 'simples_mei'
  },
  {
    id: 'monitoring-dctfweb',
    label: 'DCTFWeb / MIT',
    icon: 'i-lucide-file-input',
    to: '/monitoring/dctfweb',
    moduleKey: 'dctfweb'
  },
  {
    id: 'monitoring-fgts',
    label: 'FGTS',
    icon: 'i-lucide-landmark',
    to: '/monitoring/fgts',
    moduleKey: 'fgts'
  },
  {
    id: 'monitoring-installments',
    label: 'Parcelamentos',
    icon: 'i-lucide-calendar-range',
    to: '/monitoring/installments',
    moduleKey: 'installments'
  },
  {
    id: 'monitoring-sitfis',
    label: 'SITFIS',
    icon: 'i-lucide-clipboard-check',
    to: '/monitoring/sitfis',
    moduleKey: 'sitfis'
  },
  {
    id: 'monitoring-mailbox',
    label: 'Caixas Postais',
    icon: 'i-lucide-mail',
    to: '/monitoring/mailbox',
    moduleKey: 'mailbox'
  },
  {
    id: 'monitoring-declarations',
    label: 'Declarações',
    icon: 'i-lucide-file-check-2',
    to: '/monitoring/declarations',
    moduleKey: 'declarations'
  },
  {
    id: 'monitoring-guides',
    label: 'Guias',
    icon: 'i-lucide-receipt',
    to: '/monitoring/guides',
    moduleKey: 'guides'
  }
] as const

export function monitoringNavActiveModule(path: string): FiscalModuleKey {
  const p = path.split('?')[0] || path
  if (p === '/monitoring' || p === '/monitoring/') return 'dashboard'
  // detalhe de cliente fiscal não é item de nav — cai no dashboard
  if (p.startsWith('/monitoring/clients')) return 'dashboard'
  for (const item of MONITORING_NAV_ITEMS) {
    if (item.exact) {
      if (p === item.to) return item.moduleKey
      continue
    }
    if (p === item.to || p.startsWith(`${item.to}/`)) {
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
  activeOverride?: FiscalModuleKey | string | null
): NavigationMenuItem[] {
  const active = activeOverride
    ? String(activeOverride) as FiscalModuleKey
    : monitoringNavActiveModule(path)
  return MONITORING_NAV_ITEMS.map(item => ({
    label: item.label,
    icon: item.icon,
    to: item.to,
    exact: item.exact === true,
    active: item.moduleKey === active
  }))
}

export function monitoringPathForModule(key: FiscalModuleKey): string {
  return FISCAL_MODULE_PATHS[key]
}

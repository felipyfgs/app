/**
 * Itens de navegação horizontal do Monitoramento (toolbar Settings-like).
 * Separado de components para testes unitários sem montar Vue.
 */
import type { NavigationMenuItem } from '@nuxt/ui'
import type { FiscalModuleKey } from '~/types/fiscal-modules'
import { FISCAL_MODULE_PATHS } from '~/types/fiscal-modules'

export type MonitoringModuleKey = FiscalModuleKey | 'registrations' | 'tax_processes'

const MONITORING_EXTRA_PATHS: Record<Exclude<MonitoringModuleKey, FiscalModuleKey>, string> = {
  registrations: '/monitoring/registrations',
  tax_processes: '/monitoring/tax-processes'
}

export interface MonitoringNavItem {
  id: string
  label: string
  icon: string
  to: string
  moduleKey: MonitoringModuleKey
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
  },
  {
    id: 'monitoring-registrations',
    label: 'Cadastro / Vínculos',
    icon: 'i-lucide-link-2',
    to: '/monitoring/registrations',
    moduleKey: 'registrations'
  },
  {
    id: 'monitoring-tax-processes',
    label: 'Processos fiscais',
    icon: 'i-lucide-scale',
    to: '/monitoring/tax-processes',
    moduleKey: 'tax_processes'
  }
] as const

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
  activeOverride?: MonitoringModuleKey | string | null
): NavigationMenuItem[] {
  const active = activeOverride
    ? String(activeOverride) as MonitoringModuleKey
    : monitoringNavActiveModule(path)
  return MONITORING_NAV_ITEMS.map(item => ({
    label: item.label,
    icon: item.icon,
    to: item.to,
    exact: item.exact === true,
    active: item.moduleKey === active
  }))
}

export function monitoringPathForModule(key: MonitoringModuleKey): string {
  if (key === 'registrations' || key === 'tax_processes') return MONITORING_EXTRA_PATHS[key]
  return FISCAL_MODULE_PATHS[key]
}

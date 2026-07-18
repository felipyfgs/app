/**
 * Taxonomia canônica da área Fiscal (Monitoramento): 5 grupos × folhas.
 */
import type { MeUser } from '~/types/api'
import type { NavLayerItem, NavLeafDestination } from '~/utils/navigation-hierarchy'
import { MONITORING_NAV_ITEMS, type MonitoringModuleKey } from '~/utils/monitoring-nav'
import { canViewFiscal } from '~/utils/permissions'
import { filterNavItems, validateNavCatalog } from '~/utils/navigation-hierarchy'

function leafFor(moduleKey: MonitoringModuleKey, overrides?: Partial<NavLeafDestination>): NavLeafDestination {
  const item = MONITORING_NAV_ITEMS.find(entry => entry.moduleKey === moduleKey)!
  return {
    id: item.id,
    label: item.label,
    icon: item.icon,
    to: item.to,
    exact: item.exact,
    capability: 'view-fiscal',
    ...overrides
  }
}

/** Tabs → Subtabs da área Fiscal (máx. 5 × 5). */
export const FISCAL_NAV_ITEMS: NavLayerItem[] = [
  {
    id: 'fiscal-overview',
    label: 'Visão geral',
    icon: 'i-lucide-gauge',
    children: [leafFor('dashboard', { label: 'Dashboard fiscal' })]
  },
  {
    id: 'fiscal-obligations',
    label: 'Obrigações',
    icon: 'i-lucide-file-check-2',
    children: [
      leafFor('simples_mei'),
      leafFor('dctfweb'),
      leafFor('declarations')
    ]
  },
  {
    id: 'fiscal-regularity',
    label: 'Regularidade',
    icon: 'i-lucide-clipboard-check',
    children: [
      leafFor('sitfis'),
      leafFor('fgts', { label: 'FGTS / eSocial' }),
      leafFor('registrations'),
      leafFor('tax_processes')
    ]
  },
  {
    id: 'fiscal-finance',
    label: 'Financeiro',
    icon: 'i-lucide-wallet',
    children: [
      leafFor('installments'),
      leafFor('guides')
    ]
  },
  {
    id: 'fiscal-comms',
    label: 'Comunicações',
    icon: 'i-lucide-mail',
    children: [leafFor('mailbox')]
  }
]

validateNavCatalog(FISCAL_NAV_ITEMS)

/**
 * Catálogo fiscal filtrado por capacidade.
 * Enquanto `me` ainda não hidratou (`null`/`undefined`), devolve o catálogo
 * estático para não esvaziar a toolbar; o middleware/backend autoriza.
 */
export function fiscalNavigationItems(user?: MeUser | null): NavLayerItem[] {
  if (user == null) return FISCAL_NAV_ITEMS
  if (!canViewFiscal(user)) return []
  return filterNavItems(FISCAL_NAV_ITEMS, () => true)
}

/** Folhas na ordem da taxonomia (busca global / flatten). */
export function fiscalNavLeaves(): NavLeafDestination[] {
  return FISCAL_NAV_ITEMS.flatMap(item =>
    'children' in item ? item.children : [item]
  )
}

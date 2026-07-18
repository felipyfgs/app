import type { MeUser } from '~/types/api'
import type { NavLayerItem, NavLeafDestination } from '~/utils/navigation-hierarchy'
import { filterNavItems, flattenNavLeaves, validateNavCatalog } from '~/utils/navigation-hierarchy'
import {
  canAccessOfficeSettings,
  canAccessPlatformAdmin,
  canManageOfficeTeam,
  canManageWorkCatalog
} from '~/utils/permissions'

export interface AccountNavigationItem {
  id: string
  label: string
  icon: string
  to: string
  exact?: boolean
}

/**
 * Vocabulário canônico da Conta (folhas).
 * Perfil pertence ao User; Escritório e demais seções pertencem ao Office.
 */
export const ACCOUNT_NAVIGATION = {
  profile: {
    id: 'account-profile',
    label: 'Perfil',
    icon: 'i-lucide-user-round',
    to: '/conta',
    exact: true
  },
  office: {
    id: 'account-office',
    label: 'Escritório',
    icon: 'i-lucide-building-2',
    to: '/conta/escritorio',
    exact: true
  },
  usage: {
    id: 'account-usage',
    label: 'Consumo',
    icon: 'i-lucide-chart-pie',
    to: '/conta/consumo'
  },
  subscription: {
    id: 'account-subscription',
    label: 'Assinatura',
    icon: 'i-lucide-badge-check',
    to: '/conta/assinatura'
  },
  team: {
    id: 'account-team',
    label: 'Equipe',
    icon: 'i-lucide-users-round',
    to: '/conta/equipe'
  },
  departments: {
    id: 'account-departments',
    label: 'Departamentos',
    icon: 'i-lucide-building',
    to: '/conta/departamentos'
  }
} satisfies Record<string, AccountNavigationItem>

function leaf(item: AccountNavigationItem, capability?: string): NavLeafDestination {
  return {
    id: item.id,
    label: item.label,
    icon: item.icon,
    to: item.to,
    exact: item.exact,
    capability
  }
}

/** Hierarquia Tabs → Subtabs da Conta. */
export const ACCOUNT_NAV_ITEMS: NavLayerItem[] = [
  leaf(ACCOUNT_NAVIGATION.profile, 'profile'),
  {
    id: 'account-organization',
    label: 'Organização',
    icon: 'i-lucide-building-2',
    children: [
      leaf(ACCOUNT_NAVIGATION.office, 'office-settings'),
      leaf(ACCOUNT_NAVIGATION.departments, 'work-catalog')
    ]
  },
  {
    id: 'account-people',
    label: 'Pessoas e acesso',
    icon: 'i-lucide-users-round',
    children: [
      leaf(ACCOUNT_NAVIGATION.team, 'manage-team')
      // Perfis e permissões: só quando a change proprietária publicar a superfície.
    ]
  },
  {
    id: 'account-plan',
    label: 'Plano',
    icon: 'i-lucide-badge-check',
    children: [
      leaf(ACCOUNT_NAVIGATION.subscription, 'office-settings'),
      leaf(ACCOUNT_NAVIGATION.usage, 'office-settings')
    ]
  }
]

validateNavCatalog(ACCOUNT_NAV_ITEMS)

function allowAccountLeaf(user: MeUser | null | undefined, leafItem: NavLeafDestination): boolean {
  switch (leafItem.capability) {
    case 'profile':
      return true
    case 'office-settings':
      return canAccessOfficeSettings(user)
    case 'manage-team':
      return canManageOfficeTeam(user)
    case 'work-catalog':
      return canManageWorkCatalog(user)
    default:
      return true
  }
}

/** Catálogo hierárquico filtrado por capacidade. */
export function accountNavigationTree(user?: MeUser | null): NavLayerItem[] {
  if (!user) return []
  const office = user.current_office ?? user.office
  if (canAccessPlatformAdmin(user) && !office) return []

  return filterNavItems(ACCOUNT_NAV_ITEMS, leafItem => allowAccountLeaf(user, leafItem))
}

/** Folhas planas — sidebar, busca e compatibilidade com testes existentes. */
export function accountNavigationItems(user?: MeUser | null): AccountNavigationItem[] {
  return flattenNavLeaves(accountNavigationTree(user)).map(item => ({
    id: item.id,
    label: item.label,
    icon: item.icon || 'i-lucide-circle',
    to: item.to,
    exact: item.exact
  }))
}

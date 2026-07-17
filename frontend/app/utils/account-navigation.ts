import type { MeUser } from '~/types/api'
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
 * Vocabulário canônico da Conta.
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

/** Mesmos itens, nomes e ordem para sidebar, busca global e tabs da página. */
export function accountNavigationItems(user?: MeUser | null): AccountNavigationItem[] {
  if (!user) return []
  const office = user.current_office ?? user.office
  if (canAccessPlatformAdmin(user) && !office) return []

  const items: AccountNavigationItem[] = [ACCOUNT_NAVIGATION.profile]

  if (!canAccessOfficeSettings(user)) return items

  items.push(
    ACCOUNT_NAVIGATION.office,
    ACCOUNT_NAVIGATION.usage,
    ACCOUNT_NAVIGATION.subscription
  )

  // Equipe + Departamentos: paridade Office ADMIN e PLATFORM_ADMIN privilegiado.
  if (canManageOfficeTeam(user)) {
    items.push(ACCOUNT_NAVIGATION.team)
  }

  if (canManageWorkCatalog(user)) {
    items.push(ACCOUNT_NAVIGATION.departments)
  }

  return items
}

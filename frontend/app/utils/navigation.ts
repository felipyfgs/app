import type { NavigationMenuItem } from '@nuxt/ui'
import type { MeUser } from '~/types/api'
import {
  canCreateExport,
  canManageClients,
  hasConfirmedAdminAccess
} from '~/utils/permissions'

export interface NavDestination {
  id: string
  label: string
  icon: string
  to?: string
  target?: string
  external?: boolean
  exact?: boolean
  /** Grupo colapsável no estilo Settings do template (UNavigationMenu type=trigger). */
  type?: 'trigger'
  defaultOpen?: boolean
  children?: NavDestination[]
}

export interface QuickAction {
  id: string
  label: string
  icon: string
  to: string
}

/**
 * Destinos principais do painel, no mesmo “ritmo” do template:
 * Home / Customers / Inbox + grupo colapsável (Settings) + secundários em outro menu.
 */
export function mainDestinations(
  user?: MeUser | null,
  options?: { path?: string }
): NavDestination[] {
  const path = options?.path || ''
  // Mantém o grupo expandido quando a rota atual está dentro do módulo.
  const clientsOpen = !path || path === '/clients' || path.startsWith('/clients/')
  const operationsOpen = !path
    || path.startsWith('/exports')
    || path.startsWith('/syncs')
    || path.startsWith('/health')

  const items: NavDestination[] = [
    {
      id: 'home',
      label: 'Início',
      icon: 'i-lucide-house',
      to: '/',
      exact: true
    },
    {
      id: 'clients',
      label: 'Clientes',
      icon: 'i-lucide-users',
      type: 'trigger',
      defaultOpen: clientsOpen,
      children: [
        {
          id: 'clients-list',
          label: 'Lista',
          icon: 'i-lucide-list',
          to: '/clients',
          exact: true
        },
        {
          id: 'clients-dashboard',
          label: 'Dashboard',
          icon: 'i-lucide-layout-dashboard',
          to: '/clients/dashboard'
        }
      ]
    },
    {
      id: 'docs',
      label: 'Documentos',
      icon: 'i-lucide-file-stack',
      to: '/docs'
    },
    {
      id: 'operations',
      label: 'Operações',
      icon: 'i-lucide-settings',
      type: 'trigger',
      defaultOpen: operationsOpen,
      children: [
        {
          id: 'health',
          label: 'Saúde',
          icon: 'i-lucide-heart-pulse',
          to: '/health'
        },
        {
          id: 'exports',
          label: 'Exportações',
          icon: 'i-lucide-package',
          to: '/exports'
        },
        {
          id: 'syncs',
          label: 'Sincronizações',
          icon: 'i-lucide-refresh-cw',
          to: '/syncs'
        }
      ]
    }
  ]

  if (hasConfirmedAdminAccess(user)) {
    items.push({
      id: 'admin',
      label: 'Administração',
      icon: 'i-lucide-shield',
      to: '/admin'
    })
  }

  return items
}

/** Links inferiores (mt-auto), como Feedback / Help do template. */
export function secondaryDestinations(): NavDestination[] {
  return [{
    id: 'docs-adn',
    label: 'Documentação ADN',
    icon: 'i-lucide-book-open',
    to: 'https://www.gov.br/nfse',
    target: '_blank',
    external: true
  }, {
    id: 'help',
    label: 'Ajuda e suporte',
    icon: 'i-lucide-info',
    to: 'https://www.gov.br/nfse',
    target: '_blank',
    external: true
  }]
}

/** Ações rápidas da command palette — somente as autorizadas. */
export function quickActions(user?: MeUser | null): QuickAction[] {
  const actions: QuickAction[] = []

  if (canManageClients(user)) {
    actions.push({
      id: 'new-client',
      label: 'Novo cliente',
      icon: 'i-lucide-user-plus',
      to: '/clients?new=1'
    })
  }

  if (canCreateExport(user)) {
    actions.push({
      id: 'new-export',
      label: 'Nova exportação',
      icon: 'i-lucide-download',
      to: '/exports?new=1'
    })
  }

  return actions
}

/** Achata árvore de destinos (command palette / testes). */
export function flattenDestinations(destinations: NavDestination[]): NavDestination[] {
  const out: NavDestination[] = []
  for (const item of destinations) {
    if (item.children?.length) {
      out.push(...flattenDestinations(item.children))
    } else {
      out.push(item)
    }
  }
  return out
}

export function toNavigationItems(
  destinations: NavDestination[],
  onSelect?: () => void
): NavigationMenuItem[] {
  return destinations.map((item) => {
    const base: NavigationMenuItem = {
      label: item.label,
      icon: item.icon,
      to: item.to,
      target: item.target,
      exact: item.exact,
      onSelect: item.to && !item.external
        ? onSelect
        : item.external
          ? onSelect
          : undefined
    }

    if (item.type === 'trigger' && item.children?.length) {
      return {
        ...base,
        type: 'trigger' as const,
        defaultOpen: item.defaultOpen,
        // grupo pai não navega
        to: undefined,
        children: toNavigationItems(item.children, onSelect)
      }
    }

    return base
  })
}

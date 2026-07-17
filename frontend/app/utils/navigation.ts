import type { NavigationMenuItem } from '@nuxt/ui'
import type { MeUser } from '~/types/api'
import { accountNavigationItems } from '~/utils/account-navigation'
import { lacksOfficeContext } from '~/utils/auth-redirect'
import { MONITORING_NAV_ITEMS } from '~/utils/monitoring-nav'
import {
  canAccessPlatformAdmin,
  canCreateExport,
  canManageClients,
  canManageWorkCatalog,
  canViewWork
} from '~/utils/permissions'

export interface NavDestination {
  id: string
  label: string
  icon: string
  to?: string
  target?: string
  external?: boolean
  exact?: boolean
  /** Força estado ativo em destinos com detalhe dinâmico. */
  active?: boolean
  /** Grupo colapsável no estilo Settings do template (UNavigationMenu type=trigger). */
  type?: 'trigger'
  defaultOpen?: boolean
  children?: NavDestination[]
}

export interface QuickAction {
  id: string
  label: string
  icon: string
  to?: string
}

/**
 * Destinos principais do painel, no mesmo “ritmo” do template:
 * Home / Customers / Inbox + grupo colapsável (Settings) + secundários em outro menu.
 */
/** Destinos do grupo Monitoramento fiscal (15.4). */
export function monitoringDestinations(path = ''): NavDestination[] {
  const monitoringOpen = !path || path === '/monitoring' || path.startsWith('/monitoring/')
  return [{
    id: 'monitoring',
    label: 'Monitoramento',
    icon: 'i-lucide-radar',
    type: 'trigger',
    defaultOpen: monitoringOpen,
    children: MONITORING_NAV_ITEMS.map(item => ({
      id: item.id,
      label: item.sidebarLabel,
      icon: item.icon,
      to: item.to,
      exact: item.exact
    }))
  }]
}

function platformAdminDestinations(path = ''): NavDestination[] {
  return [{
    id: 'platform-admin',
    label: 'Admin',
    icon: 'i-lucide-shield',
    type: 'trigger',
    defaultOpen: path === '/admin' || path.startsWith('/admin/'),
    children: [
      {
        id: 'platform-offices',
        label: 'Escritórios',
        icon: 'i-lucide-building-2',
        to: '/admin/offices'
      },
      {
        id: 'platform-serpro-console',
        label: 'SERPRO',
        icon: 'i-lucide-gauge',
        to: '/admin/serpro'
      }
    ]
  }]
}

export function mainDestinations(
  user?: MeUser | null,
  options?: { path?: string }
): NavDestination[] {
  const path = options?.path || ''

  // PLATFORM_ADMIN sem Office: somente superfícies globais /admin.
  if (lacksOfficeContext(user) && canAccessPlatformAdmin(user)) {
    return platformAdminDestinations(path)
  }

  // Mantém o grupo expandido quando a rota atual está dentro do módulo.
  const clientsOpen = !path || path === '/clients' || path.startsWith('/clients/')
  const docsCatalog = path === '/docs/catalog'
  const docsDetail = path.startsWith('/docs/')
    && !path.startsWith('/docs/imports')
    && !docsCatalog
  const docsOpen = !path || path === '/docs' || docsCatalog || docsDetail
  const operationsOpen = !path
    || path.startsWith('/exports')
    || path.startsWith('/closing')
    || path.startsWith('/syncs')
    || path.startsWith('/health')
    || path.startsWith('/docs/imports')

  const isDocsDocumentView = docsCatalog || docsDetail
  const isDocsClientView = path === '/docs'

  const workOpen = !path || path === '/work' || path.startsWith('/work/')

  const items: NavDestination[] = [
    {
      id: 'home',
      label: 'Início',
      icon: 'i-lucide-house',
      to: '/',
      exact: true
    }
  ]

  if (canViewWork(user)) {
    items.push({
      id: 'work',
      label: 'Trabalho',
      icon: 'i-lucide-list-todo',
      type: 'trigger',
      defaultOpen: workOpen,
      children: [
        {
          id: 'work-queue',
          label: 'Minha fila',
          icon: 'i-lucide-inbox',
          to: '/work',
          exact: true
        },
        {
          id: 'work-processes',
          label: 'Processos',
          icon: 'i-lucide-folder-kanban',
          to: '/work/processes'
        },
        {
          id: 'work-calendar',
          label: 'Calendário',
          icon: 'i-lucide-calendar-days',
          to: '/work/calendar'
        },
        ...(canManageWorkCatalog(user)
          ? [{
            id: 'work-templates',
            label: 'Modelos',
            icon: 'i-lucide-layout-template',
            to: '/work/templates'
          } satisfies NavDestination]
          : [])
      ]
    })
  }

  items.push(
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
    ...monitoringDestinations(path),
    {
      id: 'docs',
      label: 'Documentos',
      icon: 'i-lucide-file-stack',
      type: 'trigger',
      defaultOpen: docsOpen,
      children: [
        {
          id: 'docs-by-client',
          label: 'Por cliente',
          icon: 'i-lucide-building-2',
          to: '/docs',
          exact: true,
          active: isDocsClientView
        },
        {
          id: 'docs-catalog',
          label: 'Catálogo',
          icon: 'i-lucide-file-stack',
          to: '/docs/catalog',
          active: isDocsDocumentView
        }
      ]
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
          id: 'closing',
          label: 'Fechamento',
          icon: 'i-lucide-calendar-clock',
          to: '/closing'
        },
        {
          id: 'syncs',
          label: 'Sincronizações',
          icon: 'i-lucide-refresh-cw',
          to: '/syncs'
        },
        {
          id: 'imports',
          label: 'Importações',
          icon: 'i-lucide-upload',
          to: '/docs/imports'
        }
      ]
    }
  )

  // Conta pessoal + configuração compartilhada do escritório.
  const accountItems = accountNavigationItems(user)
  if (accountItems.length) {
    items.push({
      id: 'settings',
      label: 'Configurações',
      icon: 'i-lucide-sliders-horizontal',
      type: 'trigger',
      defaultOpen: path === '/conta' || path.startsWith('/conta/'),
      children: accountItems
    })
  }

  // `/admin/*` reservado à plataforma — grupo Admin só para PLATFORM_ADMIN.
  if (canAccessPlatformAdmin(user)) {
    items.push(...platformAdminDestinations(path))
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
  if (lacksOfficeContext(user)) {
    return []
  }

  const actions: QuickAction[] = []

  if (canManageClients(user)) {
    actions.push({
      id: 'new-client',
      label: 'Novo cliente',
      icon: 'i-lucide-user-plus'
    })
  }

  if (canCreateExport(user)) {
    actions.push({
      id: 'new-export',
      label: 'Nova exportação',
      icon: 'i-lucide-download'
    })
  }

  if (canViewWork(user)) {
    actions.push({
      id: 'work-queue',
      label: 'Minha fila',
      icon: 'i-lucide-inbox',
      to: '/work'
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

const SIDEBAR_MANAGEMENT_IDS = new Set(['clients', 'settings', 'platform-admin'])

/**
 * Separa navegação operacional e gestão usando os grupos nativos do
 * UNavigationMenu. Grupos vazios são removidos para não gerar divisores órfãos.
 */
export function sidebarDestinationGroups(
  destinations: NavDestination[]
): NavDestination[][] {
  const operational = destinations.filter(item => !SIDEBAR_MANAGEMENT_IDS.has(item.id))
  const management = destinations.filter(item => SIDEBAR_MANAGEMENT_IDS.has(item.id))
  return [operational, management].filter(group => group.length > 0)
}

export function toNavigationItems(
  destinations: NavDestination[],
  onSelect?: () => void,
  nested = false
): NavigationMenuItem[] {
  return destinations.map((item) => {
    const base: NavigationMenuItem = {
      label: item.label,
      // Como no shell oficial: ícones identificam destinos/grupos principais;
      // submenus usam somente o rótulo para manter a hierarquia limpa.
      ...(nested ? {} : { icon: item.icon }),
      to: item.to,
      target: item.target,
      exact: item.exact,
      ...(item.active !== undefined ? { active: item.active } : {}),
      onSelect: item.to && !item.external
        ? onSelect
        : item.external
          ? onSelect
          : undefined
    }

    if (item.type === 'trigger' && item.children?.length) {
      return {
        ...base,
        // value estável p/ Accordion (defaultOpen + type multiple no shell)
        value: item.id,
        type: 'trigger' as const,
        defaultOpen: item.defaultOpen,
        // grupo pai não navega
        to: undefined,
        children: toNavigationItems(item.children, onSelect, true)
      }
    }

    return base
  })
}

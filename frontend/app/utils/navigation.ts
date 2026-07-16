import type { NavigationMenuItem } from '@nuxt/ui'
import type { MeUser } from '~/types/api'
import {
  canAccessPlatformSerproConsole,
  canCreateExport,
  canManageClients,
  canManageWorkCatalog,
  canViewWork,
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
    children: [
      {
        id: 'monitoring-dashboard',
        label: 'Dashboard Fiscal',
        icon: 'i-lucide-gauge',
        to: '/monitoring',
        exact: true
      },
      {
        id: 'monitoring-simples-mei',
        label: 'Simples / MEI',
        icon: 'i-lucide-badge-percent',
        to: '/monitoring/simples-mei'
      },
      {
        id: 'monitoring-dctfweb',
        label: 'DCTFWeb / MIT',
        icon: 'i-lucide-file-input',
        to: '/monitoring/dctfweb'
      },
      {
        id: 'monitoring-installments',
        label: 'Parcelamentos',
        icon: 'i-lucide-calendar-range',
        to: '/monitoring/installments'
      },
      {
        id: 'monitoring-sitfis',
        label: 'Situação Fiscal',
        icon: 'i-lucide-clipboard-check',
        to: '/monitoring/sitfis'
      },
      {
        id: 'monitoring-mailbox',
        label: 'Caixas Postais',
        icon: 'i-lucide-mail',
        to: '/monitoring/mailbox'
      },
      {
        id: 'monitoring-declarations',
        label: 'Declarações',
        icon: 'i-lucide-file-check-2',
        to: '/monitoring/declarations'
      },
      {
        id: 'monitoring-guides',
        label: 'Guias',
        icon: 'i-lucide-receipt',
        to: '/monitoring/guides'
      },
      {
        id: 'monitoring-fgts',
        label: 'FGTS (parcial)',
        icon: 'i-lucide-landmark',
        to: '/monitoring/fgts'
      },
      {
        id: 'monitoring-registrations',
        label: 'Cadastro / Vínculos',
        icon: 'i-lucide-link-2',
        to: '/monitoring/registrations'
      },
      {
        id: 'monitoring-tax-processes',
        label: 'Processos fiscais',
        icon: 'i-lucide-scale',
        to: '/monitoring/tax-processes'
      }
    ]
  }]
}

export function mainDestinations(
  user?: MeUser | null,
  options?: { path?: string }
): NavDestination[] {
  const path = options?.path || ''
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

  if (hasConfirmedAdminAccess(user)) {
    items.push({
      id: 'settings',
      label: 'Configurações',
      icon: 'i-lucide-sliders-horizontal',
      type: 'trigger',
      defaultOpen: path.startsWith('/settings') || path.startsWith('/admin'),
      children: [
        {
          id: 'settings-onboarding',
          label: 'Integra Contador',
          icon: 'i-lucide-key-round',
          to: '/settings'
        },
        {
          id: 'settings-usage',
          label: 'Consumo',
          icon: 'i-lucide-chart-pie',
          to: '/settings/usage'
        },
        {
          id: 'admin',
          label: 'Administração',
          icon: 'i-lucide-shield',
          to: '/admin'
        },
        {
          id: 'admin-departments',
          label: 'Departamentos',
          icon: 'i-lucide-building',
          to: '/admin/departments'
        }
      ]
    })
  }

  if (canAccessPlatformSerproConsole(user)) {
    items.push({
      id: 'platform-serpro',
      label: 'SERPRO plataforma',
      icon: 'i-lucide-server-cog',
      type: 'trigger',
      defaultOpen: path.startsWith('/admin/serpro'),
      children: [
        {
          id: 'platform-serpro-console',
          label: 'Console global',
          icon: 'i-lucide-gauge',
          to: '/admin/serpro'
        },
        {
          id: 'platform-serpro-contracts',
          label: 'Contratos',
          icon: 'i-lucide-file-badge',
          to: '/admin/serpro/contracts'
        },
        {
          id: 'platform-serpro-catalog',
          label: 'Cobertura',
          icon: 'i-lucide-layout-grid',
          to: '/admin/serpro/catalog'
        },
        {
          id: 'platform-serpro-usage',
          label: 'Orçamento / conciliação',
          icon: 'i-lucide-wallet',
          to: '/admin/serpro/usage'
        },
        {
          id: 'platform-serpro-rollout',
          label: 'Rollout',
          icon: 'i-lucide-rocket',
          to: '/admin/serpro/rollout'
        }
      ]
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
        // value estável para Accordion (type=single no sidebar)
        value: item.id,
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

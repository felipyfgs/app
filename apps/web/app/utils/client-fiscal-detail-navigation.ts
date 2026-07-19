/**
 * Taxonomia do detalhe fiscal do cliente (`/monitoring/clients/:id/:section?`).
 */
import type { NavLayerItem } from '~/utils/navigation-hierarchy'
import { validateNavCatalog } from '~/utils/navigation-hierarchy'

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

export function clientFiscalDetailNav(clientId: string | number): NavLayerItem[] {
  const id = clientId
  const items: NavLayerItem[] = [
    {
      id: 'cf-overview',
      label: 'Visão geral',
      icon: 'i-lucide-layout-dashboard',
      children: [{
        id: 'cf-overview-leaf',
        label: 'Resumo',
        icon: 'i-lucide-layout-dashboard',
        to: sectionPath(id, 'overview'),
        exact: true,
        isActive: sectionActive('overview', id)
      }]
    },
    {
      id: 'cf-activity',
      label: 'Atividade',
      icon: 'i-lucide-activity',
      children: [
        {
          id: 'cf-runs',
          label: 'Execuções',
          icon: 'i-lucide-play',
          to: sectionPath(id, 'runs'),
          isActive: sectionActive('runs', id)
        },
        {
          id: 'cf-findings',
          label: 'Achados',
          icon: 'i-lucide-search',
          to: sectionPath(id, 'findings'),
          isActive: sectionActive('findings', id)
        },
        {
          id: 'cf-pending',
          label: 'Pendências',
          icon: 'i-lucide-circle-alert',
          to: sectionPath(id, 'pending'),
          isActive: sectionActive('pending', id)
        }
      ]
    },
    {
      id: 'cf-obligations',
      label: 'Obrigações',
      icon: 'i-lucide-file-check-2',
      children: [
        {
          id: 'cf-declarations',
          label: 'Declarações',
          icon: 'i-lucide-file-check-2',
          to: sectionPath(id, 'declarations'),
          isActive: sectionActive('declarations', id)
        },
        {
          id: 'cf-pgdasd',
          label: 'PGDAS-D',
          icon: 'i-lucide-badge-percent',
          to: sectionPath(id, 'pgdasd'),
          isActive: sectionActive('pgdasd', id)
        },
        {
          id: 'cf-fgts',
          label: 'FGTS',
          icon: 'i-lucide-landmark',
          to: sectionPath(id, 'fgts'),
          isActive: sectionActive('fgts', id)
        }
      ]
    },
    {
      id: 'cf-finance',
      label: 'Financeiro',
      icon: 'i-lucide-wallet',
      children: [
        {
          id: 'cf-installments',
          label: 'Parcelamentos',
          icon: 'i-lucide-calendar-range',
          to: sectionPath(id, 'installments'),
          isActive: sectionActive('installments', id)
        },
        {
          id: 'cf-guides',
          label: 'Guias',
          icon: 'i-lucide-receipt',
          to: sectionPath(id, 'guides'),
          isActive: sectionActive('guides', id)
        }
      ]
    },
    {
      id: 'cf-regularity',
      label: 'Regularidade',
      icon: 'i-lucide-clipboard-check',
      children: [
        {
          id: 'cf-sitfis',
          label: 'SITFIS',
          icon: 'i-lucide-clipboard-check',
          to: sectionPath(id, 'sitfis'),
          isActive: sectionActive('sitfis', id)
        },
        {
          id: 'cf-registrations',
          label: 'Cadastro e Vínculos',
          icon: 'i-lucide-link-2',
          to: sectionPath(id, 'registrations'),
          isActive: sectionActive('registrations', id)
        },
        {
          id: 'cf-ccmei',
          label: 'CCMEI',
          icon: 'i-lucide-badge-check',
          to: sectionPath(id, 'ccmei'),
          isActive: sectionActive('ccmei', id)
        },
        {
          id: 'cf-renunciations',
          label: 'Renúncias',
          icon: 'i-lucide-unlink',
          to: sectionPath(id, 'renunciations'),
          isActive: sectionActive('renunciations', id)
        },
        {
          id: 'cf-tax-processes',
          label: 'Processos Fiscais',
          icon: 'i-lucide-scale',
          to: sectionPath(id, 'tax_processes'),
          isActive: sectionActive('tax_processes', id)
        }
      ]
    }
  ]
  validateNavCatalog(items)
  return items
}

export function sectionKeyFromFiscalPath(path: string): ClientFiscalSectionKey {
  const bare = path.replace(/\/$/, '')
  const match = bare.match(/\/monitoring\/clients\/[^/]+(?:\/([^/]+))?$/)
  const raw = match?.[1] || 'overview'
  return (CLIENT_FISCAL_SECTION_KEYS as string[]).includes(raw)
    ? raw as ClientFiscalSectionKey
    : 'overview'
}

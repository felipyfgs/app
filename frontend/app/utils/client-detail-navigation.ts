/**
 * Taxonomia do detalhe cadastral do cliente e seções do modal.
 */
import type { NavLayerItem, NavLeafDestination } from '~/utils/navigation-hierarchy'
import { flattenNavLeaves, validateNavCatalog } from '~/utils/navigation-hierarchy'

/** Seções oferecidas pelo modal de cliente (subconjunto). */
export type ClientModalSection
  = 'resumo' | 'cadastro' | 'estabelecimentos' | 'certificado' | 'sincronizacao'

export function clientDetailNav(clientId: string | number): NavLayerItem[] {
  const base = `/clients/${clientId}`
  const items: NavLayerItem[] = [
    {
      id: 'client-overview',
      label: 'Visão geral',
      icon: 'i-lucide-layout-dashboard',
      children: [{
        id: 'client-resumo',
        label: 'Resumo',
        icon: 'i-lucide-layout-dashboard',
        to: base,
        exact: true,
        isActive: path => path === base
      }]
    },
    {
      id: 'client-data',
      label: 'Dados',
      icon: 'i-lucide-clipboard-list',
      children: [
        {
          id: 'client-cadastro',
          label: 'Cadastro',
          icon: 'i-lucide-clipboard-list',
          to: `${base}/cadastro`,
          isActive: path => path.endsWith('/cadastro')
        },
        {
          id: 'client-estabelecimentos',
          label: 'Estabelecimentos',
          icon: 'i-lucide-map-pin-house',
          to: `${base}/estabelecimentos`,
          isActive: path => path.endsWith('/estabelecimentos')
        }
      ]
    },
    {
      id: 'client-fiscal',
      label: 'Fiscal',
      icon: 'i-lucide-landmark',
      children: [
        {
          id: 'client-ccmei',
          label: 'CCMEI',
          icon: 'i-lucide-badge-check',
          to: `${base}/ccmei`,
          isActive: path => path.endsWith('/ccmei')
        },
        {
          id: 'client-sicalc',
          label: 'Receitas SICALC',
          icon: 'i-lucide-receipt-text',
          to: `${base}/sicalc`,
          isActive: path => path.endsWith('/sicalc')
        },
        {
          id: 'client-pagamentos',
          label: 'Pagamentos',
          icon: 'i-lucide-chart-no-axes-column',
          to: `${base}/pagamentos`,
          isActive: path => path.endsWith('/pagamentos')
        },
        {
          id: 'client-renuncias',
          label: 'Renúncias',
          icon: 'i-lucide-unlink',
          to: `${base}/renuncias`,
          isActive: path => path.endsWith('/renuncias')
        }
      ]
    },
    {
      id: 'client-integrations',
      label: 'Integrações',
      icon: 'i-lucide-plug',
      children: [
        {
          id: 'client-certificado',
          label: 'Certificado A1',
          icon: 'i-lucide-badge-check',
          to: `${base}/certificado`,
          isActive: path => path.endsWith('/certificado')
        },
        {
          id: 'client-sincronizacao',
          label: 'Sincronização',
          icon: 'i-lucide-refresh-cw',
          to: `${base}/sincronizacao`,
          isActive: path => path.endsWith('/sincronizacao')
        },
        {
          id: 'client-saidas',
          label: 'Captura de saídas',
          icon: 'i-lucide-arrow-up-from-line',
          to: `${base}/saidas`,
          isActive: path => path.endsWith('/saidas')
        }
      ]
    }
  ]
  validateNavCatalog(items)
  return items
}

const MODAL_SECTION_IDS: Record<ClientModalSection, string> = {
  resumo: 'client-resumo',
  cadastro: 'client-cadastro',
  estabelecimentos: 'client-estabelecimentos',
  certificado: 'client-certificado',
  sincronizacao: 'client-sincronizacao'
}

/** Mesmos grupos/rótulos do detalhe, só para seções que o modal oferece. */
export function clientModalNav(): NavLayerItem[] {
  const full = clientDetailNav(0)
  const allowed = new Set(Object.values(MODAL_SECTION_IDS))
  return full
    .map((item) => {
      if (!('children' in item)) return item
      const children = item.children.filter(child => allowed.has(child.id))
      if (!children.length) return null
      return { ...item, children }
    })
    .filter((item): item is NavLayerItem => item !== null)
}

export function clientDetailLeaves(clientId: string | number): NavLeafDestination[] {
  return flattenNavLeaves(clientDetailNav(clientId))
}

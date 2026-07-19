import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'
import type { MeUser } from '~/types/api'
import { clientDetailNav } from '~/utils/client-detail-navigation'
import { clientFiscalDetailNav } from '~/utils/client-fiscal-detail-navigation'
import { fiscalNavLeaves } from '~/utils/fiscal-navigation'
import { LINK_TABS_UI, SCROLLABLE_TABS_UI } from '~/utils/list-filter-layout'
import { MONITORING_NAV_ITEMS } from '~/utils/monitoring-nav'
import {
  mainDestinations,
  monitoringDestinations,
  searchableDestinations,
  toNavigationItems
} from '~/utils/navigation'
import { resolveNavSelection } from '~/utils/navigation-hierarchy'
import { workProcessContextNav } from '~/utils/work-navigation'

const viewer = {
  id: 10,
  role: 'VIEWER',
  context_status: 'ready'
} as MeUser

describe('navegação global no sidebar', () => {
  it('organiza os 11 contextos sob um único acordeão Monitoramento', () => {
    const destinations = monitoringDestinations(viewer, '/monitoring/declarations')
    const monitoring = destinations[0]

    expect(destinations).toHaveLength(1)
    expect(monitoring).toMatchObject({
      id: 'monitoring',
      label: 'Monitoramento',
      type: 'trigger',
      defaultOpen: true
    })
    expect(monitoring).not.toHaveProperty('active')
    expect(monitoring?.children?.map(item => item.label)).toEqual([
      'Dashboard',
      'Simples Nacional | MEI',
      'DCTFWeb',
      'FGTS Digital',
      'Parcelamentos',
      'Situação Fiscal',
      'Caixas Postais',
      'Declarações',
      'Guias',
      'Cadastro e Vínculos',
      'Processos Fiscais'
    ])
    expect(monitoring?.children?.find(item => item.id === 'monitoring-declarations'))
      .toMatchObject({ active: true, to: '/monitoring/declarations' })
    expect(monitoring?.children?.every(item => !item.children?.length)).toBe(true)
  })

  it('converte Monitoramento e suas folhas para o contrato nativo do UNavigationMenu', () => {
    const items = toNavigationItems(
      monitoringDestinations(viewer, '/monitoring/simples-mei')
    )

    expect(items[0]).toMatchObject({
      label: 'Monitoramento',
      type: 'trigger',
      defaultOpen: true
    })
    expect(items[0]).not.toHaveProperty('active')
    expect(items[0]?.children?.filter(item => item.active)).toHaveLength(1)
    expect(items[0]?.children)
      .toEqual(expect.arrayContaining([
        expect.objectContaining({ label: 'Simples Nacional | MEI', to: '/monitoring/simples-mei' }),
        expect.objectContaining({ label: 'Declarações', to: '/monitoring/declarations' })
      ]))
  })

  it('preserva permissões e indexa todas as folhas fiscais na busca global', () => {
    expect(monitoringDestinations({ id: 11 } as MeUser, '/monitoring')).toEqual([])

    const searchable = searchableDestinations(viewer, { path: '/monitoring' })
    const searchablePaths = new Set(searchable.map(item => item.to))
    for (const leaf of fiscalNavLeaves()) {
      expect(searchablePaths.has(leaf.to)).toBe(true)
    }
  })

  it('mantém o catálogo e a ordem da navegação como fonte única', () => {
    expect(fiscalNavLeaves().map(item => item.label))
      .toEqual(MONITORING_NAV_ITEMS.map(item => item.label))
  })

  it('alinha os nomes do sidebar aos títulos das 11 superfícies', () => {
    const pageContracts: Array<[string, string]> = [
      ['app/pages/monitoring/index.vue', 'title="Dashboard"'],
      ['app/pages/monitoring/simples-mei/index.vue', 'title="Simples Nacional | MEI"'],
      ['app/pages/monitoring/dctfweb/index.vue', 'title="DCTFWeb"'],
      ['app/pages/monitoring/fgts.vue', 'title="FGTS Digital"'],
      ['app/pages/monitoring/installments.vue', 'title="Parcelamentos"'],
      ['app/pages/monitoring/sitfis.vue', 'title="Situação Fiscal"'],
      ['app/pages/monitoring/mailbox.vue', 'title="Caixas Postais"'],
      ['app/pages/monitoring/declarations.vue', 'title="Declarações"'],
      ['app/pages/monitoring/guides.vue', 'title="Guias"'],
      ['app/pages/monitoring/registrations.vue', 'title="Cadastro e Vínculos"'],
      ['app/pages/monitoring/tax-processes.vue', 'title="Processos Fiscais"']
    ]

    expect(pageContracts.map(([, title]) => title.replace('title="', '').replace('"', '')))
      .toEqual(MONITORING_NAV_ITEMS.map(item => item.label))
    for (const [path, title] of pageContracts) {
      expect(readFileSync(resolve(process.cwd(), path), 'utf8')).toContain(title)
    }
  })

  it('expõe as três superfícies SERPRO diretamente dentro de Admin', () => {
    const user = {
      id: 1,
      is_platform_admin: true,
      context_status: 'office_context_required'
    } as MeUser
    const admin = mainDestinations(user, { path: '/admin/serpro/contracts' })[0]

    expect(admin).toMatchObject({ id: 'platform-admin', type: 'trigger', defaultOpen: true })
    expect(admin?.children?.map(item => item.label)).toEqual([
      'Escritórios',
      'Módulos fiscais',
      'SERPRO · Operação',
      'SERPRO · Integração',
      'SERPRO · Canário DTE'
    ])
    expect(admin?.children?.find(item => item.label === 'SERPRO · Integração'))
      .toMatchObject({ active: true, to: '/admin/serpro/configuration' })
  })
})

describe('navegação contextual Tabs → Subtabs', () => {
  it('detalhe do cliente: 4 destinos CRM path-based + detalhe fiscal plano (settings)', () => {
    const nav = clientDetailNav(7)
    expect(nav.map(item => item.id)).toEqual([
      'client-cadastro',
      'client-contato',
      'client-departamento',
      'client-configuracao'
    ])
    expect(nav.every(item => !('children' in item))).toBe(true)
    const cadastro = nav.find(item => item.id === 'client-cadastro')
    expect(cadastro && 'to' in cadastro ? cadastro.to : null).toBe('/clients/7/cadastro')

    const fiscalNav = clientFiscalDetailNav(8)
    expect(fiscalNav.every(item => !('children' in item))).toBe(true)
    expect(fiscalNav.map(item => item.id)).toContain('cf-pgdasd')
    const fiscalClient = resolveNavSelection(
      fiscalNav,
      '/monitoring/clients/8/tax_processes'
    )
    expect(fiscalClient.group).toBeNull()
    expect(fiscalClient.leaf?.id).toBe('cf-tax-processes')
  })

  it('preserva seções identificadas por query no detalhe de processo', () => {
    const process = resolveNavSelection(
      workProcessContextNav(42),
      '/work/processes/42?section=comentarios'
    )
    expect(process.group).toBeNull()
    expect(process.leaf?.id).toBe('process-comentarios')
  })

  it('renderiza tabs, subtabs opcionais e um seletor mobile — sem dropdown', () => {
    const source = readFileSync(
      resolve(process.cwd(), 'app/components/navigation/SectionNavigation.vue'),
      'utf8'
    )

    expect(source.match(/<UNavigationMenu/g)).toHaveLength(2)
    expect(source.match(/<USelectMenu/g)).toHaveLength(1)
    expect(source).toContain('section-nav-subtabs')
    expect(source).toContain('toDesktopTabItems')
    expect(source).toContain('toDesktopSubtabItems')
    expect(source).not.toContain('children: group.children.map')
    expect(source).not.toContain('content-orientation="vertical"')
  })
})

describe('tabs locais', () => {
  it('mantém as tabs retraídas e delega a aparência ao tema padrão', () => {
    expect(LINK_TABS_UI.root).not.toContain('min-w-full')
    expect(LINK_TABS_UI.trigger).toContain('grow-0')
    expect(SCROLLABLE_TABS_UI.root).not.toContain('min-w-full')
    expect(SCROLLABLE_TABS_UI.trigger).toContain('grow-0')
    expect(SCROLLABLE_TABS_UI).not.toHaveProperty('indicator')
    expect(SCROLLABLE_TABS_UI.list).not.toMatch(/bg-|border|rounded|shadow/)
    expect(SCROLLABLE_TABS_UI.trigger).not.toMatch(/text-|bg-|rounded|shadow/)
  })

  it('aplica cápsulas compactas às quatro alternâncias locais', () => {
    const sources = [
      'app/pages/monitoring/simples-mei/index.vue',
      'app/pages/monitoring/dctfweb/index.vue',
      'app/pages/admin/serpro/index.vue',
      'app/pages/admin/serpro/configuration.vue'
    ].map(path => readFileSync(resolve(process.cwd(), path), 'utf8'))

    for (const source of sources) {
      expect(source).toContain('variant="pill"')
    }

    const wrapper = readFileSync(
      resolve(process.cwd(), 'app/components/shell/ScrollableTabs.vue'),
      'utf8'
    )
    expect(wrapper).toContain('props.variant === \'link\' ? LINK_TABS_UI : SCROLLABLE_TABS_UI')
    expect(wrapper).toContain('activation-mode="automatic"')
  })

  it('mantém filtros e indicadores no preset pill padrão', () => {
    const filterSources = [
      'app/components/monitoring/KpiStrip.vue',
      'app/components/work/WorkQueueWorkspace.vue',
      'app/pages/work/calendar.vue',
      'app/pages/monitoring/installments.vue'
    ].map(path => readFileSync(resolve(process.cwd(), path), 'utf8'))

    for (const source of filterSources) {
      expect(source).not.toContain('variant="link"')
    }
  })

  it('simplifica o chrome da carteira Simples Nacional sem afetar outras carteiras', () => {
    const source = readFileSync(
      resolve(process.cwd(), 'app/pages/monitoring/simples-mei/index.vue'),
      'utf8'
    )
    const moduleTable = readFileSync(
      resolve(process.cwd(), 'app/components/monitoring/ModuleTable.vue'),
      'utf8'
    )

    expect(source).toContain(':show-pending-search="false"')
    expect(source).toContain(':show-synthetic-alert="false"')
    expect(source).not.toContain('>\n          Regime\n        </p>')
    expect(source).not.toContain('class="w-full min-w-0"')
    expect(moduleTable).toContain('showPendingSearch: true')
    expect(moduleTable).toContain('showSyntheticAlert: true')
  })
})

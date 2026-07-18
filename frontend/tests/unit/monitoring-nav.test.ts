import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'
import {
  MONITORING_NAV_ITEMS,
  monitoringNavActiveModule,
  monitoringNavMenuItems,
  monitoringPathForModule
} from '../../app/utils/monitoring-nav'

describe('MonitoringModuleNav items (6.3)', () => {
  it('delega a navegação responsiva ao SectionNavigation com path da URL', () => {
    const component = readFileSync(
      resolve(__dirname, '../../app/components/monitoring/MonitoringModuleNav.vue'),
      'utf8'
    )

    expect(component).toContain('SectionNavigation')
    expect(component).toContain('fiscalNavigationItems')
    expect(component).toContain('test-id="monitoring-module-nav"')
    expect(component).toContain(':path="route.fullPath"')
    expect(component).toContain('monitoring-module-nav-skeleton')
    expect(component).not.toContain('monitoringPathForModule')
    expect(component).not.toContain('resolvedPath')
    const sectionNav = readFileSync(
      resolve(__dirname, '../../app/components/navigation/SectionNavigation.vue'),
      'utf8'
    )
    expect(sectionNav).toContain('min-h-11')
    expect(sectionNav).toContain('lg:hidden')
    expect(sectionNav).toContain('hidden min-w-0 lg:block')
  })

  it('ModuleTable hospeda nav no slot default multi-linha (sem #left de uma faixa)', () => {
    const moduleTable = readFileSync(
      resolve(__dirname, '../../app/components/monitoring/ModuleTable.vue'),
      'utf8'
    )
    expect(moduleTable).toContain('data-testid="monitoring-nav-toolbar"')
    expect(moduleTable).toContain('overflow-visible')
    expect(moduleTable).toContain('items-stretch')
    expect(moduleTable).toContain('<MonitoringModuleNav />')
    // Não prende SectionNavigation em template #left
    const toolbarBlock = moduleTable.slice(
      moduleTable.indexOf('monitoring-nav-toolbar') - 200,
      moduleTable.indexOf('monitoring-nav-toolbar') + 400
    )
    expect(toolbarBlock).not.toMatch(/#left[\s\S]*MonitoringModuleNav/)
  })

  it('controles segmentados locais não competem visualmente com a nav fiscal', () => {
    const simples = readFileSync(
      resolve(__dirname, '../../app/pages/monitoring/simples-mei/index.vue'),
      'utf8'
    )
    const dctf = readFileSync(
      resolve(__dirname, '../../app/pages/monitoring/dctfweb/index.vue'),
      'utf8'
    )
    for (const src of [simples, dctf]) {
      expect(src).toContain('variant="pill"')
      expect(src).toContain('color="primary"')
      expect(src).not.toContain('>Cápsula<')
      expect(src).not.toContain('border-t border-default/60')
    }
    expect(simples).toContain('badge: t.badge')
    expect(simples).toContain('Regime: Simples Nacional ou MEI')
    expect(dctf).toContain('Declaração: DCTFWeb ou MIT')
    expect(simples).toContain('simples-mei-capsule-control')
    expect(dctf).toContain('dctfweb-capsule-control')
  })

  it('expõe os eixos estruturados de Simples/MEI e DCTFWeb', () => {
    const simples = readFileSync(
      resolve(__dirname, '../../app/pages/monitoring/simples-mei/index.vue'),
      'utf8'
    )
    const dctf = readFileSync(
      resolve(__dirname, '../../app/pages/monitoring/dctfweb/index.vue'),
      'utf8'
    )

    expect(simples).toContain('key: \'clientId\'')
    expect(simples).toContain('key: \'competence\'')
    expect(dctf).toContain('key: \'situation\'')
    expect(dctf).toContain('key: \'clientId\'')
    expect(dctf).toContain('key: \'competence\'')
  })

  it('lista todos os destinos do hub fiscal', () => {
    const ids = MONITORING_NAV_ITEMS.map(i => i.id)
    expect(ids).toEqual([
      'monitoring-dashboard',
      'monitoring-simples-mei',
      'monitoring-dctfweb',
      'monitoring-fgts',
      'monitoring-installments',
      'monitoring-sitfis',
      'monitoring-mailbox',
      'monitoring-declarations',
      'monitoring-guides',
      'monitoring-registrations',
      'monitoring-tax-processes'
    ])
  })

  it('resolve módulo ativo por path (incluindo detalhe aninhado)', () => {
    expect(monitoringNavActiveModule('/monitoring')).toBe('dashboard')
    expect(monitoringNavActiveModule('/monitoring/simples-mei')).toBe('simples_mei')
    expect(monitoringNavActiveModule('/monitoring/simples-mei/pgmei')).toBe('simples_mei')
    expect(monitoringNavActiveModule('/monitoring/mailbox/42')).toBe('mailbox')
    expect(monitoringNavActiveModule('/monitoring/clients/9')).toBe('dashboard')
  })

  it('marca item ativo no menu highlight', () => {
    const items = monitoringNavMenuItems('/monitoring/fgts')
    const active = items.filter(i => i.active)
    expect(active).toHaveLength(1)
    expect(active[0]?.to).toBe('/monitoring/fgts')
  })

  it('paths batem com o catálogo de módulos (depth = sidebar)', () => {
    expect(monitoringPathForModule('dctfweb')).toBe('/monitoring/dctfweb')
    expect(monitoringPathForModule('simples_mei')).toBe('/monitoring/simples-mei')
    expect(monitoringPathForModule('dashboard')).toBe('/monitoring')
  })

  it('aceita activeOverride (prop active das páginas)', () => {
    const items = monitoringNavMenuItems('/monitoring', 'mailbox')
    expect(items.find(i => i.active)?.to).toBe('/monitoring/mailbox')
    expect(items.filter(i => i.active)).toHaveLength(1)
  })

  it('mantém todos os destinos visíveis na barra rolável', () => {
    const items = monitoringNavMenuItems('/monitoring')
    expect(items).toHaveLength(MONITORING_NAV_ITEMS.length)
    expect(items.every(item => item.to)).toBe(true)
    expect(items.some(item => item.label === 'Mais')).toBe(false)
    expect(items.every(item => item.icon == null)).toBe(true)
    expect(items.map(item => item.label)).toEqual([
      'Dashboard',
      'Simples / MEI',
      'DCTFWeb / MIT',
      'FGTS',
      'Parcelamentos',
      'SITFIS',
      'Caixas Postais',
      'Declarações',
      'Guias',
      'Cadastro / Vínculos',
      'Processos fiscais'
    ])
  })

  it('cada item tem label e destino /monitoring', () => {
    for (const item of MONITORING_NAV_ITEMS) {
      expect(item.label.length).toBeGreaterThan(0)
      expect(item.to.startsWith('/monitoring')).toBe(true)
      expect(item.icon).toMatch(/^i-lucide-/)
    }
  })

  it('paths públicos batem com moduleKey (sem duplicata de rota)', () => {
    const paths = MONITORING_NAV_ITEMS.map(i => i.to)
    expect(new Set(paths).size).toBe(paths.length)
    const keys = MONITORING_NAV_ITEMS.map(i => i.moduleKey)
    expect(new Set(keys).size).toBe(keys.length)
  })

  it('dashboard é exact; demais aceitam nested path', () => {
    expect(monitoringNavActiveModule('/monitoring/')).toBe('dashboard')
    // path desconhecido sob /monitoring cai no dashboard (fallback)
    expect(monitoringNavActiveModule('/monitoring/extra')).toBe('dashboard')
    // nested de mailbox
    expect(monitoringNavActiveModule('/monitoring/mailbox/99/anexo')).toBe('mailbox')
    // exact: /monitoring/guides-extra não ativa guides
    expect(monitoringNavActiveModule('/monitoring/guides-extra')).toBe('dashboard')
  })
})

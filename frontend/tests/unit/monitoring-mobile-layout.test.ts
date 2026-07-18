import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

/**
 * Contrato mobile das cascas de monitoramento.
 *
 * Desktop: customers.vue (UTable + Display).
 * Mobile (&lt; md): ModuleMobileCards — 1 card/linha, collapsible, ações.
 */
describe('layout mobile — componentes de monitoramento', () => {
  const root = resolve(__dirname, '../../app')

  function read(rel: string) {
    return readFileSync(resolve(root, rel), 'utf8')
  }

  it('ModuleDataTable usa cards no compacto e tabela no desktop', () => {
    const source = read('components/monitoring/ModuleDataTable.vue')
    expect(source).toContain('useMobileCards')
    expect(source).toContain('MonitoringModuleMobileCards')
    expect(source).toContain('data-testid="fiscal-table-scroll"')
    expect(source).toContain('data-testid="fiscal-table"')
    expect(source).toContain('paginationSiblingCount')
    expect(source).toContain('flex-col gap-3')
    expect(source).toContain('sm:flex-row')
    // Sem pin de colunas
    expect(source).not.toContain('column-pinning')
    expect(source).not.toContain('columnPinning')
  })

  it('ModuleMobileCards: card + collapsible + ações', () => {
    const source = read('components/monitoring/ModuleMobileCards.vue')
    expect(source).toContain('data-testid="fiscal-mobile-cards"')
    expect(source).toContain('UCollapsible')
    expect(source).toContain('fiscal-mobile-card-toggle')
    expect(source).toContain('fiscal-mobile-card-actions')
    expect(source).toContain('Mais detalhes')
    expect(source).toContain('Ocultar detalhes')
    expect(source).toContain('HEADER_IDS')
    expect(source).toContain('ACTION_IDS')
  })

  it('ModuleTable repassa mobile-cards e empilha o body', () => {
    const source = read('components/monitoring/ModuleTable.vue')
    expect(source).toContain('data-testid="fiscal-module-body"')
    expect(source).toContain('min-w-0 flex-col gap-3 sm:gap-4')
    expect(source).toContain(':mobile-cards="mobileCards"')
    expect(source).not.toContain('pinLeftColumns')
    expect(source).not.toContain('mobileHiddenColumns')
  })

  it('KpiStrip e ModuleNav usam scroll touch e size compacto no phone', () => {
    const kpi = read('components/monitoring/KpiStrip.vue')
    const nav = read('components/monitoring/MonitoringModuleNav.vue')
    expect(kpi).toContain('tabSize')
    expect(kpi).toContain('isNarrow.value ? \'sm\' : \'md\'')
    expect(kpi).toContain('touch-pan-x')
    expect(nav).toContain('touch-pan-x')
    expect(nav).toContain('text-xs sm:text-sm')
  })

  it('toolbar e filtros escondem labels longos abaixo de sm (aria-label preservado)', () => {
    const toolbar = read('components/monitoring/ModuleToolbar.vue')
    const saved = read('components/data-table-filter/SavedFiltersMenu.vue')
    const filters = read('components/data-table-filter/Root.vue')
    const moduleTable = read('components/monitoring/ModuleTable.vue')
    const pending = read('components/monitoring/PendingSearchButton.vue')

    for (const source of [toolbar, saved, filters, moduleTable, pending]) {
      expect(source).toContain('label: \'hidden sm:inline\'')
    }
    expect(toolbar).toContain('aria-label="Salvar filtros"')
    expect(saved).toContain('aria-label="Filtros salvos"')
    expect(moduleTable).toContain('aria-label="Exibir colunas"')
    expect(pending).toContain('aria-label="Buscar pendências"')
  })

  it('páginas densas habilitam horizontal-scroll + min-width (desktop)', () => {
    const dense = [
      'pages/monitoring/installments.vue',
      'pages/monitoring/guides.vue',
      'pages/monitoring/fgts.vue',
      'pages/monitoring/sitfis.vue',
      'pages/monitoring/declarations.vue',
      'pages/monitoring/dctfweb/index.vue',
      'pages/monitoring/simples-mei/index.vue'
    ]
    for (const rel of dense) {
      const source = read(rel)
      expect(source, rel).toContain(':horizontal-scroll="true"')
      expect(source, rel).toMatch(/table-class="min-w-\[/)
    }
  })
})

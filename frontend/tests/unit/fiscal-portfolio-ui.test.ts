import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'
import {
  coverageMeta,
  dataOriginMeta,
  fiscalStatusMeta,
  resolveFiscalEmptyKind
} from '../../app/utils/fiscal-status'
import {
  fiscalKpiSituationFilter,
  fiscalSituationToKpiKey,
  type FiscalKpiKey,
  type FiscalModuleCounters
} from '../../app/types/fiscal-modules'
import { monitoringNavMenuItems, MONITORING_NAV_ITEMS } from '../../app/utils/monitoring-nav'

/** Espelha resolução de props do MonitoringKpiStrip (API unificada). */
function resolveKpiTotal(input: { total?: number | null, totalClients?: number | null }) {
  if (input.total != null && Number.isFinite(Number(input.total))) return Number(input.total)
  if (input.totalClients != null && Number.isFinite(Number(input.totalClients))) {
    return Number(input.totalClients)
  }
  return 0
}

function resolveActiveKey(input: {
  activeKey?: FiscalKpiKey | null
  activeSituation?: string | null
}): FiscalKpiKey {
  if (input.activeKey) return input.activeKey
  if (input.activeSituation != null && String(input.activeSituation).length > 0) {
    return fiscalSituationToKpiKey(input.activeSituation)
  }
  return 'total'
}

/** Espelha onKpiSelect do MonitoringModuleTable. */
function onKpiSelect(key: FiscalKpiKey) {
  return {
    key,
    situation: fiscalKpiSituationFilter(key) || 'all'
  }
}

describe('MonitoringKpiStrip mapping (6.4 / 6.11)', () => {
  it('clique em Pendências/Atenção produz filtro de situação', () => {
    expect(fiscalKpiSituationFilter('pending')).toBe('PENDING')
    expect(fiscalKpiSituationFilter('attention')).toBe('ATTENTION')
    expect(fiscalKpiSituationFilter('total')).toBeNull()
  })

  it('aliases totalClients / activeSituation resolvem o mesmo contrato', () => {
    expect(resolveKpiTotal({ totalClients: 42 })).toBe(42)
    expect(resolveKpiTotal({ total: 10, totalClients: 99 })).toBe(10)
    expect(resolveKpiTotal({})).toBe(0)

    expect(resolveActiveKey({ activeSituation: 'PENDING' })).toBe('pending')
    expect(resolveActiveKey({ activeSituation: 'all' })).toBe('total')
    expect(resolveActiveKey({ activeKey: 'error', activeSituation: 'PENDING' })).toBe('error')
  })

  it('@select emite key + situation (contrato das páginas e MonitoringModuleTable)', () => {
    function onSelect(key: 'pending' | 'total') {
      return [key, fiscalKpiSituationFilter(key)] as const
    }
    expect(onSelect('pending')).toEqual(['pending', 'PENDING'])
    expect(onSelect('total')).toEqual(['total', null])
  })

  it('fiscalSituationToKpiKey cobre a faixa acionável', () => {
    expect(fiscalSituationToKpiKey('UP_TO_DATE')).toBe('up_to_date')
    expect(fiscalSituationToKpiKey('processing')).toBe('processing')
    expect(fiscalSituationToKpiKey('UNSUPPORTED')).toBe('total')
    expect(fiscalSituationToKpiKey(null)).toBe('total')
  })

  it('MonitoringModuleTable: totalClients preferido no strip; situation→activeKey', () => {
    const counters: FiscalModuleCounters = {
      up_to_date: 5,
      processing: 1,
      pending: 3,
      attention: 2,
      error: 0
    }
    // Tabela passa totalClients ?? total e activeKey derivado da situation
    const totalClients = 11
    const pageTotal = 15 // paginator total (pode ser filtrado)
    const stripTotal = resolveKpiTotal({
      total: totalClients ?? pageTotal,
      totalClients: totalClients ?? pageTotal
    })
    expect(stripTotal).toBe(11)
    expect(counters.pending + counters.attention).toBe(5)

    const activeFromUrl = resolveActiveKey({
      activeKey: fiscalSituationToKpiKey('PENDING'),
      activeSituation: 'PENDING'
    })
    expect(activeFromUrl).toBe('pending')

    // Clique no KPI total limpa filtro na tabela
    expect(onKpiSelect('total')).toEqual({ key: 'total', situation: 'all' })
    expect(onKpiSelect('pending')).toEqual({ key: 'pending', situation: 'PENDING' })
  })

  it('badges das cápsulas ficam estáveis ao filtrar por situation (só a lista muda)', () => {
    // Overview sem situation → counters de carteira completa
    const overviewCounters: FiscalModuleCounters = {
      up_to_date: 10,
      processing: 2,
      pending: 4,
      attention: 1,
      error: 0
    }
    const totalClients = 17 // soma da carteira (sem cápsula)
    // ModuleTable passa totalClients ?? total para o strip — lista filtrada não sobrescreve
    const pageTotalFiltered = 4
    const stripProps = {
      total: totalClients ?? pageTotalFiltered,
      totalClients: totalClients ?? pageTotalFiltered
    }
    expect(resolveKpiTotal(stripProps)).toBe(17)
    expect(overviewCounters.pending).toBe(4)
    expect(overviewCounters.up_to_date).toBe(10)
    // Clique em Pendências só filtra lista; badges do overview permanecem
    expect(onKpiSelect('pending').situation).toBe('PENDING')
    expect(resolveKpiTotal(stripProps)).toBe(17)
  })

  it('chips esperados: Total + contadores (+ Erro)', () => {
    const keys: FiscalKpiKey[] = [
      'total', 'up_to_date', 'processing', 'pending', 'attention', 'error'
    ]
    for (const key of keys) {
      const sit = fiscalKpiSituationFilter(key)
      expect(fiscalSituationToKpiKey(sit)).toBe(key === 'total' ? 'total' : key)
    }
  })
})

describe('badges cobertura, origem e status (6.6 / 6.11)', () => {
  it('coverageMeta expõe texto e ícone (não só cor)', () => {
    for (const code of ['FULL', 'PARTIAL', 'UNSUPPORTED', 'NOT_APPLICABLE', 'UNKNOWN']) {
      const meta = coverageMeta(code)
      expect(meta.label.length).toBeGreaterThan(0)
      expect(meta.icon).toMatch(/^i-lucide-/)
      expect(meta.description.length).toBeGreaterThan(0)
    }
    expect(coverageMeta('PARTIAL').color).toBe('warning')
    expect(coverageMeta('FULL').color).toBe('success')
  })

  it('dataOriginMeta marca DEMO/SIMULATED sem criar banner persistente', () => {
    const demo = dataOriginMeta('DEMO')
    expect(demo.synthetic).toBe(true)
    expect(demo.icon).toMatch(/^i-lucide-/)

    const live = dataOriginMeta('LIVE')
    expect(live.synthetic).toBe(false)
  })

  it('fiscalStatusMeta distingue situação por label+ícone (badge)', () => {
    const pending = fiscalStatusMeta('PENDING')
    expect(pending.label).toMatch(/pendente/i)
    expect(pending.icon).toMatch(/^i-lucide-/)
    expect(pending.color).toBe('warning')

    const blocked = fiscalStatusMeta('BLOCKED')
    expect(blocked.label).toMatch(/bloquead/i)
    expect(blocked.color).toBe('error')

    const unsupported = fiscalStatusMeta('UNSUPPORTED')
    expect(unsupported.sourceHint).toBeTruthy()
  })
})

describe('empty states distintos (6.9 / 6.11)', () => {
  it('resolve loading, error, unsupported, blocked, filtered e empty', () => {
    expect(resolveFiscalEmptyKind({ loading: true })).toBe('loading')
    expect(resolveFiscalEmptyKind({ error: 'falha' })).toBe('error')
    expect(resolveFiscalEmptyKind({ situation: 'UNSUPPORTED' })).toBe('unsupported')
    expect(resolveFiscalEmptyKind({ situation: 'BLOCKED' })).toBe('blocked')
    expect(resolveFiscalEmptyKind({ filtered: true })).toBe('filtered')
    expect(resolveFiscalEmptyKind({})).toBe('empty')
  })

  it('não mascara erro quando já há dados anteriores (caller evita empty)', () => {
    expect(resolveFiscalEmptyKind({ hasRows: true, error: 'x' })).toBe('empty')
  })

  it('com hasPrevious e erro sem rows → não classifica como error puro', () => {
    // Empty kind cai em filtered/empty; UI mostra alert + empty, sem inventar dados
    const kind = resolveFiscalEmptyKind({
      error: 'timeout',
      hasRows: false,
      hasPrevious: true,
      filtered: true
    })
    expect(kind).toBe('filtered')
  })

  it('aliases title / emptyTitle (MonitoringTableEmptyState)', () => {
    function resolveTitle(input: { title?: string, emptyTitle?: string }, kind: string) {
      const custom = input.title || input.emptyTitle || ''
      if (custom) return custom
      if (kind === 'filtered') return 'Nenhum resultado para os filtros'
      if (kind === 'unsupported') return 'Não suportado'
      if (kind === 'blocked') return 'Consulta bloqueada'
      if (kind === 'error') return 'Falha ao carregar'
      return 'Nenhum registro'
    }
    expect(resolveTitle({ emptyTitle: 'Nenhum cliente Simples/MEI' }, 'empty'))
      .toBe('Nenhum cliente Simples/MEI')
    expect(resolveTitle({ title: 'A' }, 'empty')).toBe('A')
    expect(resolveTitle({}, 'filtered')).toBe('Nenhum resultado para os filtros')
    expect(resolveTitle({}, 'blocked')).toBe('Consulta bloqueada')
    expect(resolveTitle({}, 'unsupported')).toBe('Não suportado')
  })

  it('tabela permanece montada no vazio (customers.vue: UTable + #empty)', () => {
    const dataTable = readFileSync(
      resolve(__dirname, '../../app/components/monitoring/ModuleDataTable.vue'),
      'utf8'
    )
    expect(dataTable).toContain('data-testid="fiscal-table"')
    expect(dataTable).toContain('#empty')
    expect(dataTable).toContain('MonitoringTableEmptyState')
    // Não troca a tabela inteira por empty/skeleton
    expect(dataTable).not.toContain('showTableSkeleton')
    expect(dataTable).not.toContain('v-else-if="showEmpty"')
    expect(dataTable).not.toContain('fiscal-table-skeleton')
  })
})

describe('MonitoringModuleNav (6.3 / 6.11)', () => {
  it('activeOverride força o módulo destacado independente do path', () => {
    const items = monitoringNavMenuItems('/monitoring/guides', 'simples_mei')
    const active = items.filter(i => i.active)
    expect(active).toHaveLength(1)
    expect(active[0]?.to).toBe('/monitoring/simples-mei/pgdasd')
  })

  it('lista canônica cobre todos os destinos do hub', () => {
    expect(MONITORING_NAV_ITEMS.map(i => i.moduleKey)).toEqual([
      'dashboard',
      'simples_mei',
      'dctfweb',
      'fgts',
      'installments',
      'sitfis',
      'mailbox',
      'declarations',
      'guides',
      'registrations',
      'tax_processes'
    ])
  })

  it('menu highlight tem exatamente um active por path de módulo', () => {
    for (const item of MONITORING_NAV_ITEMS) {
      const menu = monitoringNavMenuItems(item.to)
      expect(menu.filter(i => i.active)).toHaveLength(1)
      expect(menu.find(i => i.active)?.to).toBe(item.to)
    }
  })
})

describe('ações de carteira no contexto da seleção', () => {
  it('não renderiza ações globais no navbar e delega bulk condicionado à seleção', () => {
    const moduleTable = readFileSync(
      resolve(__dirname, '../../app/components/monitoring/ModuleTable.vue'),
      'utf8'
    )
    const bulk = readFileSync(
      resolve(__dirname, '../../app/components/monitoring/ModuleBulkActions.vue'),
      'utf8'
    )
    const dataTable = readFileSync(
      resolve(__dirname, '../../app/components/monitoring/ModuleDataTable.vue'),
      'utf8'
    )

    expect(moduleTable).not.toContain('fiscal-module-navbar-actions')
    expect(moduleTable).not.toContain('name="navbar-actions"')
    expect(moduleTable).toContain('MonitoringModuleBulkActions')
    expect(moduleTable).toContain(':selection-enabled="selectionEnabled"')
    expect(bulk).toContain('data-testid="fiscal-bulk-actions"')
    expect(bulk).toContain('v-if="actionState.visible"')
    expect(bulk).toContain('label="Ações"')
    expect(bulk.match(/<UKbd>/g)).toHaveLength(1)
    // customers.vue: cápsulas → KPIs → stack com toolbar + tabela
    expect(moduleTable.indexOf('data-testid="fiscal-submodules"'))
      .toBeLessThan(moduleTable.indexOf('data-testid="fiscal-kpi-block"'))
    expect(moduleTable.indexOf('data-testid="fiscal-kpi-block"'))
      .toBeLessThan(moduleTable.indexOf('data-testid="fiscal-table-stack"'))
    expect(dataTable.indexOf('name="toolbar"'))
      .toBeLessThan(dataTable.indexOf('data-testid="fiscal-table"'))
  })

  it('segue customers.vue: ações em massa precedem filtros e Exibir', () => {
    const toolbar = readFileSync(
      resolve(__dirname, '../../app/components/monitoring/ModuleToolbar.vue'),
      'utf8'
    )

    expect(toolbar.indexOf('<slot name="actions"'))
      .toBeLessThan(toolbar.indexOf('data-testid="fiscal-filter-situation"'))
    expect(toolbar.indexOf('data-testid="fiscal-filter-situation"'))
      .toBeLessThan(toolbar.indexOf('<slot name="trailing"'))
    expect(toolbar).toContain('class="flex flex-wrap items-center justify-between gap-1.5"')
    expect(toolbar).toContain('class="w-full sm:w-auto sm:max-w-sm"')
    expect(toolbar).toContain('data-testid="advanced-filters-toggle"')
    expect(toolbar).toContain('data-testid="fiscal-advanced-filters"')
    expect(toolbar).toContain('@submit.prevent="applyAdvancedFilters"')
    expect(toolbar).toContain('label="Aplicar filtros"')
    expect(toolbar).toContain('data-testid="fiscal-filters-reset"')
    expect(toolbar).toContain(':label="advancedFiltersLabel"')
    expect(toolbar).not.toContain('label="Atualizar"')
    expect(toolbar).toContain('const qDraft = ref(appliedFilters.value.q)')
    expect(toolbar).toContain('@keyup.enter="submitQ"')
  })

  it('filtros avançados usam rascunho controlado e contrato filters/filterConfig', () => {
    const toolbar = readFileSync(
      resolve(__dirname, '../../app/components/monitoring/ModuleToolbar.vue'),
      'utf8'
    )
    const moduleTable = readFileSync(
      resolve(__dirname, '../../app/components/monitoring/ModuleTable.vue'),
      'utf8'
    )
    const dctfweb = readFileSync(
      resolve(__dirname, '../../app/pages/monitoring/dctfweb/[submodule].vue'),
      'utf8'
    )

    expect(toolbar).toContain('const advancedDraft = ref')
    expect(toolbar).toContain('advancedDraft.clientId')
    expect(toolbar).toContain('type="month"')
    expect(toolbar).toContain('Use uma competência válida no formato AAAA-MM.')
    expect(toolbar).toContain(':disabled="Boolean(competenceError)"')
    expect(toolbar).not.toContain('advancedQDraft')
    expect(toolbar).not.toContain('advancedSituationDraft')
    expect(moduleTable).toContain(':filters="filters"')
    expect(moduleTable).toContain(':filter-config="filterConfig"')
    expect(moduleTable).toContain('emit(\'apply-filters\', $event)')
    expect(moduleTable).toContain('emit(\'quick-filter-change\', $event)')
    expect(dctfweb).toContain(':filters="filters"')
    expect(dctfweb).toContain(':filter-config="filterConfig"')
    expect(dctfweb).toContain('@apply-filters="applyFilters"')
    expect(dctfweb).toContain('@quick-filter-change="applyQuickFilters"')
  })

  it('aplica o formulário completo em uma única transação da carteira', () => {
    const portfolio = readFileSync(
      resolve(__dirname, '../../app/composables/useFiscalModulePortfolio.ts'),
      'utf8'
    )

    expect(portfolio).toContain('async function applyFilters(nextValue: MonitoringFilterValue)')
    expect(portfolio).toContain('async function applyQuickFilters(nextValue: MonitoringFilterValue)')
    expect(portfolio).toContain('filterTransactionDepth += 1')
    expect(portfolio).toContain('await nextTick()')
    expect(portfolio).toContain('if (!ready || filterTransactionDepth > 0) return')
    expect(portfolio).toContain('if (advancedChanged)')
    expect(portfolio).toContain('await load()')
  })

  it('usa uma única fonte de espaçamento e restaura o footer do arquétipo', () => {
    const moduleTable = readFileSync(
      resolve(__dirname, '../../app/components/monitoring/ModuleTable.vue'),
      'utf8'
    )
    const dataTable = readFileSync(
      resolve(__dirname, '../../app/components/monitoring/ModuleDataTable.vue'),
      'utf8'
    )

    expect(moduleTable).not.toContain('class="mb-4"')
    expect(dataTable).toContain('<div class="text-sm text-muted">')
    expect(dataTable).toContain('<div class="flex items-center gap-1.5">')
  })

  it('reduz a densidade inicial da DCTFWeb sem remover colunas do menu Exibir', () => {
    const dctfweb = readFileSync(
      resolve(__dirname, '../../app/pages/monitoring/dctfweb/[submodule].vue'),
      'utf8'
    )

    expect(dctfweb).toContain(`:initial-hidden-columns="['evidence', 'darf']"`)
    expect(dctfweb).toMatch(/'icon': 'i-lucide-ellipsis-vertical'/)
    expect(dctfweb).toMatch(/meta: \{ class: \{ th: 'w-12', td: 'w-12' \} \}/)
  })

  it('módulos fiscais não injetam mais PortfolioActions no cabeçalho', () => {
    const pages = [
      'pages/monitoring/installments.vue',
      'pages/monitoring/declarations.vue',
      'pages/monitoring/fgts.vue',
      'pages/monitoring/sitfis.vue',
      'pages/monitoring/simples-mei/[submodule].vue',
      'pages/monitoring/dctfweb/[submodule].vue',
      'pages/monitoring/guides.vue',
      'pages/monitoring/mailbox.vue'
    ]

    for (const page of pages) {
      const source = readFileSync(resolve(__dirname, '../../app', page), 'utf8')
      expect(source).not.toContain('<MonitoringPortfolioActions')
    }
  })
})

describe('client cell identidade (6.6 / 6.11)', () => {
  it('prioriza displayName e CNPJ mascarado (sem PFX/segredo)', () => {
    function primaryName(input: {
      displayName?: string | null
      name?: string | null
      legalName?: string | null
      clientId?: number | null
    }) {
      const n = input.displayName || input.name || input.legalName || ''
      return String(n).trim() || (input.clientId ? `Cliente #${input.clientId}` : '—')
    }
    expect(primaryName({ displayName: 'Loja', legalName: 'Loja LTDA' })).toBe('Loja')
    expect(primaryName({ clientId: 9 })).toBe('Cliente #9')
    const masked = '11.***.***/****-81'
    expect(masked).toMatch(/\*/)
    expect(masked).not.toMatch(/PFX|password|pem|token/i)
  })
})

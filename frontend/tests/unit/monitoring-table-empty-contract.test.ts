/**
 * Contrato: tabelas de monitoramento nunca somem no vazio
 * (padrão customers.vue / ModuleTable: UTable + #empty).
 */
import { readdirSync, readFileSync, statSync } from 'node:fs'
import { join, resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

const APP = resolve(__dirname, '../../app')
const PAGES = join(APP, 'pages/monitoring')
const COMPONENTS = join(APP, 'components/monitoring')

function walk(dir: string, acc: string[] = []): string[] {
  for (const entry of readdirSync(dir)) {
    const full = join(dir, entry)
    const st = statSync(full)
    if (st.isDirectory()) walk(full, acc)
    else if (full.endsWith('.vue')) acc.push(full)
  }
  return acc
}

describe('contrato empty de tabelas de monitoramento', () => {
  it('ModuleTable mantém UTable + #empty (sem skeleton/empty que substitui a tabela)', () => {
    const src = readFileSync(join(COMPONENTS, 'ModuleTable.vue'), 'utf8')
    expect(src).toContain('data-testid="fiscal-table"')
    expect(src).toContain('#empty')
    expect(src).toContain('MonitoringTableEmptyState')
    expect(src).not.toContain('fiscal-table-skeleton')
    expect(src).not.toContain('v-else-if="showEmpty"')
    expect(src).not.toContain('showTableSkeleton')
  })

  it('ModuleTable: busca/filtros imediatamente acima da tabela (customers.vue)', () => {
    const src = readFileSync(join(COMPONENTS, 'ModuleTable.vue'), 'utf8')
    const stack = src.indexOf('data-testid="fiscal-table-stack"')
    const toolbar = src.indexOf('<slot name="toolbar">')
    const table = src.indexOf('data-testid="fiscal-table"')
    const kpis = src.indexOf('data-testid="fiscal-kpi-block"')
    expect(stack).toBeGreaterThan(-1)
    expect(kpis).toBeLessThan(stack)
    expect(toolbar).toBeGreaterThan(stack)
    expect(toolbar).toBeLessThan(table)
  })

  it('carteiras usam MonitoringModuleTable (herdam o padrão empty)', () => {
    const portfolioPages = [
      'simples-mei/[submodule].vue',
      'dctfweb/[submodule].vue',
      'declarations.vue',
      'fgts.vue',
      'guides.vue',
      'installments.vue',
      'registrations.vue',
      'sitfis.vue',
      'tax-processes.vue'
    ]
    for (const rel of portfolioPages) {
      const src = readFileSync(join(PAGES, rel), 'utf8')
      expect(src, rel).toContain('MonitoringModuleTable')
    }
  })

  it('parcelamentos: modalidades do catálogo como cápsulas em largura total', () => {
    const src = readFileSync(join(PAGES, 'installments.vue'), 'utf8')
    expect(src).toContain('installments-modality-tabs')
    expect(src).toContain('variant="pill"')
    expect(src).toContain('PARCSN')
    expect(src).toContain('PARCMEI')
    expect(src).toContain('RELPMEI')
    expect(src).toContain('selectedModality')
    // Não mais badges soltos no card de catálogo
    expect(src).not.toContain('Modalidades do catálogo')
  })

  it('detalhe do cliente: seções-lista usam UTable + #empty (não v-else que some a tabela)', () => {
    const src = readFileSync(join(PAGES, 'clients/[clientId].vue'), 'utf8')
    expect(src).toContain('#empty')
    expect(src).toContain('MonitoringTableEmptyState')
    expect(src).toContain('data-testid="client-section-table-overview"')
    expect(src).toContain('data-testid="client-section-table-runs"')
    // Anti-padrão antigo: PLACEHOLDER + v-else na UTable
    expect(src).not.toContain('PLACEHOLDER_')
    expect(src).not.toMatch(/v-else\s*\n\s*:data="snapshots"/)
    expect(src).not.toMatch(/v-if="!snapshots\.length"/)
  })

  it('mailbox e hub não ocultam a casca no empty', () => {
    const mailbox = readFileSync(join(PAGES, 'mailbox.vue'), 'utf8')
    expect(mailbox).toContain('MonitoringMailboxList')
    expect(mailbox).not.toContain('v-if="!loadError || rows.length"')
    expect(mailbox).toContain('mailbox-pagination')

    const list = readFileSync(join(COMPONENTS, 'MailboxList.vue'), 'utf8')
    expect(list).toContain('MonitoringTableEmptyState')
    expect(list).toContain('data-testid="mailbox-list"')

    const hub = readFileSync(join(PAGES, 'index.vue'), 'utf8')
    expect(hub).toContain('MonitoringTableEmptyState')
    expect(hub).not.toMatch(/UEmpty[\s\S]{0,40}Nada em atenção/)
  })

  it('nenhuma página monitoring esconde UTable com v-if de length + v-else', () => {
    const files = walk(PAGES)
    const offenders: string[] = []
    for (const file of files) {
      const src = readFileSync(file, 'utf8')
      // UTable só no ramo v-else após checagem de length
      if (/v-if="![^"]*\.length"[\s\S]{0,200}<UTable[\s\S]{0,80}v-else/.test(src)
        || /<UTable[\s\S]{0,40}v-if="[^"]*\.length"/.test(src)) {
        offenders.push(file.replace(APP + '/', ''))
      }
    }
    expect(offenders).toEqual([])
  })
})

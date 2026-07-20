import { existsSync, readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'
import {
  LIST_TABLE_CLASS,
  LIST_TABLE_FOOTER_CLASS,
  LIST_TABLE_PER_PAGE_ITEMS,
  LIST_TABLE_STACK_CLASS,
  normalizeListTablePerPage
} from '~/utils/table-ui'

const shell = (name: string) =>
  resolve(process.cwd(), `app/components/shell/${name}.vue`)

describe('list-table-layout (customers.vue @ 0f30c09)', () => {
  it('exporta classes canônicas da lista admin', () => {
    expect(LIST_TABLE_CLASS).toBe('shrink-0')
    expect(LIST_TABLE_FOOTER_CLASS).toBe(
      'flex flex-col gap-3 border-t border-default pt-4 mt-auto sm:flex-row sm:items-center sm:justify-between'
    )
    expect(LIST_TABLE_STACK_CLASS).toContain('min-h-full')
    expect(LIST_TABLE_STACK_CLASS).toContain('flex-1')
    expect(LIST_TABLE_PER_PAGE_ITEMS.map(i => i.value)).toEqual([10, 20, 50])
    expect(normalizeListTablePerPage(25)).toBe(20)
  })

  it('documenta regra Shell* em table-ui', () => {
    const source = readFileSync(resolve(process.cwd(), 'app/utils/table-ui.ts'), 'utf8')
    expect(source).toContain('ShellDataTable')
    expect(source).toContain('NÃO montam')
    expect(source).toContain('coluna fantasma')
    expect(source).toContain('w-full')
  })

  it('carteiras fiscais usam Cliente w-full (sem faixa vazia em table-fixed)', () => {
    for (const rel of [
      'app/utils/pgdasd-table.ts',
      'app/utils/pgmei-table.ts',
      'app/utils/sitfis-table.ts'
    ]) {
      const source = readFileSync(resolve(process.cwd(), rel), 'utf8')
      expect(source, rel).toContain('min-w-48 w-full')
      expect(source, rel).not.toMatch(/w-\[\d+%\]/)
    }
  })

  it('PGDAS-D/PGMEI mantêm comunicação somente informativa na coluna Ações', () => {
    for (const rel of ['app/utils/pgdasd-table.ts', 'app/utils/pgmei-table.ts']) {
      const source = readFileSync(resolve(process.cwd(), rel), 'utf8')
      expect(source, rel).toContain('id: \'actions\'')
      expect(source, rel).not.toMatch(/id:\s*'send'/)
      expect(source, rel).toContain('communication-info')
      expect(source, rel).toContain('communication-preferences')
      expect(source, rel).not.toContain('AutomaticSwitch')
      expect(source, rel).not.toContain('BulkAutomaticSwitch')
    }
  })

  it('ShellDataTable e peças do kit existem', () => {
    expect(existsSync(shell('DataTable'))).toBe(true)
    expect(existsSync(shell('TableFooter'))).toBe(true)
    expect(existsSync(shell('ListEmpty'))).toBe(true)
    expect(existsSync(shell('LoadError'))).toBe(true)
    expect(existsSync(shell('PagePanel'))).toBe(true)
    expect(existsSync(shell('PageNavbar'))).toBe(true)

    const dataTable = readFileSync(shell('DataTable'), 'utf8')
    expect(dataTable).toContain('ShellTableFooter')
    expect(dataTable).toContain('update:itemsPerPage')
    expect(dataTable).toContain('LIST_TABLE_CLASS')
    expect(dataTable).toContain('dashboard')
    expect(dataTable).toContain('monitoring-compact')
  })

  it('ModuleDataTable e ClientCatalogList usam o contrato de footer', () => {
    const moduleSource = readFileSync(
      resolve(process.cwd(), 'app/components/monitoring/ModuleDataTable.vue'),
      'utf8'
    )
    const clientsSource = readFileSync(
      resolve(process.cwd(), 'app/components/clients/ClientCatalogList.vue'),
      'utf8'
    )
    const footerSource = readFileSync(shell('TableFooter'), 'utf8')

    expect(moduleSource).not.toContain('LIST_TABLE_STACK_CLASS')
    expect(moduleSource).toContain('ShellDataTable')
    expect(moduleSource).toContain('update:perPage')
    expect(moduleSource).not.toContain('sticky="header"')

    const moduleTableSource = readFileSync(
      resolve(process.cwd(), 'app/components/monitoring/ModuleTable.vue'),
      'utf8'
    )
    // Filhos do #body com `contents` — mesmo respiro de /clients (sem flex-1 aninhado).
    expect(moduleTableSource).toContain('fiscal-module-body')
    expect(moduleTableSource).toContain('class="contents"')
    expect(moduleTableSource).not.toContain('min-h-0 min-w-0 flex-1 flex-col gap-3')
    expect(moduleSource).toContain('fiscal-table-stack')
    expect(moduleTableSource).toContain('perPage: 20')

    expect(clientsSource).toContain('ShellDataTable')

    expect(footerSource).toContain('LIST_TABLE_FOOTER_CLASS')
    expect(footerSource).toContain('LIST_TABLE_PER_PAGE_ITEMS')
    expect(footerSource).toContain('USelect')
    expect(footerSource).toContain('UPagination')
    expect(footerSource).toContain('list-table-per-page')
  })

  it('carteiras não forçam min-w artificial na table (preferem caber na viewport)', () => {
    const moduleSource = readFileSync(
      resolve(process.cwd(), 'app/components/monitoring/ModuleDataTable.vue'),
      'utf8'
    )
    expect(moduleSource).toContain('FIT_VIEWPORT_TABLE_CLASS')
    expect(moduleSource).toContain('w-full min-w-0')
    expect(moduleSource).not.toMatch(/=\s*'min-w-\[\d+rem\]'/)
    expect(moduleSource).not.toContain('DEFAULT_SCROLL_MIN_WIDTH')

    const monitoringPages = [
      'app/pages/monitoring/simples-mei/index.vue',
      'app/pages/monitoring/dctfweb/index.vue',
      'app/pages/monitoring/guides.vue',
      'app/pages/monitoring/fgts.vue',
      'app/pages/monitoring/declarations.vue',
      'app/pages/monitoring/installments.vue',
      'app/pages/monitoring/sitfis.vue'
    ]
    for (const rel of monitoringPages) {
      const source = readFileSync(resolve(process.cwd(), rel), 'utf8')
      expect(source, rel).not.toMatch(/table-class="min-w-\[\d+(px|rem)\]"/)
    }
  })

  it('carteiras com comunicação alinham Ações · Rastreio · Última consulta', () => {
    for (const rel of [
      'app/utils/pgdasd-table.ts',
      'app/utils/pgmei-table.ts',
      'app/utils/dctfweb-table.ts'
    ]) {
      const source = readFileSync(resolve(process.cwd(), rel), 'utf8')
      expect(source, rel).toContain('MONITORING_CONSULTED_LABEL')
      expect(source, rel).toContain('MONITORING_TRACKING_LABEL')
      expect(source, rel).toContain('MONITORING_ACTIONS_LABEL')
      expect(source, rel).not.toMatch(/id:\s*'send'/)
      expect(source, rel).not.toContain('Última Busca')
    }
  })

  it('Simples MEI tracking é um único atalho (sem trio status+download+search)', () => {
    for (const rel of ['app/utils/pgdasd-table.ts', 'app/utils/pgmei-table.ts']) {
      const source = readFileSync(resolve(process.cwd(), rel), 'utf8')
      expect(source, rel).not.toContain('pgdasd-tracking-attachment')
      expect(source, rel).not.toContain('pgmei-tracking-attachment')
      expect(source, rel).not.toContain('pgdasd-artifacts-menu')
      expect(source, rel).toMatch(/testId: 'pgdasd-tracking'|testId: 'pgmei-tracking'/)
    }
  })
})

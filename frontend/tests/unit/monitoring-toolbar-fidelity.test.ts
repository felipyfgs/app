import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

describe('fidelidade toolbar ↔ customers.vue', () => {
  it('toolbar fica imediatamente antes da tabela no stack e preserva ordem de ações', () => {
    const moduleTable = readFileSync(
      resolve(__dirname, '../../app/components/monitoring/ModuleTable.vue'),
      'utf8'
    )
    const dataTable = readFileSync(
      resolve(__dirname, '../../app/components/monitoring/ModuleDataTable.vue'),
      'utf8'
    )
    const toolbar = readFileSync(
      resolve(__dirname, '../../app/components/monitoring/ModuleToolbar.vue'),
      'utf8'
    )
    const shell = readFileSync(
      resolve(__dirname, '../../app/components/shell/ListFilterToolbar.vue'),
      'utf8'
    )

    // customers.vue: stack com toolbar + tabela
    expect(moduleTable.indexOf('data-testid="fiscal-table-stack"'))
      .toBeLessThan(moduleTable.indexOf('MonitoringModuleDataTable'))
    expect(dataTable.indexOf('name="toolbar"'))
      .toBeLessThan(dataTable.indexOf('data-testid="fiscal-table"'))

    // Adapter monitoring → shell canônico
    expect(toolbar).toContain('ShellListFilterToolbar')
    expect(toolbar).toContain('test-id-prefix="fiscal-filter"')
    expect(toolbar).toContain('surface')
    expect(toolbar).toContain('onApplyPreset')

    // bulk → filtros (direita) → salvar/presets → refresh → trailing (Exibir)
    expect(shell.indexOf('<slot name="actions"'))
      .toBeLessThan(shell.indexOf('`${prefix}-structured`'))
    expect(shell.indexOf('save-filters-button'))
      .toBeLessThan(shell.indexOf('`${prefix}-refresh`'))
    expect(shell.indexOf('`${prefix}-refresh`'))
      .toBeLessThan(shell.indexOf('<slot name="trailing"'))

    // busca à esquerda, filtros no bloco da direita
    expect(shell.indexOf('`${prefix}-q`'))
      .toBeLessThan(shell.indexOf('`${prefix}-structured`'))
    expect(shell).toContain('LIST_FILTER_SEARCH_INPUT')
    expect(shell).toContain('LIST_FILTER_TOOLBAR_STACK')
    expect(shell).toContain('LIST_FILTER_ACTIONS_ROW')
    expect(shell).not.toContain('basis-full')
    expect(shell).toContain('DataTableFilterRoot')

    const filterRoot = readFileSync(
      resolve(__dirname, '../../app/components/data-table-filter/Root.vue'),
      'utf8'
    )
    expect(filterRoot).toContain(':portal="true"')
    expect(filterRoot).toContain('bg-default shadow-lg')
  })
})

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

    // customers.vue: stack com toolbar + tabela
    expect(moduleTable.indexOf('data-testid="fiscal-table-stack"'))
      .toBeLessThan(moduleTable.indexOf('MonitoringModuleDataTable'))
    expect(dataTable.indexOf('name="toolbar"'))
      .toBeLessThan(dataTable.indexOf('data-testid="fiscal-table"'))

    // bulk actions → refresh → trailing (Exibir)
    expect(toolbar.indexOf('<slot name="actions"'))
      .toBeLessThan(toolbar.indexOf('data-testid="fiscal-filter-refresh"'))
    expect(toolbar.indexOf('data-testid="fiscal-filter-refresh"'))
      .toBeLessThan(toolbar.indexOf('<slot name="trailing"'))

    // chips entre controles da toolbar e a tabela (abaixo da faixa principal)
    expect(toolbar.indexOf('data-testid="page-toolbar"'))
      .toBeLessThan(toolbar.indexOf('data-testid="fiscal-structured-filters"'))
    expect(toolbar).toContain('DataTableFilterRoot')
  })
})

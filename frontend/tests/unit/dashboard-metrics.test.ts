import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { isSyntheticFiscalOrigin } from '../../app/types/fiscal-modules'

/**
 * Garante que o dashboard não incorpora gráfico/variação/filtro temporal artificial.
 * A ordem operacional e a ausência de dados inventados são requisitos do change.
 */
describe('dashboard operacional', () => {
  const source = [
    readFileSync(resolve(__dirname, '../../app/pages/index.vue'), 'utf8'),
    readFileSync(resolve(__dirname, '../../app/components/home/HomeStats.vue'), 'utf8'),
    readFileSync(resolve(__dirname, '../../app/components/home/HomeOperations.vue'), 'utf8'),
    readFileSync(resolve(__dirname, '../../app/components/home/HomeTotals.vue'), 'utf8')
  ].join('\n')

  it('não inclui gráfico HomeChart, Unovis ou variação percentual artificial', () => {
    expect(source).not.toMatch(/HomeChart|unovis|variation|%|PeriodSelect|DateRangePicker/i)
  })

  it('prioriza indicadores de severidade operacional no código', () => {
    expect(source).toContain('Cursores bloqueados')
    expect(source).toContain('Falhas (24h)')
    expect(source).toContain('Sincronizações vencidas')
    // Totais informativos vêm depois
    const blocked = source.indexOf('Cursores bloqueados')
    const clients = source.indexOf('Clientes ativos')
    expect(blocked).toBeGreaterThan(-1)
    expect(clients).toBeGreaterThan(blocked)
  })

  it('preserva resumo anterior em falha e expõe timestamp de atualização', () => {
    expect(source).toContain('lastValidAt')
    expect(source).toContain('refreshError')
    expect(source).toContain('Atualizado')
  })

  it('expõe alerta de backup e atalho da inbox no home', () => {
    expect(source).toMatch(/backup\?\.never|backup\?\.stale|Nenhum backup/)
    expect(source).toContain('/health')
    expect(source).toContain('inboxItems')
  })
})

describe('dashboard fiscal /monitoring — origem e KPIs produtivos', () => {
  const hub = readFileSync(
    resolve(__dirname, '../../app/pages/monitoring/index.vue'),
    'utf8'
  )

  it('identifica origem por módulo e alerta sintético global', () => {
    expect(hub).toContain('FiscalDataOriginBadge')
    expect(hub).toContain('data-testid="dashboard-module-origin"')
    expect(hub).toContain('data-testid="dashboard-synthetic-alert"')
    expect(hub).toContain('hasSyntheticModules')
    expect(hub).toContain('isSyntheticFiscalOrigin')
  })

  it('exclui módulos sintéticos dos indicadores produtivos (module_errors)', () => {
    expect(hub).toContain('productiveModuleOverviews')
    expect(hub).toContain('productiveModuleOverviews.value.filter')
    // Lógica espelhada: DEMO não entra em contagem produtiva
    const modules = [
      { origin: 'LIVE', error: 1 },
      { origin: 'DEMO', error: 5 },
      { origin: 'SIMULATED', error: 2 }
    ]
    const productiveErrors = modules
      .filter(m => !isSyntheticFiscalOrigin(m.origin))
      .filter(m => m.error > 0)
      .length
    expect(productiveErrors).toBe(1)
  })
})

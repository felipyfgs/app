import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'
import {
  FISCAL_COUNTER_KPI_KEYS,
  fiscalKpiSituationFilter,
  fiscalSituationToKpiKey,
  normalizeFiscalModuleCounters,
  type FiscalKpiKey,
  type FiscalModuleCounters
} from '../../app/types/fiscal-modules'

const PRIMARY = ['up_to_date', 'processing', 'pending', 'attention'] as const

/** Espelha a lógica de visibilidade do MonitoringKpiStrip (primários fixos). */
function visibleKpiKeys(
  counters: Partial<FiscalModuleCounters> | null | undefined,
  activeKey: FiscalKpiKey,
  showError = true
): FiscalKpiKey[] {
  const c = normalizeFiscalModuleCounters(counters)
  const primary = new Set<string>(PRIMARY)
  const keys: FiscalKpiKey[] = ['total']
  for (const key of FISCAL_COUNTER_KPI_KEYS) {
    if (key === 'error' && !showError) continue
    const isPrimary = primary.has(key)
    if (!isPrimary && c[key] <= 0 && activeKey !== key) continue
    keys.push(key)
  }
  return keys
}

describe('contadores do monitoramento', () => {
  const source = readFileSync(
    resolve(__dirname, '../../app/components/monitoring/KpiStrip.vue'),
    'utf8'
  )

  it('usa tabs compactas em vez de cards', () => {
    expect(source).toContain('ShellScrollableTabs')
    expect(source).not.toContain('<UPageCard')
    expect(source).not.toContain('<ShellKpiStrip')
    expect(source).not.toMatch(/Atualizando/)
    const scrollTabs = readFileSync(
      resolve(__dirname, '../../app/components/shell/ScrollableTabs.vue'),
      'utf8'
    )
    expect(scrollTabs).toContain('variant: \'pill\'')
  })

  it('fixa a faixa operacional Total + Em dia + Processando + Pendências + Atenção', () => {
    expect(source).toContain('PRIMARY_KPI_KEYS')
    expect(source).toContain('\'up_to_date\'')
    expect(source).toContain('\'processing\'')
    expect(source).toContain('\'pending\'')
    expect(source).toContain('\'attention\'')
    expect(source).toContain('Pendências')
    expect(source).toContain('Em dia')
    expect(source).toContain('normalizeFiscalModuleCounters')
    expect(source).toContain('@update:model-value="onSelect"')
    expect(source).toContain('fiscalKpiSituationFilter(k)')
  })

  it('sempre mostra os quatro primários mesmo em zero; Bloqueado só se > 0', () => {
    const blockedOnly: FiscalModuleCounters = {
      up_to_date: 0,
      processing: 0,
      pending: 0,
      attention: 0,
      error: 0,
      blocked: 10,
      unknown: 0,
      unsupported: 0,
      not_applicable: 0
    }
    expect(visibleKpiKeys(blockedOnly, 'total')).toEqual([
      'total',
      'up_to_date',
      'processing',
      'pending',
      'attention',
      'blocked'
    ])
  })

  it('com dados operacionais espelha a faixa de referência', () => {
    const sample: FiscalModuleCounters = {
      up_to_date: 38,
      processing: 0,
      pending: 81,
      attention: 3,
      error: 0,
      blocked: 0,
      unknown: 0,
      unsupported: 0,
      not_applicable: 0
    }
    expect(visibleKpiKeys(sample, 'total')).toEqual([
      'total',
      'up_to_date',
      'processing',
      'pending',
      'attention'
    ])
  })

  it('preserva secundário ativo mesmo em zero', () => {
    const empty = normalizeFiscalModuleCounters({})
    expect(visibleKpiKeys(empty, 'blocked')).toEqual([
      'total',
      'up_to_date',
      'processing',
      'pending',
      'attention',
      'blocked'
    ])
  })

  it('mapeia bloqueados e desconhecidos para situation da API', () => {
    expect(fiscalKpiSituationFilter('blocked')).toBe('BLOCKED')
    expect(fiscalKpiSituationFilter('unknown')).toBe('UNKNOWN')
    expect(fiscalSituationToKpiKey('BLOCKED')).toBe('blocked')
    expect(fiscalSituationToKpiKey('UNKNOWN')).toBe('unknown')
  })
})

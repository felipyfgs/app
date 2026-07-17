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

/** Espelha a lógica de visibilidade do MonitoringKpiStrip. */
function visibleKpiKeys(
  counters: Partial<FiscalModuleCounters> | null | undefined,
  activeKey: FiscalKpiKey,
  showError = true
): FiscalKpiKey[] {
  const c = normalizeFiscalModuleCounters(counters)
  const keys: FiscalKpiKey[] = ['total']
  for (const key of FISCAL_COUNTER_KPI_KEYS) {
    if (key === 'error' && !showError) continue
    if (c[key] > 0 || activeKey === key) keys.push(key)
  }
  return keys
}

describe('contadores do monitoramento', () => {
  const source = readFileSync(
    resolve(__dirname, '../../app/components/monitoring/KpiStrip.vue'),
    'utf8'
  )

  it('usa tabs compactas em vez de cards', () => {
    expect(source).toContain('<UTabs')
    expect(source).toContain(':content="false"')
    expect(source).toContain('variant="pill"')
    expect(source).not.toContain('<UPageCard')
    expect(source).not.toContain('<ShellKpiStrip')
    expect(source).not.toMatch(/Atualizando/)
  })

  it('é dirigido pelo catálogo de nove estados', () => {
    expect(source).toContain('FISCAL_COUNTER_KPI_KEYS')
    expect(source).toContain('normalizeFiscalModuleCounters')
    expect(source).toContain('fiscalStatusMeta')
    expect(source).toContain('count > 0 || active === key')
    expect(source).toContain('@update:model-value="onSelect"')
    expect(source).toContain('fiscalKpiSituationFilter(k)')
  })

  it('mostra Total + positivos; omite zeros; preserva ativo em zero', () => {
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
    expect(visibleKpiKeys(blockedOnly, 'total')).toEqual(['total', 'blocked'])
    expect(visibleKpiKeys(blockedOnly, 'pending')).toEqual(['total', 'pending', 'blocked'])
    expect(visibleKpiKeys(blockedOnly, 'unknown')).toEqual(['total', 'blocked', 'unknown'])
  })

  it('não renderiza sequência de KPIs zerados', () => {
    const empty = normalizeFiscalModuleCounters({})
    expect(visibleKpiKeys(empty, 'total')).toEqual(['total'])
  })

  it('mapeia bloqueados e desconhecidos para situation da API', () => {
    expect(fiscalKpiSituationFilter('blocked')).toBe('BLOCKED')
    expect(fiscalKpiSituationFilter('unknown')).toBe('UNKNOWN')
    expect(fiscalSituationToKpiKey('BLOCKED')).toBe('blocked')
    expect(fiscalSituationToKpiKey('UNKNOWN')).toBe('unknown')
  })
})

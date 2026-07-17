import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { beforeAll, describe, expect, it, vi } from 'vitest'
import { SIMPLES_MEI_TABS } from '../../app/types/fiscal-modules'
import {
  pgmeiDebtMeta,
  pgmeiFreshnessMeta,
  pgmeiSummary,
  pgmeiTotalLabel
} from '../../app/utils/pgmei'

beforeAll(() => vi.stubGlobal('resolveComponent', (name: string) => name))

function row(detail: Record<string, unknown> = {}) {
  return {
    module_key: 'simples_mei' as const,
    client_id: 1,
    legal_name: 'MEI Demo',
    detail: {
      submodule: 'PGMEI',
      pgmei: {
        year: 2026,
        debt_state: 'HAS_ACTIVE_DEBT',
        freshness_state: 'OUTDATED',
        debt_count: 2,
        total_cents: 15050,
        last_valid_query_at: '2026-01-01T00:00:00Z',
        communication: {
          automatic_requested: false,
          automatic_effective: false,
          execution_mode: 'TEMPLATE_ONLY',
          email_enabled: false,
          whatsapp_enabled: false,
          lock_version: 1
        }
      },
      ...detail
    }
  }
}

describe('pgmei monitoring UI', () => {
  it('expõe apenas duas cápsulas Simples Nacional e MEI', () => {
    expect(SIMPLES_MEI_TABS).toHaveLength(2)
    expect(SIMPLES_MEI_TABS.map(t => t.value)).toEqual(['PGDASD', 'PGMEI'])
  })

  it('resume debt state, total e frescor', () => {
    const summary = pgmeiSummary(row(), 2026)
    expect(summary?.debt_state).toBe('HAS_ACTIVE_DEBT')
    expect(pgmeiDebtMeta(summary?.debt_state).color).toBe('error')
    expect(pgmeiFreshnessMeta(summary?.freshness_state).color).toBe('warning')
    expect(pgmeiTotalLabel(summary)).toMatch(/150/)
    expect(pgmeiSummary(row(), 2025)).toBeNull()
  })

  it('colunas PGMEI na ordem da spec (fonte)', () => {
    const source = readFileSync(
      resolve(__dirname, '../../app/utils/pgmei-table.ts'),
      'utf8'
    )
    const ids = [...source.matchAll(/id: '([^']+)'/g)].map(match => match[1])
    expect(ids).toEqual([
      'client',
      'active_debt',
      'total_debt',
      'send',
      'automatic',
      'tracking',
      'consulted',
      'details'
    ])
  })

  it('página canônica usa duas cápsulas e filtro anual', () => {
    const page = readFileSync(
      resolve(__dirname, '../../app/pages/monitoring/simples-mei/index.vue'),
      'utf8'
    )
    expect(page).toContain('SIMPLES_MEI_TABS')
    expect(page).toContain('buildPgmeiColumns')
    expect(page).toContain('pgmei-year-filter')
    expect(page).toContain('MonitoringPgmeiHistoryModal')
    expect(page).not.toContain('DASN_SIMEI')
    expect(page).not.toContain('\'REGIME\'')
  })
})

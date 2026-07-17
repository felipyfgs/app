import { describe, expect, it } from 'vitest'
import { pgdasdStateMeta } from '../../app/composables/usePgdasdMonitoring'
import type { SimplesMeiClientRow } from '../../app/types/fiscal-modules'

describe('pgdasdStateMeta', () => {
  it('maps operational states with accessible labels', () => {
    expect(pgdasdStateMeta('CURRENT').color).toBe('success')
    expect(pgdasdStateMeta('DUE_WITHIN_DEADLINE').color).toBe('warning')
    expect(pgdasdStateMeta('OVERDUE_NOT_FOUND').color).toBe('error')
    expect(pgdasdStateMeta('UNVERIFIED').color).toBe('neutral')
    expect(pgdasdStateMeta(undefined).tooltip.length).toBeGreaterThan(10)
    expect(pgdasdStateMeta('CURRENT').label).toBe('Declaração atual')
  })
})

describe('SimplesMeiClientRow pgdasd detail', () => {
  it('accepts enriched detail shape for specialized table', () => {
    const row: SimplesMeiClientRow = {
      module_key: 'simples_mei',
      client_id: 1,
      legal_name: 'ACME LTDA',
      cnpj_masked: '12.345.678/0001-99',
      situation: 'UNKNOWN',
      coverage: 'FULL',
      detail: {
        submodule: 'PGDASD',
        declaration_state: 'UNVERIFIED',
        communication: {
          automatic_requested: false,
          automatic_effective: false,
          email_enabled: false,
          whatsapp_enabled: false,
          lock_version: 1,
          execution_mode: 'TEMPLATE_ONLY'
        },
        pgdasd: {
          declaration_state: 'UNVERIFIED',
          latest_declaration: null,
          rbt12: { status: 'NO_DAS' }
        }
      }
    }
    expect(row.detail.declaration_state).toBe('UNVERIFIED')
    expect(row.detail.communication?.automatic_effective).toBe(false)
    expect(row.detail.pgdasd?.rbt12?.status).toBe('NO_DAS')
  })

  it('documents expected specialized column ids for the PGDAS-D table', () => {
    // Contrato de UI (spec): ordem sem coluna CNPJ
    const columnIds = [
      'client',
      'last_declaration',
      'rbt12',
      'send',
      'automatic',
      'tracking',
      'consulted',
      'details'
    ]
    expect(columnIds).not.toContain('cnpj')
    expect(columnIds[0]).toBe('client')
    expect(columnIds.at(-1)).toBe('details')
  })
})

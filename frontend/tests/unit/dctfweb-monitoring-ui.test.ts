import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'
import type { DctfwebClientRow } from '../../app/types/fiscal-modules'
import {
  dctfwebDeclarationMeta,
  dctfwebLastDeclarationLabel,
  dctfwebSummary,
  formatDctfwebDate,
  formatDctfwebPeriod,
  isDctfwebCapsule,
  isMitCapsule
} from '../../app/utils/dctfweb'

function row(partial: Partial<DctfwebClientRow['detail']> & { client_id?: number } = {}): DctfwebClientRow {
  const { client_id, ...detail } = partial
  return {
    client_id: client_id ?? 1,
    name: 'ACME LTDA',
    legal_name: 'ACME LTDA',
    cnpj_masked: '12.***.***/****-34',
    situation: 'UNKNOWN',
    detail: {
      module_key: 'dctfweb',
      submodule: 'DCTFWEB',
      ...detail
    }
  } as DctfwebClientRow
}

describe('dctfweb utils', () => {
  it('formata PA e data', () => {
    expect(formatDctfwebPeriod('2026-02')).toBe('02/2026')
    expect(formatDctfwebPeriod('202602')).toBe('02/2026')
    expect(formatDctfwebDate('2026-03-15T12:00:00Z')).toMatch(/^\d{2}\/\d{2}\/\d{4}$/)
  })

  it('extrai resumo da carteira', () => {
    const summary = dctfwebSummary(row({
      dctfweb: {
        period_key: '2026-02',
        expected_period_key: '2026-02',
        declaration_state: 'CURRENT',
        last_declaration: { period_key: '2026-02', receipt_number: 'R1' },
        last_search_at: '2026-03-10T10:00:00Z',
        has_history: true
      }
    }))
    expect(summary?.declaration_state).toBe('CURRENT')
    expect(dctfwebLastDeclarationLabel(summary)).toBe('02/2026')
    expect(dctfwebDeclarationMeta('NO_MOVEMENT_VALID').label).toBe('Sem movimento')
    expect(dctfwebDeclarationMeta('CURRENT').color).toBe('success')
    expect(dctfwebDeclarationMeta('OVERDUE_NOT_FOUND').color).toBe('error')
    expect(dctfwebDeclarationMeta('valor-inventado').color).toBe('neutral')
  })

  it('cápsulas independentes', () => {
    expect(isDctfwebCapsule('DCTFWEB')).toBe(true)
    expect(isMitCapsule('MIT')).toBe(true)
    expect(isMitCapsule('DCTFWEB')).toBe(false)
  })
})

describe('renderer DCTFWeb', () => {
  it('materializa exatamente oito colunas na ordem normativa, sem seleção extra', () => {
    const source = readFileSync(
      resolve(__dirname, '../../app/utils/dctfweb-table.ts'),
      'utf8'
    )
    // Apenas o bloco de buildDctfwebColumns (antes de buildMitColumns).
    const dctfBlock = source.split('export function buildMitColumns')[0] || source
    const ids = [...dctfBlock.matchAll(/id: '([^']+)'/g)].map(match => match[1])
    expect(ids).toEqual([
      'situation',
      'last_declaration',
      'actions',
      'send',
      'client',
      'tracking',
      'last_search',
      'history'
    ])
    expect(ids).not.toEqual(
      expect.arrayContaining(['select', 'payment', 'darf', 'transmission', 'evidence', 'competence'])
    )
  })

  it('renderer MIT é independente e não reutiliza colunas DCTFWeb', () => {
    const source = readFileSync(
      resolve(__dirname, '../../app/utils/dctfweb-table.ts'),
      'utf8'
    )
    const mitBlock = source.split('export function buildMitColumns')[1] || ''
    const ids = [...mitBlock.matchAll(/id: '([^']+)'/g)].map(match => match[1])
    expect(ids).toContain('closure')
    expect(ids).not.toContain('last_declaration')
    expect(ids).not.toContain('history')
  })

  it('página DCTFWeb não oferece mutações fiscais na grade', () => {
    const page = readFileSync(
      resolve(__dirname, '../../app/pages/monitoring/dctfweb/index.vue'),
      'utf8'
    )
    expect(page).toContain('buildDctfwebColumns')
    expect(page).toContain('buildMitColumns')
    expect(page).not.toContain('FiscalMutationConfirmModal')
    expect(page).not.toContain('openTransmit')
    expect(page).not.toContain('Transmitir declaração')
  })
})

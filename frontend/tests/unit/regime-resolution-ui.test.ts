import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

function source(relativePath: string): string {
  return readFileSync(resolve(__dirname, '../../app', relativePath), 'utf8')
}

describe('resolução 104 do Regime de Caixa', () => {
  it('separa leitura local da solicitação explícita', () => {
    const api = source('composables/api/createFiscalApi.ts')
    const composable = source('composables/useRegimeResolutionMonitoring.ts')

    expect(api).toContain('/api/v1/fiscal/simples-mei/clients/${clientId}/regime-resolutions')
    expect(api).toContain('/api/v1/fiscal/simples-mei/regime-resolution/consult')
    expect(composable).toContain('fetchHistory')
    expect(composable).toContain('requestConsult')
    expect(composable).toContain('{ client_id: clientId, year }')
    expect(composable).not.toContain('office_id')
  })

  it('lista o documento local e exige confirmação antes da consulta', () => {
    const page = source('pages/monitoring/simples-mei/index.vue')
    const table = source('utils/pgdasd-table.ts')
    const modal = source('components/monitoring/RegimeResolutionModal.vue')

    expect(table).toContain('Resoluções do Regime de Caixa')
    expect(table).toContain('Atualizar resolução (ano atual)')
    expect(page).toContain('Confirmar consulta da resolução')
    expect(page).toContain('regime-resolution-consult-confirm')
    expect(modal).toContain('abrir este modal não consulta a SERPRO')
    expect(modal).toContain('regime-resolution-document')
    expect(modal).toContain('item.document.href')
    expect(modal).not.toContain('textoResolucao')
  })
})

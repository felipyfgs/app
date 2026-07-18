import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

function source(relativePath: string): string {
  return readFileSync(resolve(__dirname, '../../app', relativePath), 'utf8')
}

describe('opção anual de regime 103', () => {
  it('separa histórico local da solicitação explícita e não envia office_id', () => {
    const api = source('composables/api/createFiscalApi.ts')
    const composable = source('composables/useRegimeOptionMonitoring.ts')

    expect(api).toContain('/api/v1/fiscal/simples-mei/clients/${clientId}/regime-options')
    expect(api).toContain('/api/v1/fiscal/simples-mei/regime-option/consult')
    expect(composable).toContain('fetchHistory')
    expect(composable).toContain('requestConsult')
    expect(composable).toContain('{ client_id: clientId, year }')
    expect(composable).not.toContain('office_id')
  })

  it('oferece confirmação faturável e modal de dados locais sem retorno sensível', () => {
    const page = source('pages/monitoring/simples-mei/index.vue')
    const table = source('utils/pgdasd-table.ts')
    const modal = source('components/monitoring/RegimeOptionModal.vue')

    expect(table).toContain('Opção anual de regime')
    expect(table).toContain('Atualizar opção anual (ano atual)')
    expect(page).toContain('Confirmar consulta da opção anual')
    expect(page).toContain('regime-option-consult-confirm')
    expect(modal).toContain('abrir este modal não consulta a SERPRO')
    expect(modal).toContain('calendar_year')
    expect(modal).not.toContain('cnpjMatriz')
    expect(modal).not.toContain('demonstrativoPdf')
  })
})

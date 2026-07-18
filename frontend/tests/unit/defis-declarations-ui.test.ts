import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

function source(relativePath: string): string {
  return readFileSync(resolve(__dirname, '../../app', relativePath), 'utf8')
}

describe('declarações DEFIS 142', () => {
  it('separa histórico local da solicitação confirmada e não envia office_id', () => {
    const api = source('composables/api/createFiscalApi.ts')
    const composable = source('composables/useDefisDeclarationsMonitoring.ts')

    expect(api).toContain('/api/v1/fiscal/simples-mei/defis/clients/${clientId}/history')
    expect(api).toContain('/api/v1/fiscal/simples-mei/defis/clients/${clientId}/consult')
    expect(composable).toContain('fetchHistory')
    expect(composable).toContain('requestConsult')
    expect(composable).not.toContain('office_id')
  })

  it('oferece confirmação faturável e modal sem idDefis ou payload bruto', () => {
    const page = source('pages/monitoring/simples-mei/index.vue')
    const table = source('utils/pgdasd-table.ts')
    const modal = source('components/monitoring/DefisDeclarationsModal.vue')

    expect(table).toContain('Declarações DEFIS')
    expect(table).toContain('Atualizar declarações DEFIS')
    expect(page).toContain('Confirmar consulta das declarações DEFIS')
    expect(page).toContain('defis-consult-confirm')
    expect(modal).toContain('abrir este modal não consulta a SERPRO')
    expect(modal).toContain('calendar_year')
    expect(modal).not.toContain('idDefis')
    expect(modal).not.toContain('JSON.stringify')
  })
})

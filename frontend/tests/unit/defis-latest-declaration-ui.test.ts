import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

function source(relativePath: string): string {
  return readFileSync(resolve(__dirname, '../../app', relativePath), 'utf8')
}

describe('última DEFIS 143', () => {
  it('expõe apenas histórico local e consulta anual confirmada', () => {
    const api = source('composables/api/createFiscalApi.ts')
    const composable = source('composables/useDefisLatestDeclarationMonitoring.ts')

    expect(api).toContain('/defis/latest-declaration/clients/${clientId}/history')
    expect(api).toContain('calendar_year: calendarYear')
    expect(composable).toContain('fetchHistory')
    expect(composable).toContain('requestConsult')
    expect(composable).not.toContain('office_id')
  })

  it('mantém o PDF fora da UI e exige confirmação antes da chamada', () => {
    const page = source('pages/monitoring/simples-mei/index.vue')
    const table = source('utils/pgdasd-table.ts')
    const modal = source('components/monitoring/DefisLatestDeclarationModal.vue')

    expect(table).toContain('Última DEFIS e recibo')
    expect(table).toContain('Atualizar última DEFIS (ano atual)')
    expect(page).toContain('Confirmar consulta da última DEFIS')
    expect(page).toContain('defis-latest-consult-confirm')
    expect(modal).toContain('abrir este modal não consulta a SERPRO')
    expect(modal).toContain('download_path')
    expect(modal).not.toContain('idDefis')
    expect(modal).not.toContain('base64')
  })
})

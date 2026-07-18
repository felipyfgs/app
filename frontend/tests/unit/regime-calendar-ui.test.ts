import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

function source(relativePath: string): string {
  return readFileSync(resolve(__dirname, '../../app', relativePath), 'utf8')
}

describe('histórico de anos-calendário do regime', () => {
  it('separa leitura local da solicitação explícita 102', () => {
    const api = source('composables/api/createFiscalApi.ts')
    const composable = source('composables/useRegimeCalendarMonitoring.ts')

    expect(api).toContain('/api/v1/fiscal/simples-mei/clients/${clientId}/regime-calendar')
    expect(api).toContain('/api/v1/fiscal/simples-mei/regime-calendar/consult')
    expect(composable).toContain('fetchHistory')
    expect(composable).toContain('requestConsult')
    expect(composable).toContain('{ client_id: clientId }')
    expect(composable).not.toContain('office_id')
  })

  it('mostra somente projeção local e exige confirmação para a consulta faturável', () => {
    const page = source('pages/monitoring/simples-mei/index.vue')
    const table = source('utils/pgdasd-table.ts')
    const modal = source('components/monitoring/RegimeCalendarModal.vue')

    expect(table).toContain('Histórico de regimes')
    expect(page).toContain('Confirmar consulta de regimes')
    expect(page).toContain('regime-calendar-consult-confirm')
    expect(modal).toContain('abrir este modal não consulta a SERPRO')
    expect(modal).toContain('calendar_year')
    expect(modal).not.toContain('office_id')
  })
})

import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

function source(relativePath: string) {
  return readFileSync(resolve(__dirname, '../../app', relativePath), 'utf8')
}

describe('declaração DEFIS específica 144', () => {
  it('consulta somente uma referência local confirmada e não envia office_id', () => {
    const api = source('composables/api/createFiscalApi.ts')
    const composable = source('composables/useDefisSpecificDeclarationMonitoring.ts')

    expect(api).toContain('/defis/specific-declaration/clients/${clientId}/history')
    expect(api).toContain('/defis/specific-declaration/clients/${clientId}/consult')
    expect(api).toContain('confirmed: true, reference_id: referenceId')
    expect(composable).toContain('fetchHistory')
    expect(composable).toContain('requestConsult')
    expect(composable).not.toContain('office_id')
  })

  it('mantém os documentos no cofre e exige confirmação antes da chamada', () => {
    const page = source('pages/monitoring/simples-mei/index.vue')
    const table = source('utils/pgdasd-table.ts')
    const modal = source('components/monitoring/DefisSpecificDeclarationModal.vue')

    expect(table).toContain('Declaração DEFIS e recibo')
    expect(page).toContain('Confirmar consulta da declaração DEFIS')
    expect(page).toContain('defis-specific-consult-confirm')
    expect(modal).toContain('abrir este modal não consulta a SERPRO')
    expect(modal).toContain('download_path')
    expect(modal).not.toContain('idDefis')
    expect(modal).not.toContain('base64')
  })
})

import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

function source(relativePath: string): string {
  return readFileSync(resolve(process.cwd(), 'app', relativePath), 'utf8')
}

describe('histórico PGDAS-D no detalhe do cliente', () => {
  it('usa uma seção dedicada e remove o modal de histórico da carteira', () => {
    const clientDetail = source('pages/monitoring/clients/[clientId].vue')
    const fiscalNav = source('utils/client-fiscal-detail-navigation.ts')
    const portfolio = source('pages/monitoring/simples-mei/index.vue')

    expect(clientDetail).toContain('| \'pgdasd\'')
    expect(clientDetail).toContain('clientFiscalDetailNav')
    expect(fiscalNav).toContain('label: \'PGDAS-D\'')
    expect(clientDetail).toContain('<MonitoringPgdasdHistoryView')
    expect(clientDetail).not.toContain('v-if="tab !== \'pgdasd\'"')
    expect(portfolio).toContain('/monitoring/clients/${row.client_id}/pgdasd')
    expect(portfolio).not.toContain('MonitoringPgdasdHistoryModal')
  })
})

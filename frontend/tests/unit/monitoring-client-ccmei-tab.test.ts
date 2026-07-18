import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

const page = readFileSync(
  resolve(process.cwd(), 'app/pages/monitoring/clients/[clientId].vue'),
  'utf8'
)
const nav = readFileSync(
  resolve(process.cwd(), 'app/utils/client-fiscal-detail-navigation.ts'),
  'utf8'
)

describe('aba CCMEI no detalhe de monitoramento do cliente', () => {
  it('reúne consultas e emissão do certificado na rota existente de monitoramento', () => {
    expect(page).toContain('| \'ccmei\'')
    expect(page).toContain('clientFiscalDetailNav')
    expect(nav).toContain('label: \'CCMEI\'')
    expect(page).toContain('v-else-if="tab === \'ccmei\'"')
    expect(page).toContain('<ClientCcmeiPanel')
    expect(page).toContain('<ClientCcmeiRegistrationStatusPanel')
    expect(page).toContain('<ClientCcmeiCertificateIssuancePanel')
  })

  it('não dispara consulta externa ao selecionar a aba e não expõe contexto técnico', () => {
    expect(page).toContain('case \'ccmei\':\n        // Os painéis carregam somente projeções locais; egress exige clique explícito.')
    expect(page).not.toContain('office_id')
  })
})

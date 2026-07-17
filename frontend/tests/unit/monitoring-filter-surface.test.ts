import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

const pagesDir = resolve(__dirname, '../../app/pages/monitoring')

function readPage(relative: string) {
  return readFileSync(resolve(pagesDir, relative), 'utf8')
}

describe('superfície de filtros das nove listas de monitoramento', () => {
  it('Simples/MEI e DCTFWeb/MIT expõem situação, cliente e competência', () => {
    for (const file of ['simples-mei/[submodule].vue', 'dctfweb/[submodule].vue']) {
      const src = readPage(file)
      expect(src).toContain('key: \'situation\'')
      expect(src).toContain('key: \'clientId\'')
      expect(src).toContain('key: \'competence\'')
      expect(src).toContain('fields:')
    }
  })

  it('Simples/MEI e SITFIS expõem coverage (coluna de negócio)', () => {
    for (const file of ['simples-mei/[submodule].vue', 'sitfis.vue']) {
      const src = readPage(file)
      expect(src).toContain('key: \'coverage\'')
    }
  })

  it('Parcelamentos expõe situação, cliente e modality (server-side)', () => {
    const src = readPage('installments.vue')
    expect(src).toContain('key: \'situation\'')
    expect(src).toContain('key: \'clientId\'')
    expect(src).toContain('key: \'modality\'')
    expect(src).not.toMatch(/fields:[\s\S]*key: 'competence'/)
    expect(src).toContain('selectedModality')
  })

  it('SITFIS expõe situação, cliente e coverage sem competência', () => {
    const src = readPage('sitfis.vue')
    expect(src).toContain('key: \'situation\'')
    expect(src).toContain('key: \'clientId\'')
    expect(src).toContain('key: \'coverage\'')
    expect(src).not.toMatch(/fields:[\s\S]*key: 'competence'/)
  })

  it('Declarações expõem situação, cliente, competência e status de entrega', () => {
    const src = readPage('declarations.vue')
    expect(src).toContain('key: \'situation\'')
    expect(src).toContain('key: \'clientId\'')
    expect(src).toContain('key: \'competence\'')
    expect(src).toContain('key: \'deliveryStatus\'')
  })

  it('FGTS expõe situação, cliente e competência', () => {
    const src = readPage('fgts.vue')
    expect(src).toContain('key: \'situation\'')
    expect(src).toContain('key: \'clientId\'')
    expect(src).toContain('key: \'competence\'')
  })

  it('Guias expõe cliente e status de pagamento, sem competência na UI', () => {
    const src = readPage('guides.vue')
    expect(src).toContain('key: \'clientId\'')
    expect(src).toContain('key: \'paymentStatus\'')
    expect(src).not.toMatch(/fields:[\s\S]*key: 'competence'/)
    expect(src).toContain('// Endpoint atual não aplica competência')
    expect(src).toContain('surface="monitoring.guides"')
  })

  it('Cadastro/Vínculos e Processos expõem status e clientId', () => {
    for (const file of ['registrations.vue', 'tax-processes.vue']) {
      const src = readPage(file)
      expect(src).toContain('key: \'status\'')
      expect(src).toContain('key: \'clientId\'')
      expect(src).toContain('client_id:')
      expect(src).not.toContain('key: \'situation\'')
      expect(src).not.toContain('key: \'competence\'')
    }
  })

  it('Mailbox expõe surface de presets + triagem/cliente via toolbar', () => {
    const src = readPage('mailbox.vue')
    expect(src).toContain('surface="monitoring.mailbox"')
    expect(src).toContain('MonitoringModuleToolbar')
    expect(src).toContain('key: \'status\'')
    expect(src).toContain('key: \'clientId\'')
    expect(src).toContain('triage_status')
    expect(src).toContain('search: false')
  })

  it('troca de Office limpa filtros aplicados antes da nova carga', () => {
    const portfolio = readFileSync(
      resolve(__dirname, '../../app/composables/useFiscalModulePortfolio.ts'),
      'utf8'
    )
    expect(portfolio).toContain('clearFiltersForTenantSwitch')
    expect(portfolio).toContain('clientId.value = null')
    expect(portfolio).toContain('coverage.value = \'all\'')
    expect(portfolio).toContain('modality.value = \'all\'')

    const guides = readPage('guides.vue')
    expect(guides).toContain('paymentStatus.value = \'all\'')
    expect(guides).toContain('clientId.value = \'\'')

    for (const file of ['registrations.vue', 'tax-processes.vue']) {
      const src = readPage(file)
      expect(src).toContain('status.value = \'all\'')
      expect(src).toContain('clientId.value = null')
    }

    const mailbox = readPage('mailbox.vue')
    expect(mailbox).toContain('triage.value = \'all\'')
    expect(mailbox).toContain('clientId.value = \'\'')
  })

  it('requests de listagem não enviam office_id', () => {
    const files = [
      'simples-mei/[submodule].vue',
      'dctfweb/[submodule].vue',
      'installments.vue',
      'sitfis.vue',
      'declarations.vue',
      'fgts.vue',
      'guides.vue',
      'registrations.vue',
      'tax-processes.vue',
      'mailbox.vue'
    ]
    for (const file of files) {
      const src = readPage(file)
      expect(src).not.toMatch(/office_id\s*:/)
    }
    const portfolio = readFileSync(
      resolve(__dirname, '../../app/composables/useFiscalModulePortfolio.ts'),
      'utf8'
    )
    expect(portfolio).not.toMatch(/office_id\s*:/)
  })
})

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

  it('Parcelamentos e SITFIS expõem situação e cliente', () => {
    for (const file of ['installments.vue', 'sitfis.vue']) {
      const src = readPage(file)
      expect(src).toContain('key: \'situation\'')
      expect(src).toContain('key: \'clientId\'')
      expect(src).not.toMatch(/fields:[\s\S]*key: 'competence'/)
    }
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
  })

  it('Cadastro/Vínculos e Processos expõem apenas status', () => {
    for (const file of ['registrations.vue', 'tax-processes.vue']) {
      const src = readPage(file)
      expect(src).toContain('key: \'status\'')
      expect(src).not.toContain('key: \'situation\'')
      expect(src).not.toContain('key: \'clientId\'')
      expect(src).not.toContain('key: \'competence\'')
    }
  })

  it('troca de Office limpa filtros aplicados antes da nova carga', () => {
    const portfolio = readFileSync(
      resolve(__dirname, '../../app/composables/useFiscalModulePortfolio.ts'),
      'utf8'
    )
    expect(portfolio).toContain('clearFiltersForTenantSwitch')
    expect(portfolio).toContain('clientId.value = null')

    const guides = readPage('guides.vue')
    expect(guides).toContain('paymentStatus.value = \'all\'')
    expect(guides).toContain('clientId.value = \'\'')

    for (const file of ['registrations.vue', 'tax-processes.vue']) {
      const src = readPage(file)
      expect(src).toContain('status.value = \'all\'')
    }
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
      'tax-processes.vue'
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

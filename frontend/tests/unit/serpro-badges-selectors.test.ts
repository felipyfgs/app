import { describe, expect, it } from 'vitest'
import { resolveProvenanceBadge, provenanceBadgeMeta } from '../../app/utils/serpro-badges'
import {
  isValidSerproIdentity,
  normalizeSerproIdentity,
  SERPRO_SERVICE_OPTIONS,
  SERPRO_POWER_OPTIONS
} from '../../app/utils/serpro-selectors'
import { formatCnpj, normalizeCnpj } from '../../app/utils/format'

describe('badges simulado|real|estimado|conciliado', () => {
  it('classifica proveniência e bilhetagem', () => {
    expect(resolveProvenanceBadge({ is_simulated: true }).code).toBe('simulado')
    expect(resolveProvenanceBadge({ source_provenance: 'SERPRO_REAL' }).code).toBe('real')
    expect(resolveProvenanceBadge({ reconciliation_status: 'RECONCILED' }).code).toBe('conciliado')
    expect(resolveProvenanceBadge({ consumption_class: 'ESTIMATED' }).code).toBe('estimado')
    expect(resolveProvenanceBadge({
      source_provenance: 'SERPRO_REAL',
      result: 'POSSIBLY_BILLABLE'
    }).code).toBe('possivelmente_bilhetavel')
  })

  it('expõe labels pt-BR estáveis', () => {
    expect(provenanceBadgeMeta('simulado').label).toBe('Simulado')
    expect(provenanceBadgeMeta('real').label).toBe('Real')
    expect(provenanceBadgeMeta('estimado').label).toBe('Estimado')
    expect(provenanceBadgeMeta('conciliado').label).toBe('Conciliado')
  })
})

describe('seletores e CNPJ alfanumérico', () => {
  it('valida CPF e CNPJ alfanumérico de 14 posições', () => {
    expect(isValidSerproIdentity('CPF', '123.456.789-09')).toBe(true)
    expect(isValidSerproIdentity('CNPJ', '12.ABC.345/01DE-35')).toBe(true)
    expect(isValidSerproIdentity('CNPJ', '11222333000181')).toBe(true)
    expect(isValidSerproIdentity('CNPJ', '123')).toBe(false)
    expect(isValidSerproIdentity('CPF', '12ABC34501D')).toBe(false)
  })

  it('normaliza identidade e formata máscara alfanumérica', () => {
    expect(normalizeSerproIdentity('12.abc.345/01de-35')).toBe('12ABC34501DE35')
    expect(normalizeCnpj('12abc34501de35')).toBe('12ABC34501DE35')
    expect(formatCnpj('12ABC34501DE35')).toBe('12.ABC.345/01DE-35')
  })

  it('catálogo de serviço/poder não está vazio', () => {
    expect(SERPRO_SERVICE_OPTIONS.length).toBeGreaterThan(3)
    expect(SERPRO_POWER_OPTIONS.length).toBeGreaterThan(2)
    expect(SERPRO_SERVICE_OPTIONS.every(o => o.value && o.label)).toBe(true)
  })
})

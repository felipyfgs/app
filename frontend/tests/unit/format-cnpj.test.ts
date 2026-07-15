import { describe, expect, it } from 'vitest'
import { formatCnpj, nfseOperationalLabel, normalizeCnpj, statusLabel } from '../../app/utils/format'

describe('normalizeCnpj / formatCnpj', () => {
  it('normaliza removendo máscara e colocando maiúsculas', () => {
    expect(normalizeCnpj('11.222.333/0001-81')).toBe('11222333000181')
    expect(normalizeCnpj('12abc34501de35')).toBe('12ABC34501DE35')
  })

  it('formata CNPJ completo com máscara visual', () => {
    expect(formatCnpj('11222333000181')).toBe('11.222.333/0001-81')
    expect(formatCnpj('11.222.333/0001-81')).toBe('11.222.333/0001-81')
    expect(formatCnpj('12ABC34501DE35')).toBe('12.ABC.345/01DE-35')
  })

  it('não força máscara em valores incompletos', () => {
    expect(formatCnpj('11222333')).toBe('11222333')
    expect(formatCnpj('')).toBe('—')
    expect(formatCnpj(null)).toBe('—')
  })
})

describe('labels de status por contexto', () => {
  it('não trata credencial ACTIVE como situação fiscal', () => {
    expect(statusLabel('ACTIVE')).toBe('Ativa')
    expect(nfseOperationalLabel('ACTIVE')).toBe('Autorizada')
  })
})

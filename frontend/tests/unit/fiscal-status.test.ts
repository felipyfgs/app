import { describe, expect, it } from 'vitest'
import {
  coverageLabel,
  coverageMeta,
  dataOriginMeta,
  fiscalSituationFilterItems,
  fiscalStatusColor,
  fiscalStatusIcon,
  fiscalStatusLabel,
  fiscalStatusMeta,
  isNonPositiveFiscalSituation,
  normalizeFiscalSituation,
  resolveFiscalEmptyKind
} from '../../app/utils/fiscal-status'

describe('fiscal-status vocabulary (15.8)', () => {
  const codes = [
    'UP_TO_DATE',
    'PENDING',
    'PROCESSING',
    'ATTENTION',
    'ERROR',
    'NOT_APPLICABLE',
    'UNKNOWN',
    'UNSUPPORTED',
    'BLOCKED'
  ] as const

  it('normaliza e rotula todos os códigos do vocabulário', () => {
    for (const code of codes) {
      expect(normalizeFiscalSituation(code.toLowerCase())).toBe(code)
      const meta = fiscalStatusMeta(code)
      expect(meta.label.length).toBeGreaterThan(0)
      expect(meta.icon).toMatch(/^i-lucide-/)
      expect(meta.description.length).toBeGreaterThan(0)
      expect(fiscalStatusLabel(code)).toBe(meta.label)
      expect(fiscalStatusIcon(code)).toBe(meta.icon)
      expect(fiscalStatusColor(code)).toBe(meta.color)
    }
  })

  it('UNSUPPORTED e UNKNOWN expõem hint de origem (não só cor)', () => {
    expect(fiscalStatusMeta('UNSUPPORTED').sourceHint).toMatch(/API|oficial/i)
    expect(fiscalStatusMeta('UNKNOWN').sourceHint).toMatch(/evidência/i)
  })

  it('filtro inclui opção «todas» e todos os códigos', () => {
    const items = fiscalSituationFilterItems(true)
    expect(items[0]?.value).toBe('all')
    for (const code of codes) {
      expect(items.some(i => i.value === code)).toBe(true)
    }
  })

  it('coverageLabel distingue plena/parcial', () => {
    expect(coverageLabel('FULL')).toMatch(/plena/i)
    expect(coverageLabel('PARTIAL')).toMatch(/parcial/i)
    expect(coverageLabel('UNSUPPORTED')).toMatch(/sem cobertura/i)
  })

  it('fallback honesto para código desconhecido', () => {
    const meta = fiscalStatusMeta('SOMETHING_NEW')
    expect(meta.code).toBe('SOMETHING_NEW')
    expect(meta.color).toBe('neutral')
    // Label humanizado (não código cru UPPERCASE na UI)
    expect(meta.label).toBe('Something New')
  })

  it('aliases de pagamento/emissão em pt-BR', () => {
    expect(fiscalStatusLabel('CONFIRMED')).toBe('Confirmado')
    expect(fiscalStatusLabel('NOT_CONFIRMED')).toBe('Sem confirmação')
    expect(fiscalStatusLabel('TRANSMITTED')).toBe('Transmitido')
    expect(fiscalStatusLabel('REJECTED')).toBe('Rejeitado')
  })

  it('coverageMeta e dataOriginMeta (6.6 / 6.10)', () => {
    expect(coverageMeta('PARTIAL').label).toMatch(/parcial/i)
    expect(dataOriginMeta('DEMO').synthetic).toBe(true)
    expect(resolveFiscalEmptyKind({ situation: 'BLOCKED' })).toBe('blocked')
  })

  it('dataOriginMeta fail-closed para origem ausente', () => {
    expect(dataOriginMeta(null).label).toBe('Origem não informada')
    expect(dataOriginMeta('').label).toBe('Origem não informada')
    expect(dataOriginMeta(undefined).synthetic).toBe(false)
    expect(dataOriginMeta('LIVE').synthetic).toBe(false)
  })

  it('UNKNOWN/UNSUPPORTED/BLOCKED/ERROR nunca usam tom de sucesso', () => {
    for (const code of ['UNKNOWN', 'UNSUPPORTED', 'BLOCKED', 'ERROR'] as const) {
      expect(isNonPositiveFiscalSituation(code)).toBe(true)
      expect(fiscalStatusMeta(code).color).not.toBe('success')
      expect(fiscalStatusMeta(code).label).not.toMatch(/em dia|regular|sucesso|concluíd/i)
    }
  })

  it('resolveFiscalEmptyKind prioriza loading e erro sem previous (6.9)', () => {
    expect(resolveFiscalEmptyKind({ loading: true, error: 'x' })).toBe('loading')
    expect(resolveFiscalEmptyKind({ error: 'x', hasPrevious: false })).toBe('error')
    expect(resolveFiscalEmptyKind({ situation: 'unsupported' })).toBe('unsupported')
  })
})

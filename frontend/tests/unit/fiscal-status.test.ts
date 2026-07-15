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
  })

  it('coverageMeta e dataOriginMeta (6.6 / 6.10)', () => {
    expect(coverageMeta('PARTIAL').label).toMatch(/parcial/i)
    expect(dataOriginMeta('DEMO').synthetic).toBe(true)
    expect(resolveFiscalEmptyKind({ situation: 'BLOCKED' })).toBe('blocked')
  })

  it('resolveFiscalEmptyKind prioriza loading e erro sem previous (6.9)', () => {
    expect(resolveFiscalEmptyKind({ loading: true, error: 'x' })).toBe('loading')
    expect(resolveFiscalEmptyKind({ error: 'x', hasPrevious: false })).toBe('error')
    expect(resolveFiscalEmptyKind({ situation: 'unsupported' })).toBe('unsupported')
  })
})

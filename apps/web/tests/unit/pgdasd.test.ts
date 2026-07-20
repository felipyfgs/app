import { describe, expect, it } from 'vitest'
import { pgdasdDeclarationState } from '~/utils/pgdasd'

describe('pgdasd utils', () => {
  it('maps known declaration states', () => {
    expect(pgdasdDeclarationState('CURRENT')).toBe('CURRENT')
    expect(pgdasdDeclarationState('DUE_WITHIN_DEADLINE')).toBe('DUE_WITHIN_DEADLINE')
    expect(pgdasdDeclarationState('OVERDUE_NOT_FOUND')).toBe('OVERDUE_NOT_FOUND')
    expect(pgdasdDeclarationState('UNVERIFIED')).toBe('UNVERIFIED')
  })

  it('falls back to UNVERIFIED for unknown values', () => {
    expect(pgdasdDeclarationState('nope')).toBe('UNVERIFIED')
    expect(pgdasdDeclarationState(null)).toBe('UNVERIFIED')
    expect(pgdasdDeclarationState(undefined)).toBe('UNVERIFIED')
  })
})

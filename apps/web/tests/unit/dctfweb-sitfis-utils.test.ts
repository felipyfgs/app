import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'
import { dctfwebDeclarationMeta, dctfwebDeclarationState } from '~/utils/dctfweb'

describe('dctfweb / sitfis utils smoke', () => {
  it('maps dctfweb declaration states', () => {
    expect(dctfwebDeclarationState('CURRENT')).toBe('CURRENT')
    expect(dctfwebDeclarationState('weird')).toBe('UNVERIFIED')
    expect(dctfwebDeclarationMeta('CURRENT').color).toBe('success')
  })

  it('sitfis-table exports age/detail helpers', () => {
    const source = readFileSync(
      resolve(__dirname, '../../app/utils/sitfis-table.ts'),
      'utf8'
    )
    expect(source).toContain('export function sitfisAgeLabel')
    expect(source).toContain('export function sitfisDetailOf')
  })
})

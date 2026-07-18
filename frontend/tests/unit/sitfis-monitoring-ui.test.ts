import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

function readUtil(name: string) {
  return readFileSync(resolve(__dirname, `../../app/utils/${name}`), 'utf8')
}

function readPage(name: string) {
  return readFileSync(resolve(__dirname, `../../app/pages/monitoring/${name}`), 'utf8')
}

describe('renderer SITFIS', () => {
  it('materializa colunas no padrão Simples (domínio + ações; secundárias no fim)', () => {
    const source = readUtil('sitfis-table.ts')
    const ids = [...source.matchAll(/id: '([^']+)'/g)].map(match => match[1])
    expect(ids).toEqual([
      'client',
      'situation',
      'findings',
      'coverage',
      'actions',
      'procuracao',
      'franchise',
      'age',
      'observed'
    ])
    expect(source).toContain('sortHeader(\'Cliente\'')
    expect(source).toContain('sortHeader(\'Situação\'')
    expect(source).toContain('header: \'Achados\'')
    expect(source).toContain('header: \'Cobertura\'')
    expect(source).toContain('header: \'Ações\'')
    expect(source).toContain('header: \'Procuração\'')
    expect(source).toContain('header: \'Franquia / agenda\'')
    expect(source).toContain('header: \'Idade / TTL\'')
    expect(source).toContain('sortHeader(\'Observado\'')
    expect(source).toContain('fill: true')
    expect(source).toContain('tableIconGroup')
    expect(source).toContain('tableIconButton')
    expect(source).toContain('sitfis-actions-group')
    expect(source).toContain('documentActionVisible')
    expect(source).toContain('formatDate')
    expect(source).toContain('export function sitfisAgeLabel')
    expect(source).toContain('Math.floor(s / 3600)} h')
    expect(source).not.toContain('label: \'Achados\'')
    expect(source).not.toContain('w-56')
  })

  it('página oculta secundárias por padrão e usa o builder', () => {
    const page = readPage('sitfis.vue')
    expect(page).toContain('buildSitfisColumns')
    expect(page).toContain(":initial-hidden-columns=\"['procuracao', 'franchise', 'age', 'observed']\"")
    expect(page).toContain('min-w-[720px]')
    expect(page).toContain('MonitoringModuleTable')
  })
})

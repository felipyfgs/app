import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

function readUtil(name: string) {
  return readFileSync(resolve(__dirname, `../../app/utils/${name}`), 'utf8')
}

describe('table-icon-slots', () => {
  it('importa UI via @nuxt/ui/components (evita TDZ do barrel #components no HMR)', () => {
    const source = readUtil('table-icon-slots.ts')
    expect(source).toContain('@nuxt/ui/components/DropdownMenu.vue')
    expect(source).toContain('@nuxt/ui/components/Button.vue')
    expect(source).toContain('@nuxt/ui/components/Tooltip.vue')
    expect(source).not.toMatch(/from ['"]#components['"]/)
    expect(source).toContain('tableIconMenu')
    expect(source).toContain('UDropdownMenu')
  })

  it('builders PGDAS/PGMEI/DCTF/SITFIS usam UI estável fora do barrel #components', () => {
    for (const file of ['pgdasd-table.ts', 'pgmei-table.ts', 'dctfweb-table.ts', 'sitfis-table.ts', 'table-sort.ts']) {
      const source = readUtil(file)
      expect(source, file).not.toMatch(/UBadge,\s*\n\s*UButton/)
      expect(source, file).toMatch(/@nuxt\/ui\/components\//)
    }
  })
})

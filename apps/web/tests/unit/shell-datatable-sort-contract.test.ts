import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

const root = (...parts: string[]) => resolve(process.cwd(), ...parts)
const read = (...parts: string[]) => readFileSync(root(...parts), 'utf8')

describe('shell-datatable-sort-contract', () => {
  it('guides: sortHeader só em colunas com map API; sem sort por id', () => {
    const source = read('app/pages/monitoring/guides.vue')
    expect(source).toContain('GUIDE_SORT_COLUMN_TO_API')
    expect(source).toContain('client: \'client_id\'')
    expect(source).toContain('competence: \'competence\'')
    expect(source).toContain('due: \'due_at\'')
    expect(source).toContain('resolveGuideSortApi')
    expect(source).toContain('sort: resolveGuideSortApi')
    expect(source).toContain('direction: sort?.desc')
    expect(source).toContain('sortHeader(\'Cliente\'')
    expect(source).toContain('sortHeader(\'Competência\'')
    expect(source).toContain('sortHeader(\'Vencimento\'')
    expect(source).not.toContain('sortHeader(\'ID\'')
    expect(source).toMatch(/id:\s*'id'[\s\S]*?enableSorting:\s*false/)
    expect(source).toContain('watch(sorting')
    expect(source).toContain('syncGuidesUrl')
    expect(source).toContain('Object.keys(route.query).length > 0')
    expect(source).not.toMatch(/router\.replace\(\{\s*path:\s*route\.path,\s*query/)
  })

  it('registrations e tax-processes: sem sortHeader fantasma', () => {
    for (const rel of [
      'app/pages/monitoring/registrations.vue',
      'app/pages/monitoring/tax-processes.vue'
    ]) {
      const source = read(rel)
      expect(source, rel).not.toContain('sortHeader')
      expect(source, rel).not.toContain('from \'~/utils/table-sort\'')
      expect(source, rel).toMatch(/header:\s*'Cliente'/)
      expect(source, rel).toContain('enableSorting: false')
      // ModuleTable exige :sorting, mas colunas ficam com enableSorting: false (sem chrome fantasma).
      expect(source, rel).toContain(':sorting=')
    }
  })

  it('clientes: whitelist de sort sem cnpj', () => {
    const source = read('app/components/clients/ClientCatalogList.vue')
    expect(source).not.toMatch(/sort\?\.id === 'cnpj'/)
    expect(source).toMatch(/sort\?\.id === 'is_active' \|\| sort\?\.id === 'tax_regime'/)
  })

  it('ByClient: sync URL de sort + empty no slot', () => {
    const source = read('app/components/docs/ByClient.vue')
    expect(source).toContain('syncByClientSortUrl')
    expect(source).toContain('hydrateByClientSortingFromQuery')
    expect(source).toContain('sort_direction')
    expect(source).toContain(':manual-sorting="true"')
    expect(source).toContain('#empty')
    expect(source).not.toMatch(/ShellDataTable[\s\S]*v-if="loading \|\| rows\.length"/)
  })

  it('listas N1: empty no #empty do ShellDataTable', () => {
    for (const rel of [
      'app/pages/syncs.vue',
      'app/pages/health.vue',
      'app/pages/admin/serpro/contracts.vue',
      'app/components/docs/Catalog.vue'
    ]) {
      const source = read(rel)
      expect(source, rel).toContain('#empty')
      expect(source, rel).toContain('ShellDataTable')
    }
  })
})

import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

/** Superfícies migradas nesta change — lista principal sem `<UTable`. */
const MIGRATED = [
  'app/pages/exports.vue',
  'app/pages/closing.vue',
  'app/pages/docs/imports/index.vue',
  'app/pages/docs/imports/[id].vue',
  'app/pages/work/processes/index.vue',
  'app/pages/work/templates/index.vue',
  'app/pages/admin/offices/index.vue',
  'app/pages/admin/serpro/catalog.vue',
  'app/pages/admin/serpro/contracts.vue',
  'app/pages/admin/serpro/usage.vue',
  'app/pages/settings/usage.vue',
  'app/pages/syncs.vue',
  'app/pages/health.vue',
  'app/pages/monitoring/clients/[clientId].vue',
  'app/components/clients/ClientCatalogList.vue',
  'app/components/clients/ClientListDashboard.vue',
  'app/components/docs/Catalog.vue',
  'app/components/docs/ByClient.vue',
  'app/components/docs/Detail.vue',
  'app/components/monitoring/ModuleDataTable.vue'
] as const

describe('shell-list-migration-gate', () => {
  it('superfícies migradas usam ShellDataTable e não montam UTable na lista', () => {
    for (const rel of MIGRATED) {
      const source = readFileSync(resolve(process.cwd(), rel), 'utf8')
      expect(source, rel).toContain('ShellDataTable')
      expect(source, rel).not.toMatch(/<UTable[\s>]/)
    }
  })
})

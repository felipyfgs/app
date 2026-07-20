import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

const read = (path: string) => readFileSync(resolve(process.cwd(), path), 'utf8')

describe('simples-mei consulta rápida', () => {
  it('toolbar PGDAS-D expõe Consultar em lote', () => {
    const src = read('app/components/monitoring/pgdasd/SelectionActions.vue')
    expect(src).toContain('pgdasd-bulk-consult')
    expect(src).toContain('label="Consultar"')
    expect(src).toContain('enqueueReadUpdate')
  })

  it('colunas expõem atalho de consulta por linha', () => {
    expect(read('app/utils/pgdasd-table.ts')).toContain('pgdasd-row-consult')
    expect(read('app/utils/pgmei-table.ts')).toContain('pgmei-row-consult')
  })

  it('página wire canConsult e confirmação unificada', () => {
    const page = read('app/pages/monitoring/simples-mei/index.vue')
    expect(page).toContain('canConsult: canTriggerSync.value')
    expect(page).toContain('confirmRowConsult')
    expect(page).toContain(':can-consult="canTriggerSync"')
  })
})

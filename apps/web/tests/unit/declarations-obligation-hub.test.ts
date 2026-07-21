import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import {
  DECLARATIONS_TABS,
  declarationsSurfaceTitle,
  normalizeDeclarationsSubmodule
} from '../../app/types/fiscal-modules'

describe('declarations-obligation-hub', () => {
  it('normaliza submodule com default PGDAS e aliases', () => {
    expect(normalizeDeclarationsSubmodule(undefined)).toBe('PGDAS')
    expect(normalizeDeclarationsSubmodule('')).toBe('PGDAS')
    expect(normalizeDeclarationsSubmodule('pgdasd')).toBe('PGDAS')
    expect(normalizeDeclarationsSubmodule('PGDAS_D')).toBe('PGDAS')
    expect(normalizeDeclarationsSubmodule('dctf')).toBe('DCTFWEB')
    expect(normalizeDeclarationsSubmodule('DEFIS')).toBe('DEFIS')
    expect(normalizeDeclarationsSubmodule('dirf')).toBe('DIRF')
  })

  it('expõe as cinco abas na ordem da referência', () => {
    expect(DECLARATIONS_TABS.map(t => t.value)).toEqual([
      'PGDAS',
      'DCTFWEB',
      'FGTS',
      'DEFIS',
      'DIRF'
    ])
  })

  it('monta título dinâmico da superfície', () => {
    expect(declarationsSurfaceTitle('PGDAS')).toBe('PGDAS - Declarações')
    expect(declarationsSurfaceTitle('DCTFWEB')).toBe('DCTFWeb - Declarações')
    expect(declarationsSurfaceTitle('DIRF')).toBe('DIRF - Declarações')
  })

  it('página declarações usa tabs locais, default PGDAS e colunas/histórico PGDAS', () => {
    const page = readFileSync(
      resolve(process.cwd(), 'app/pages/monitoring/declarations.vue'),
      'utf8'
    )
    expect(page).toContain('declarations-submodule-tabs')
    expect(page).toContain('normalizeDeclarationsSubmodule(\'PGDAS\')')
    expect(page).toContain('useFiscalModulePortfolio(\'declarations\'')
    expect(page).toContain('ShellScrollableTabs')
    expect(page).toContain('MonitoringPgdasdDasHistoryModal')
    expect(page).toContain('MonitoringDctfwebHistoryModal')
    expect(page).toContain('MonitoringDefisDeclarationsModal')
    expect(page).toContain('declarations-dirf-unsupported')
    expect(page).not.toMatch(/navigateTo\([^)]*declarations\//)
  })

  it('colunas PGDAS cobrem Situação / Últ. Declaração / Cliente / Ações · Consulta, sem Histórico na grade', () => {
    const source = readFileSync(
      resolve(process.cwd(), 'app/utils/declarations-table.ts'),
      'utf8'
    )
    const [pgdasSection] = source.split('/** Colunas genéricas filtradas por obrigação')
    expect(source).toContain('Situação da declaração')
    expect(source).toContain('Últ. Declaração')
    expect(pgdasSection).toContain('MONITORING_ACTIONS_LABEL')
    expect(pgdasSection).toContain('MONITORING_CONSULTED_LABEL')
    expect(pgdasSection).not.toMatch(/id:\s*'history'/)
    expect(pgdasSection).not.toContain('Última Busca')
    expect(pgdasSection).toContain('Histórico de busca')
    expect(pgdasSection).toContain('declarations-pgdas-row-actions')
  })

  it('modal DAS mantém histórico próprio sem abrir modal aninhado de declarações', () => {
    const das = readFileSync(
      resolve(process.cwd(), 'app/components/monitoring/PgdasdDasHistoryModal.vue'),
      'utf8'
    )
    expect(das).toContain('DAS Simples Nacional - Histórico')
    expect(das).toContain('MAEDs não são enviadas automaticamente')
    expect(das).toContain('Ano da busca')
    expect(das).toContain('Baixar DAS')
    expect(das).toContain('fetchHistory')
    expect(das).not.toContain('MonitoringPgdasdDeclarationsHistoryModal')
    expect(das).not.toContain('pgdasd-das-open-declarations')
  })
})

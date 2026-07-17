import { readFileSync, readdirSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'
import {
  monitoringCanonicalQuery,
  monitoringLegacySubmoduleLocation,
  monitoringModuleBasePath,
  monitoringSubmoduleLocation,
  monitoringSubmodulePath,
  normalizeMonitoringSubmodule
} from '../../app/utils/monitoring-nav'

describe('monitoring submodule routes', () => {
  it('path canônico = item da sidebar; tabs não entram na URL', () => {
    expect(monitoringModuleBasePath('simples_mei')).toBe('/monitoring/simples-mei')
    expect(monitoringModuleBasePath('dctfweb')).toBe('/monitoring/dctfweb')
    expect(monitoringSubmoduleLocation('simples_mei', 'PGDASD')).toEqual({
      path: '/monitoring/simples-mei',
      query: {}
    })
    // Mesmo com tab não-default, path permanece o do módulo
    expect(monitoringSubmoduleLocation('simples_mei', 'DASN_SIMEI')).toEqual({
      path: '/monitoring/simples-mei',
      query: {}
    })
    expect(monitoringSubmoduleLocation('dctfweb', 'MIT')).toEqual({
      path: '/monitoring/dctfweb',
      query: {}
    })
    expect(monitoringSubmodulePath('simples_mei', 'PGMEI')).toBe('/monitoring/simples-mei')
    expect(normalizeMonitoringSubmodule('dctfweb', 'dctfweb')).toBe('DCTFWEB')
  })

  it('default local; desconhecido cai no default (estado de tab, não URL)', () => {
    expect(normalizeMonitoringSubmodule('simples_mei', undefined)).toBe('PGDASD')
    expect(monitoringSubmodulePath('dctfweb', 'desconhecido')).toBe('/monitoring/dctfweb')
  })

  it('legado /modulo/:slug redireciona para path limpo da sidebar', () => {
    expect(monitoringLegacySubmoduleLocation('simples_mei', {
      situation: 'PENDING',
      q: 'Empresa'
    }, 'pgmei')).toEqual({
      path: '/monitoring/simples-mei',
      query: {}
    })
  })

  it('nunca propaga office_id, tab ou filtros na query', () => {
    expect(monitoringLegacySubmoduleLocation('dctfweb', {
      tab: ['MIT'],
      office_id: '99',
      competence: '2026-07'
    })).toEqual({
      path: '/monitoring/dctfweb',
      query: {}
    })
    expect(monitoringCanonicalQuery({ office_id: '1', tab: 'MIT', sort: 'client' }))
      .toEqual({})
  })

  it('páginas de monitoring não usam query de filtro nem de tab na URL', () => {
    const root = resolve(__dirname, '../../app/pages/monitoring')
    const files = readdirSync(root, { recursive: true, withFileTypes: true })
      .filter(entry => entry.isFile() && entry.name.endsWith('.vue'))
      .map(entry => readFileSync(resolve(entry.parentPath, entry.name), 'utf8'))
      .join('\n')

    for (const query of [
      '?tab=',
      '?situation=',
      '?client_id=',
      '?competence=',
      '?q=',
      '?submodule=',
      'query.submodule'
    ]) {
      expect(files).not.toContain(query)
    }
  })
})

import { readFileSync, readdirSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'
import {
  monitoringCanonicalQuery,
  monitoringLegacySubmoduleLocation,
  monitoringSubmodulePath,
  normalizeMonitoringSubmodule
} from '../../app/utils/monitoring-nav'

describe('monitoring submodule routes', () => {
  it('converte códigos e slugs para paths canônicos', () => {
    expect(monitoringSubmodulePath('simples_mei', 'PGDASD')).toBe('/monitoring/simples-mei/pgdasd')
    expect(monitoringSubmodulePath('simples_mei', 'DASN_SIMEI')).toBe('/monitoring/simples-mei/dasn-simei')
    expect(monitoringSubmodulePath('dctfweb', 'MIT')).toBe('/monitoring/dctfweb/mit')
    expect(normalizeMonitoringSubmodule('dctfweb', 'dctfweb')).toBe('DCTFWEB')
  })

  it('usa o submódulo padrão para valores ausentes ou desconhecidos', () => {
    expect(normalizeMonitoringSubmodule('simples_mei', undefined)).toBe('PGDASD')
    expect(monitoringSubmodulePath('dctfweb', 'desconhecido')).toBe('/monitoring/dctfweb/dctfweb')
  })

  it('migra deep-link legado e descarta filtros efêmeros', () => {
    expect(monitoringLegacySubmoduleLocation('simples_mei', {
      submodule: 'PGMEI',
      situation: 'PENDING',
      q: 'Empresa'
    })).toEqual({
      path: '/monitoring/simples-mei/pgmei',
      query: {}
    })
  })

  it('aceita tab legado e nunca propaga office_id', () => {
    expect(monitoringLegacySubmoduleLocation('dctfweb', {
      tab: ['MIT'],
      office_id: '99',
      competence: '2026-07'
    })).toEqual({
      path: '/monitoring/dctfweb/mit',
      query: {}
    })
    expect(monitoringCanonicalQuery({ office_id: '1', tab: 'MIT', sort: 'client' }))
      .toEqual({})
  })

  it('não cria links de monitoramento com filtros ou tabs na query string', () => {
    const root = resolve(__dirname, '../../app/pages/monitoring')
    const files = readdirSync(root, { recursive: true, withFileTypes: true })
      .filter(entry => entry.isFile() && entry.name.endsWith('.vue'))
      .map(entry => readFileSync(resolve(entry.parentPath, entry.name), 'utf8'))
      .join('\n')

    for (const query of ['?tab=', '?situation=', '?client_id=', '?competence=', '?q=']) {
      expect(files).not.toContain(query)
    }
  })
})

import { existsSync, readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

const read = (path: string) => readFileSync(resolve(process.cwd(), path), 'utf8')

describe('comunicação informativa do monitor fiscal', () => {
  it('preserva preferências e histórico sem controle de envio ou automação', () => {
    const modal = read('app/components/monitoring/PgdasdCommunicationModals.vue')

    expect(modal).toContain('displayedPreference')
    expect(modal).toContain('loadTracking')
    expect(modal).toContain('Histórico local de comunicação')
    expect(modal).toContain('Nenhum provider de e-mail ou WhatsApp')
    expect(modal).not.toContain('<USwitch')
    expect(modal).not.toContain('Enviar agora')
    expect(modal).not.toContain('savePreferences')
    expect(modal).not.toContain('updatePreferences')
    expect(modal).not.toContain('canManage')
  })

  it('usa o mesmo contrato somente leitura em PGDAS-D, PGMEI e DCTFWeb', () => {
    const simples = read('app/pages/monitoring/simples-mei/index.vue')
    const dctfweb = read('app/pages/monitoring/dctfweb/index.vue')

    expect(simples.match(/<MonitoringPgdasdCommunicationModals/gu)).toHaveLength(2)
    expect(simples).toContain('context="PGMEI"')
    expect(dctfweb).toContain('context="DCTFWEB"')
    expect(dctfweb).toContain('<MonitoringPgdasdCommunicationModals')
  })

  it('remove switches individuais e em lote das tabelas e toolbars', () => {
    const sources = [
      read('app/utils/pgdasd-table.ts'),
      read('app/utils/pgmei-table.ts'),
      read('app/utils/dctfweb-table.ts'),
      read('app/components/monitoring/pgmei/BulkActions.vue')
    ]

    for (const source of sources) {
      expect(source).not.toContain('AutomaticSwitch')
      expect(source).not.toContain('BulkAutomaticSwitch')
      expect(source).not.toContain('updateBulk')
      expect(source).not.toContain('batchAutomatic')
      expect(source).not.toContain('Ligar automático')
    }

    for (const removed of [
      'app/components/monitoring/pgdasd/AutomaticSwitch.vue',
      'app/components/monitoring/pgdasd/BulkAutomaticActions.vue',
      'app/components/monitoring/pgmei/AutomaticSwitch.vue',
      'app/components/monitoring/pgmei/BulkAutomaticActions.vue',
      'app/components/monitoring/PgmeiCommunicationModals.vue'
    ]) {
      expect(existsSync(resolve(process.cwd(), removed)), removed).toBe(false)
    }
  })
})

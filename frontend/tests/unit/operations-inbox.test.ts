import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

const APP_ROOT = resolve(__dirname, '../../app')

describe('operations inbox surface', () => {
  // Superfície estática: fachada + módulo operations (runtime em api-modules.test.ts).
  const facade = readFileSync(resolve(APP_ROOT, 'composables/useApi.ts'), 'utf8')
  const operationsModule = readFileSync(resolve(APP_ROOT, 'composables/api/createOperationsApi.ts'), 'utf8')
  const api = [facade, operationsModule].join('\n')
  const types = readFileSync(resolve(APP_ROOT, 'types/api.ts'), 'utf8')
  const health = readFileSync(resolve(APP_ROOT, 'pages/health.vue'), 'utf8')
  const notifications = readFileSync(resolve(APP_ROOT, 'components/NotificationsSlideover.vue'), 'utf8')

  it('expõe client tipado de inbox e summary com backup', () => {
    expect(api).toContain(`'/api/v1/operations/inbox'`)
    expect(api).toContain('inbox:')
    expect(facade).toContain('createOperationsApi')
    expect(facade).toContain('operations: operationsApi.operations')
    expect(types).toContain('export interface InboxItem')
    expect(types).toContain('export interface BackupStatus')
    expect(types).toContain('inbox_total')
  })

  it('lista /health mantém filtros e empty state positivo (URL sync)', () => {
    expect(health).toContain('const severityFilter = ref')
    expect(health).toContain('const typeFilter = ref')
    expect(health).toContain('syncHealthUrl')
    expect(health).toContain('Nenhum problema operacional')
    expect(health).not.toMatch(/selecionar todos|bulk select|label="Restaurar"/i)
    expect(health).toMatch(/Não há restore|Restore de backup fora do painel/)
  })

  it('slideover prefere inbox com fallback sanitizado', () => {
    expect(notifications).toContain('api.operations.inbox')
    expect(notifications).toContain('loadFallback')
    expect(notifications).toContain('Inbox indisponível')
  })
})

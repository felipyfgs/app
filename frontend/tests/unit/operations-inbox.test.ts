import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

const APP_ROOT = resolve(__dirname, '../../app')

describe('operations inbox surface', () => {
  const api = readFileSync(resolve(APP_ROOT, 'composables/useApi.ts'), 'utf8')
  const types = readFileSync(resolve(APP_ROOT, 'types/api.ts'), 'utf8')
  const health = readFileSync(resolve(APP_ROOT, 'pages/health/index.vue'), 'utf8')
  const notifications = readFileSync(resolve(APP_ROOT, 'components/NotificationsSlideover.vue'), 'utf8')
  const admin = readFileSync(resolve(APP_ROOT, 'pages/admin/index.vue'), 'utf8')

  it('expõe client tipado de inbox e summary com backup', () => {
    expect(api).toContain(`'/api/v1/operations/inbox'`)
    expect(api).toContain('inbox:')
    expect(types).toContain('export interface InboxItem')
    expect(types).toContain('export interface BackupStatus')
    expect(types).toContain('inbox_total')
  })

  it('lista /health mantém filtros locais e empty state positivo', () => {
    expect(health).toContain('const severityFilter = ref')
    expect(health).toContain('const typeFilter = ref')
    expect(health).not.toContain('router.replace({ query:')
    expect(health).toContain('Nenhum problema operacional')
    expect(health).not.toMatch(/selecionar todos|bulk select|label="Restaurar"/i)
    expect(health).toContain('Não há restore')
  })

  it('slideover prefere inbox com fallback sanitizado', () => {
    expect(notifications).toContain('api.operations.inbox')
    expect(notifications).toContain('loadFallback')
    expect(notifications).toContain('Inbox indisponível')
  })

  it('admin tem card somente leitura de backup sem botão de restore', () => {
    expect(admin).toContain('admin-backup-card')
    expect(admin).toContain('Último SUCCESS')
    expect(admin).toContain('restore drill')
    expect(admin).not.toMatch(/@click=.*restore|label="Restaurar"|to=".*restore/i)
  })
})

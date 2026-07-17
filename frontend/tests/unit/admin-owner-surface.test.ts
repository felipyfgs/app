import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

/**
 * Contrato da superfície singular do Proprietário:
 * sem tabela, sem botão de novo administrador, path /admin/owner.
 */
describe('admin owner surface contract', () => {
  const page = readFileSync(
    resolve(__dirname, '../../app/pages/admin/owner/index.vue'),
    'utf8'
  )
  const nav = readFileSync(
    resolve(__dirname, '../../app/utils/navigation.ts'),
    'utf8'
  )
  const api = readFileSync(
    resolve(__dirname, '../../app/composables/api/createPlatformApi.ts'),
    'utf8'
  )

  it('página singular sem UI de criação/listagem plural', () => {
    expect(page).toContain('data-testid="admin-owner-panel"')
    expect(page).toContain('title="Proprietário"')
    expect(page).toContain('admin-owner-save')
    expect(page).not.toContain('Novo administrador')
    expect(page).not.toContain('admin-admins')
    expect(page).not.toContain('UTable')
    expect(page).not.toContain('activation/regenerate')
  })

  it('navegação aponta para /admin/owner rotulado Proprietário', () => {
    expect(nav).toContain('id: \'platform-owner\'')
    expect(nav).toContain('label: \'Proprietário\'')
    expect(nav).toContain('to: \'/admin/owner\'')
    expect(nav).not.toContain('to: \'/admin/admins\'')
    expect(nav).not.toContain('label: \'Administradores\'')
  })

  it('API composable expõe owner singular e remove admins', () => {
    expect(api).toContain('\'/api/v1/platform/owner\'')
    expect(api).toContain('owner: {')
    expect(api).not.toContain('/api/v1/platform/admins')
    expect(api).not.toMatch(/\badmins:\s*\{/)
  })
})

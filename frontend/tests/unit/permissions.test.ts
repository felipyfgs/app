import { describe, expect, it } from 'vitest'
import {
  canCreateExport,
  canManageClients,
  canManageCredentials,
  canTriggerSync,
  hasConfirmedAdminAccess,
  unwrapMeUser
} from '../../app/utils/permissions'
import type { MeUser } from '../../app/types/api'

function user(partial: Partial<MeUser> & Pick<MeUser, 'role'>): MeUser {
  return {
    id: 1,
    name: 'Teste',
    email: 't@example.com',
    two_factor_confirmed: true,
    two_factor_required: true,
    requires_two_factor_setup: false,
    office: { id: 1, name: 'Escritório', slug: 'escritorio' },
    ...partial
  }
}

describe('permissions', () => {
  it('unwrapMeUser aceita envelope e objeto plano', () => {
    const me = user({ role: 'VIEWER' })
    expect(unwrapMeUser({ data: me })).toEqual(me)
    expect(unwrapMeUser(me)).toEqual(me)
    expect(unwrapMeUser(null)).toBeNull()
  })

  it('ADMIN sem 2FA confirmado não acessa administração nem mutações sensíveis', () => {
    const adminPending = user({
      role: 'ADMIN',
      two_factor_confirmed: false,
      two_factor_required: true
    })
    expect(hasConfirmedAdminAccess(adminPending)).toBe(false)
    expect(canManageCredentials(adminPending)).toBe(false)
    expect(canManageClients(adminPending)).toBe(false)
    expect(canTriggerSync(adminPending)).toBe(false)
    expect(canCreateExport(adminPending)).toBe(false)
  })

  it('ADMIN com 2FA confirmado tem acesso total', () => {
    const admin = user({ role: 'ADMIN' })
    expect(hasConfirmedAdminAccess(admin)).toBe(true)
    expect(canManageCredentials(admin)).toBe(true)
    expect(canManageClients(admin)).toBe(true)
    expect(canTriggerSync(admin)).toBe(true)
    expect(canCreateExport(admin)).toBe(true)
  })

  it('OPERATOR muta clientes/export/sync mas não gerencia A1', () => {
    const operator = user({ role: 'OPERATOR' })
    expect(hasConfirmedAdminAccess(operator)).toBe(false)
    expect(canManageCredentials(operator)).toBe(false)
    expect(canManageClients(operator)).toBe(true)
    expect(canTriggerSync(operator)).toBe(true)
    expect(canCreateExport(operator)).toBe(true)
  })

  it('VIEWER é somente leitura', () => {
    const viewer = user({ role: 'VIEWER' })
    expect(canManageClients(viewer)).toBe(false)
    expect(canManageCredentials(viewer)).toBe(false)
    expect(canTriggerSync(viewer)).toBe(false)
    expect(canCreateExport(viewer)).toBe(false)
    expect(hasConfirmedAdminAccess(viewer)).toBe(false)
  })
})

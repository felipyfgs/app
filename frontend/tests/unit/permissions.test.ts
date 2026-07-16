import { describe, expect, it } from 'vitest'
import {
  canAccessOfficeSettings,
  canAccessPlatformAdmin,
  canAccessPlatformSerproConsole,
  canCreateExport,
  canManageClients,
  canManageCredentials,
  canTriggerSync,
  hasConfirmedAdminAccess,
  isPlatformAdmin,
  isPlatformPrivilegedContext,
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

  it('ADMIN tem acesso administrativo sem gate de 2FA (TOTP descontinuado)', () => {
    const adminPending = user({
      role: 'ADMIN',
      two_factor_confirmed: false,
      two_factor_required: true
    })
    // TOTP não é mais gate de UI; senha recente é server-side.
    expect(hasConfirmedAdminAccess(adminPending)).toBe(true)
    expect(canManageCredentials(adminPending)).toBe(true)
    expect(canManageClients(adminPending)).toBe(true)
    expect(canTriggerSync(adminPending)).toBe(true)
    expect(canCreateExport(adminPending)).toBe(true)
  })

  it('ADMIN tem acesso total de escritório', () => {
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

  it('PLATFORM_ADMIN acessa console/admin sem membership e sem TOTP global', () => {
    const plat = user({
      role: null,
      office: null,
      is_platform_admin: true,
      two_factor_confirmed: false,
      two_factor_required: true
    })
    expect(isPlatformAdmin(plat)).toBe(true)
    expect(canAccessPlatformAdmin(plat)).toBe(true)
    expect(canAccessPlatformSerproConsole(plat)).toBe(true)
    expect(hasConfirmedAdminAccess(plat)).toBe(false)
    expect(canAccessOfficeSettings(plat)).toBe(false)
  })

  it('PLATFORM_ADMIN privilegiado acessa settings do office', () => {
    const plat = user({
      role: null,
      is_platform_admin: true,
      access_mode: 'platform_privileged',
      office: { id: 2, name: 'Tenant', slug: 'tenant' }
    })
    expect(isPlatformPrivilegedContext(plat)).toBe(true)
    expect(canAccessOfficeSettings(plat)).toBe(true)
  })

  it('ADMIN de office sem flag platform não acessa console global', () => {
    const admin = user({ role: 'ADMIN', is_platform_admin: false })
    expect(canAccessPlatformSerproConsole(admin)).toBe(false)
    expect(canAccessPlatformAdmin(admin)).toBe(false)
  })
})

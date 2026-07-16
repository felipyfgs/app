import { describe, expect, it } from 'vitest'
import type { MeUser } from '~/types/api'
import {
  homeForIdentity,
  isPlatformAdminPath,
  lacksOfficeContext,
  requiresPlatformAdminHome,
  safeRedirectForIdentity,
  safeRedirectTarget
} from '~/utils/auth-redirect'
import { extractActivationTokenFromHash } from '~/utils/activation'
import { isAuthPublicPath } from '~/utils/auth-public'
import { mainDestinations, quickActions } from '~/utils/navigation'

function platformUser(overrides: Partial<MeUser> = {}): MeUser {
  return {
    id: 1,
    name: 'Admin',
    email: 'admin@example.com',
    two_factor_confirmed: false,
    two_factor_required: false,
    requires_two_factor_setup: false,
    is_platform_admin: true,
    context_status: 'office_context_required',
    office: null,
    role: null,
    ...overrides
  }
}

describe('auth redirect policy', () => {
  it('aceita path interno relativo', () => {
    expect(safeRedirectTarget('/clients')).toBe('/clients')
    expect(safeRedirectTarget('/work?tab=open')).toBe('/work?tab=open')
  })

  it('rejeita open redirect e protocolos externos', () => {
    expect(safeRedirectTarget('https://evil.example')).toBeNull()
    expect(safeRedirectTarget('//evil.example')).toBeNull()
    expect(safeRedirectTarget('javascript:alert(1)')).toBeNull()
  })

  it('rejeita loops de auth e onboarding', () => {
    expect(safeRedirectTarget('/login')).toBeNull()
    expect(safeRedirectTarget('/two-factor-challenge')).toBeNull()
    expect(safeRedirectTarget('/two-factor/setup')).toBeNull()
    expect(safeRedirectTarget('/onboarding')).toBeNull()
    expect(safeRedirectTarget('/activate')).toBeNull()
  })

  it('home prioriza PLATFORM_ADMIN', () => {
    expect(homeForIdentity(platformUser())).toBe('/admin')
    expect(homeForIdentity({ ...platformUser(), role: 'OPERATOR', is_platform_admin: false, context_status: 'ok', office: { id: 1, name: 'O', slug: 'o' } })).toBe('/work')
    expect(homeForIdentity({ ...platformUser(), is_platform_admin: false, role: 'ADMIN', context_status: 'ok', office: { id: 1, name: 'O', slug: 'o' } })).toBe('/')
  })

  it('sem Office aceita somente redirects globais de /admin', () => {
    const user = platformUser()
    expect(safeRedirectForIdentity('/clients', user)).toBeNull()
    expect(safeRedirectForIdentity('/work?tab=open', user)).toBeNull()
    expect(safeRedirectForIdentity('/admin/offices/new', user)).toBe('/admin/offices/new')
    expect(safeRedirectForIdentity('/admin?tab=offices', user)).toBe('/admin?tab=offices')
  })

  it('com Office preserva redirect tenant do PLATFORM_ADMIN', () => {
    const user = platformUser({
      context_status: 'ok',
      office: { id: 1, name: 'Office', slug: 'office' },
      role: 'ADMIN'
    })
    expect(safeRedirectForIdentity('/clients', user)).toBe('/clients')
  })
})

describe('onboarding fragment token', () => {
  it('extrai token forte do hash e ignora curtos', () => {
    const token = 'a'.repeat(32)
    expect(extractActivationTokenFromHash(`#token=${token}`)).toBe(token)
    expect(extractActivationTokenFromHash('#token=curto')).toBeNull()
  })

  it('/onboarding é rota pública', () => {
    expect(isAuthPublicPath('/onboarding')).toBe(true)
  })
})

describe('navegação sem office', () => {
  it('oculta destinos tenant e quick actions', () => {
    const user = platformUser()
    expect(lacksOfficeContext(user)).toBe(true)
    const ids = mainDestinations(user).map(d => d.id)
    expect(ids).toEqual(['platform-admin'])
    expect(ids).not.toContain('home')
    expect(ids).not.toContain('clients')
    expect(quickActions(user)).toEqual([])
  })

  it('redireciona acesso direto tenant e preserva rotas globais', () => {
    const user = platformUser()
    expect(isPlatformAdminPath('/admin')).toBe(true)
    expect(isPlatformAdminPath('/admin/offices/new')).toBe(true)
    expect(requiresPlatformAdminHome(user, '/clients')).toBe(true)
    expect(requiresPlatformAdminHome(user, '/settings')).toBe(true)
    expect(requiresPlatformAdminHome(user, '/admin/offices/new')).toBe(false)
  })
})

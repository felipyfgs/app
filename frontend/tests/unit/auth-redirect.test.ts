import { describe, expect, it } from 'vitest'

/**
 * Espelha a política de redirect pós-login (login.vue) sem montar Nuxt.
 * Garante ausência de open redirect e bloqueio de rotas de auth.
 */
function safeRedirectTarget(raw: unknown): string | null {
  const value = Array.isArray(raw) ? raw[0] : raw
  if (typeof value !== 'string' || !value.startsWith('/') || value.startsWith('//')) {
    return null
  }
  if (value.startsWith('/login') || value.startsWith('/two-factor')) {
    return null
  }
  return value
}

function homeForRole(role?: string | null): string {
  if (role === 'OPERATOR') return '/work'
  return '/'
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

  it('rejeita loops de auth', () => {
    expect(safeRedirectTarget('/login')).toBeNull()
    expect(safeRedirectTarget('/two-factor-challenge')).toBeNull()
    expect(safeRedirectTarget('/two-factor/setup')).toBeNull()
  })

  it('home por papel', () => {
    expect(homeForRole('OPERATOR')).toBe('/work')
    expect(homeForRole('ADMIN')).toBe('/')
    expect(homeForRole('VIEWER')).toBe('/')
  })
})

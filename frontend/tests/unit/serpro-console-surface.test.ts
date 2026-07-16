import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { createPlatformApi } from '../../app/composables/api/createPlatformApi'
import { canAccessPlatformSerproConsole, isPlatformAdmin } from '../../app/utils/permissions'
import type { MeUser } from '../../app/types/api'

const APP = resolve(__dirname, '../../app')

function user(partial: Partial<MeUser> = {}): MeUser {
  return {
    id: 1,
    name: 'Plat',
    email: 'p@example.com',
    two_factor_confirmed: true,
    two_factor_required: true,
    requires_two_factor_setup: false,
    is_platform_admin: false,
    office: null,
    role: null,
    ...partial
  }
}

describe('console global SERPRO (superfície)', () => {
  it('gates PLATFORM_ADMIN + TOTP', () => {
    expect(isPlatformAdmin(user({ is_platform_admin: true }))).toBe(true)
    expect(canAccessPlatformSerproConsole(user({ is_platform_admin: true }))).toBe(true)
    expect(canAccessPlatformSerproConsole(user({
      is_platform_admin: true,
      two_factor_confirmed: false
    }))).toBe(false)
    expect(canAccessPlatformSerproConsole(user({ is_platform_admin: false, role: 'ADMIN' }))).toBe(false)
  })

  it('API platform aponta para rotas canônicas sanitizadas', async () => {
    const calls: string[] = []
    const client = async (path: string) => {
      calls.push(path)
      return { data: {} }
    }
    const api = createPlatformApi(client as never)
    await api.platform.serpro.health()
    await api.platform.serpro.contracts.list()
    await api.platform.serpro.catalog()
    await api.platform.serpro.killSwitch.status()
    await api.platform.serpro.usage.consolidation({ year: 2026, month: 7 })
    expect(calls).toContain('/api/v1/platform/serpro/health')
    expect(calls).toContain('/api/v1/platform/serpro/contracts')
    expect(calls).toContain('/api/v1/platform/serpro/catalog')
    expect(calls).toContain('/api/v1/platform/serpro/kill-switch')
    expect(calls).toContain('/api/v1/platform/serpro-usage/consolidation')
  })

  it('páginas admin/serpro existem e não reexibem segredos', () => {
    const files = [
      'pages/admin/serpro.vue',
      'pages/admin/serpro/index.vue',
      'pages/admin/serpro/contracts.vue',
      'pages/admin/serpro/catalog.vue',
      'pages/admin/serpro/usage.vue',
      'pages/admin/serpro/rollout.vue'
    ]
    for (const f of files) {
      const src = readFileSync(resolve(APP, f), 'utf8')
      expect(src.length).toBeGreaterThan(100)
      expect(src).not.toMatch(/consumer_secret|pfx_password|vault_object|BEGIN PRIVATE/i)
      expect(src).not.toMatch(/v-html/i)
    }
  })

  it('settings checklist e health usam utilitários tenant-safe', () => {
    const settings = readFileSync(resolve(APP, 'pages/settings/index.vue'), 'utf8')
    expect(settings).toContain('SerproOnboardingChecklist')
    expect(settings).toMatch(/alfanumérico|12ABC34501DE35/i)
    expect(settings).toContain('clearSensitive')

    const health = readFileSync(resolve(APP, 'pages/health.vue'), 'utf8')
    expect(health).toContain('resolveInboxItemLink')
    expect(health).toContain('SERPRO_INBOX_TYPE_FILTERS')

    const slideover = readFileSync(resolve(APP, 'components/NotificationsSlideover.vue'), 'utf8')
    expect(slideover).toContain('resolveInboxItemLink')

    const homeOps = readFileSync(resolve(APP, 'components/home/HomeOperations.vue'), 'utf8')
    expect(homeOps).toContain('resolveInboxItemLink')
  })

  it('facade useApi expõe platform', () => {
    const facade = readFileSync(resolve(APP, 'composables/useApi.ts'), 'utf8')
    expect(facade).toContain('createPlatformApi')
    expect(facade).toContain('platform: platformApi.platform')
  })
})

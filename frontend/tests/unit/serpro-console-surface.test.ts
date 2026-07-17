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
  it('gates PLATFORM_ADMIN sem TOTP global na navegação', () => {
    expect(isPlatformAdmin(user({ is_platform_admin: true }))).toBe(true)
    expect(canAccessPlatformSerproConsole(user({ is_platform_admin: true }))).toBe(true)
    // OpenSpec 6.2/4.3: navegação de plataforma não exige TOTP global.
    expect(canAccessPlatformSerproConsole(user({
      is_platform_admin: true,
      two_factor_confirmed: false
    }))).toBe(true)
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
    await api.platform.serpro.configuration.show({ environment: 'TRIAL' })
    await api.platform.serpro.catalog()
    await api.platform.serpro.killSwitch.status()
    await api.platform.serpro.usage.consolidation({ year: 2026, month: 7 })
    expect(calls).toContain('/api/v1/platform/serpro/health')
    expect(calls).toContain('/api/v1/platform/serpro/contracts')
    expect(calls).toContain('/api/v1/platform/serpro/configuration')
    expect(calls).toContain('/api/v1/platform/serpro/catalog')
    expect(calls).toContain('/api/v1/platform/serpro/kill-switch')
    expect(calls).toContain('/api/v1/platform/serpro-usage/consolidation')
  })

  it('páginas admin/serpro existem e não reexibem segredos', () => {
    const files = [
      'pages/admin/serpro.vue',
      'pages/admin/serpro/index.vue',
      'pages/admin/serpro/configuration.vue',
      'pages/admin/serpro/contracts.vue',
      'pages/admin/serpro/catalog.vue',
      'pages/admin/serpro/usage.vue',
      'pages/admin/serpro/rollout.vue',
      'components/serpro/SerproOwnerConfirmModal.vue',
      'utils/serpro-owner-confirmation.ts'
    ]
    for (const f of files) {
      const src = readFileSync(resolve(APP, f), 'utf8')
      expect(src.length).toBeGreaterThan(100)
      // consumer_secret no formulário de upload é aceitável se não for exibido da API
      expect(src).not.toMatch(/pfx_password|vault_object|BEGIN PRIVATE/i)
      expect(src).not.toMatch(/v-html/i)
    }

    const configPage = readFileSync(resolve(APP, 'pages/admin/serpro/configuration.vue'), 'utf8')
    expect(configPage).toContain('admin-serpro-configuration')
    expect(configPage).toContain('credentialVersions')
    expect(configPage).not.toMatch(/vault_object_id|BEGIN CERTIFICATE/i)

    const contracts = readFileSync(resolve(APP, 'pages/admin/serpro/contracts.vue'), 'utf8')
    expect(contracts).toContain('/admin/serpro/configuration')
    expect(contracts).not.toMatch(/contracts\.activate|contracts\.store/)

    const readiness = readFileSync(resolve(APP, 'pages/admin/serpro/index.vue'), 'utf8')
    expect(readiness).toContain('SerproOwnerConfirmModal')
    expect(readiness).toContain('KILL_SWITCH_OFF')
    expect(readiness).not.toMatch(/segundo PLATFORM_ADMIN|quatro olhos/i)

    const rollout = readFileSync(resolve(APP, 'pages/admin/serpro/rollout.vue'), 'utf8')
    expect(rollout).toMatch(/DUAL|Office ADMIN|canário/i)
    expect(rollout).toContain('approval_policy')
  })

  it('settings unificado e health usam superfícies tenant-safe', () => {
    const settings = readFileSync(resolve(APP, 'pages/settings/index.vue'), 'utf8')
    expect(settings).toContain('settings-office-unified')
    expect(settings).toContain('SettingsOfficeCredentialSection')
    expect(settings).not.toContain('SerproOnboardingChecklist')
    expect(settings).not.toMatch(/Autor do Pedido|uploadTermo/i)

    const credential = readFileSync(resolve(APP, 'components/settings/OfficeCredentialSection.vue'), 'utf8')
    expect(credential).toContain('clearSensitive')

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

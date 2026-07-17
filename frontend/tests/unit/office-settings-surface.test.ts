import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { createOfficeApi } from '../../app/composables/api/createOfficeApi'
import { createPlatformApi } from '../../app/composables/api/createPlatformApi'
import {
  canAccessOfficeSettings,
  canAccessPlatformAdmin,
  isPlatformAdmin,
  isPlatformPrivilegedContext
} from '../../app/utils/permissions'
import {
  credentialAlerts,
  onboardingStatusLabel,
  actionableOfficeError
} from '../../app/utils/office-settings'
import {
  procuracaoLabel,
  procuracaoTone,
  normalizeProcuracaoStatus
} from '../../app/utils/procuracao'
import {
  commercialBalanceLabel,
  recentRefreshConfirmDescription
} from '../../app/utils/monitor-commercial'
import type { MeUser } from '../../app/types/api'

const APP = resolve(__dirname, '../../app')

function user(partial: Partial<MeUser> = {}): MeUser {
  return {
    id: 1,
    name: 'Teste',
    email: 't@example.com',
    two_factor_confirmed: true,
    two_factor_required: true,
    requires_two_factor_setup: false,
    is_platform_admin: false,
    office: { id: 1, name: 'Escritório', slug: 'escritorio' },
    role: 'ADMIN',
    ...partial
  }
}

describe('configuração unificada /settings (6.1)', () => {
  it('página unificada cobre perfil, consentimento, A1 e agendas', () => {
    const page = readFileSync(resolve(APP, 'pages/settings/index.vue'), 'utf8')
    expect(page).toContain('settings-office-unified')
    expect(page).toContain('SettingsOfficeProfileSection')
    expect(page).toContain('SettingsOfficeConsentSection')
    expect(page).toContain('SettingsOfficeCredentialSection')
    expect(page).toContain('SettingsOfficeSchedulesSection')
    // Sem campos técnicos SERPRO tenant-facing
    expect(page).not.toMatch(/Autor do Pedido|configureAuthor|uploadTermo|refreshToken/i)
    expect(page).not.toMatch(/consumer_secret|BEGIN PRIVATE|Baixar PFX/i)
    const credential = readFileSync(resolve(APP, 'components/settings/OfficeCredentialSection.vue'), 'utf8')
    expect(credential).toContain('clearSensitive')
  })

  it('credential section não oferece download e limpa senha', () => {
    const src = readFileSync(resolve(APP, 'components/settings/OfficeCredentialSection.vue'), 'utf8')
    expect(src).toContain('clearSensitive')
    expect(src).toMatch(/Sem download|nunca são recuperáveis|Nunca poderão ser baixados/i)
    expect(src).not.toMatch(/label="Baixar (PFX|certificado)"/i)
    expect(src).not.toMatch(/href=.*\/(pfx|download)/i)
  })

  it('API office unificada usa paths /office/settings/*', async () => {
    const calls: string[] = []
    const client = async (path: string) => {
      calls.push(path)
      return { data: { profile: {}, credential: null } }
    }
    const api = createOfficeApi(client as never)
    await api.office.profile.show()
    await api.office.technicalConsent.show()
    await api.office.canonicalCredential.show()
    await api.office.monitorSchedules.list()
    await api.office.onboardingStatus()
    expect(calls).toContain('/api/v1/office/settings')
    expect(calls).toContain('/api/v1/office/settings/consent')
    expect(calls).toContain('/api/v1/office/settings/credential')
    expect(calls).toContain('/api/v1/office/settings/monitor-schedules')
    expect(calls).toContain('/api/v1/office/settings/onboarding-status')
  })

  it('labels de onboarding e alertas de A1', () => {
    expect(onboardingStatusLabel('action_required')).toMatch(/Ação necessária/i)
    expect(credentialAlerts({
      status: 'ACTIVE',
      expires_alert_7: true
    })).toContain('Vence em até 7 dias')
    expect(actionableOfficeError('oauth mTLS failed')).toMatch(/pendência técnica|plataforma/i)
  })
})

describe('plataforma /admin e seletor global (6.2)', () => {
  it('PLATFORM_ADMIN acessa admin sem TOTP; office ADMIN não', () => {
    const plat = user({
      role: null,
      office: null,
      is_platform_admin: true,
      two_factor_confirmed: false,
      two_factor_required: true
    })
    expect(isPlatformAdmin(plat)).toBe(true)
    expect(canAccessPlatformAdmin(plat)).toBe(true)
    expect(canAccessOfficeSettings(user({ role: 'ADMIN' }))).toBe(true)
    expect(canAccessPlatformAdmin(user({ role: 'ADMIN', is_platform_admin: false }))).toBe(false)
  })

  it('contexto privilegiado exige access_mode + office', () => {
    expect(isPlatformPrivilegedContext(user({
      is_platform_admin: true,
      access_mode: 'platform_privileged',
      office: { id: 9, name: 'X', slug: 'x' },
      role: null
    }))).toBe(true)
    expect(isPlatformPrivilegedContext(user({
      is_platform_admin: true,
      access_mode: 'membership',
      office: { id: 1, name: 'A', slug: 'a' }
    }))).toBe(false)
  })

  it('API platform offices select/clear', async () => {
    const calls: Array<{ path: string, method?: string }> = []
    const client = async (path: string, opts?: { method?: string }) => {
      calls.push({ path, method: opts?.method })
      return { data: {} }
    }
    const api = createPlatformApi(client as never)
    await api.platform.offices.list()
    await api.platform.offices.select(3)
    await api.platform.offices.clear()
    expect(calls[0]?.path).toBe('/api/v1/platform/offices')
    expect(calls[1]).toEqual({ path: '/api/v1/platform/offices/select', method: 'POST' })
    expect(calls[2]?.method).toBe('DELETE')
  })

  it('admin entra por Escritórios e as superfícies internas seguem Lista/Settings', () => {
    const admin = readFileSync(resolve(APP, 'pages/admin/index.vue'), 'utf8')
    expect(admin).toContain('redirect: \'/admin/offices\'')
    expect(admin).not.toContain('UDashboardPanel')
    expect(admin).not.toContain('UPageCard')

    const list = readFileSync(resolve(APP, 'pages/admin/offices/index.vue'), 'utf8')
    expect(list).toContain('admin-offices-panel')
    expect(list).toContain('admin-offices-toolbar')
    expect(list).toContain('admin-offices-table')
    expect(list).not.toContain('DashboardContent')

    const create = readFileSync(resolve(APP, 'pages/admin/offices/new.vue'), 'utf8')
    expect(create).toContain('DashboardContent width="comfortable"')
    expect(create).toContain('admin-office-mobile-progress')
    expect(create).toContain('admin-office-stepper')
    expect(create).not.toContain('stepDescriptions')

    const detail = readFileSync(resolve(APP, 'pages/admin/offices/[id].vue'), 'utf8')
    expect(detail).toContain('admin-office-regenerate-card')
    expect(detail).toContain('admin-office-first-admin-card')
    expect(detail).toContain('admin-office-correct-reconfirm')

    const staticDescriptions = [list, create, detail]
      .flatMap(source => source.match(/\sdescription="[^"]+"/g) || [])
    expect(staticDescriptions).toHaveLength(1)
    expect(staticDescriptions[0]).toContain('Revoga o acesso anterior.')
    expect(admin).not.toContain('admin-global-office-selector')
    expect(admin).not.toContain('admin-actor-card')

    const identity = readFileSync(resolve(APP, 'components/OfficeIdentity.vue'), 'utf8')
    expect(identity).toContain('platform-global')
    expect(identity).toContain('usePlatformOfficeSelect')
    expect(identity).toContain('<USelectMenu')
    expect(identity).toContain('Buscar escritório…')
    expect(identity).toContain('content: \'w-88')
    expect(identity).toMatch(/filter-fields=.*label.*description/)
    expect(identity).not.toContain('PLATFORM_ADMIN\\n')
    expect(identity).toMatch(/data-context-style=.*compact.*detailed/)
    expect(identity).not.toContain('Atuando em:')
    expect(identity).not.toContain('ring-warning')
    expect(identity).toContain('data-platform-seal')
    expect(identity).toContain(':aria-busy="loading || switching"')
    expect(identity).not.toContain(':loading="loading || switching"')
    expect(identity).toContain(':class="collapsed && \'hidden\'"')
    expect(identity).not.toContain('v-if="!collapsed"')
    // Banner privilegiado removido — identidade compacta no seletor.
    expect(identity).not.toContain('privileged-context-banner')
  })

  it('middleware reserva /admin à plataforma', () => {
    const mw = readFileSync(resolve(APP, 'middleware/auth.global.ts'), 'utf8')
    expect(mw).toContain('canAccessPlatformAdmin')
    expect(mw).toContain('/settings')
    expect(mw).toMatch(/settings\/proxies/)
  })
})

describe('procuração e franquia na UI', () => {
  it('traduz estados oficiais de procuração', () => {
    expect(normalizeProcuracaoStatus('authorized')).toBe('authorized')
    expect(procuracaoLabel('missing')).toBe('Sem procuração')
    expect(procuracaoTone('expired')).toBe('error')
  })

  it('saldo e confirmação de refresh recente', () => {
    expect(commercialBalanceLabel({ remaining: 3, limit: 5 })).toBe('3/5')
    expect(recentRefreshConfirmDescription({ lastAt: null, remaining: 2 })).toMatch(/franquia|unidade/i)
  })

  it('lista de clientes expõe coluna Procuração', () => {
    const clients = readFileSync(resolve(APP, 'pages/clients/index.vue'), 'utf8')
    expect(clients).toContain('procuracao')
    expect(clients).toContain('ClientsClientProcuracaoBadge')
  })

  it('SITFIS expõe franquia, procuração e modal de refresh', () => {
    const sitfis = readFileSync(resolve(APP, 'pages/monitoring/sitfis.vue'), 'utf8')
    expect(sitfis).toContain('CommercialMetaCell')
    expect(sitfis).toContain('ClientProcuracaoBadge')
    expect(sitfis).toContain('MonitoringRecentRefreshConfirmModal')
    expect(sitfis).toContain('recentConfirmOpen')
  })
})

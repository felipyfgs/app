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
      'pages/admin/serpro/dte-canary.vue',
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

  it('reduz o console a três áreas e monta somente a subvisão selecionada', () => {
    const shell = readFileSync(resolve(APP, 'pages/admin/serpro.vue'), 'utf8')
    const primaryLinks = shell.match(/const links = (\[\[[\s\S]*?\]\]) satisfies NavigationMenuItem/)?.[1] || ''

    expect(primaryLinks.match(/label:/g)).toHaveLength(3)
    expect(primaryLinks).toContain('label: \'Operação\'')
    expect(primaryLinks).toContain('label: \'Integração\'')
    expect(primaryLinks).toContain('label: \'Canário DTE\'')
    expect(primaryLinks).not.toMatch(/label: '(?:Cobertura|Consumo|Liberação|Contratos)'/)

    const operation = readFileSync(resolve(APP, 'pages/admin/serpro/index.vue'), 'utf8')
    expect(operation).toContain('admin-serpro-operation-sections')
    expect(operation).toContain('if (activeSection.value !== \'status\') return')
    expect(operation).toContain('<UsageView v-else-if=')
    expect(operation).toContain('<RolloutView v-else')

    const integration = readFileSync(resolve(APP, 'pages/admin/serpro/configuration.vue'), 'utf8')
    expect(integration).toContain('admin-serpro-integration-sections')
    expect(integration).toContain('if (activeSection.value !== \'access\') return')
    expect(integration).toContain('<ContractsView v-else-if=')
    expect(integration).toContain('<CatalogView v-else')
    expect(integration).not.toContain('definePageMeta({')
  })

  it('UAlert sem description; consequências críticas no título curto', () => {
    const files = [
      'pages/admin/serpro.vue',
      'pages/admin/serpro/index.vue',
      'pages/admin/serpro/configuration.vue',
      'pages/admin/serpro/contracts.vue',
      'pages/admin/serpro/catalog.vue',
      'pages/admin/serpro/usage.vue',
      'pages/admin/serpro/rollout.vue',
      'pages/admin/serpro/dte-canary.vue',
      'components/serpro/SerproOwnerConfirmModal.vue'
    ]
    const sources = files.map(file => readFileSync(resolve(APP, file), 'utf8'))
    const joined = sources.join('\n')

    // Contrato do template: UAlert só com title acionável (gate de fidelidade).
    for (const source of sources) {
      const alertBlocks = source.match(/<UAlert\b[\s\S]*?(?:\/>|<\/UAlert>)/g) || []
      for (const block of alertBlocks) {
        expect(block).not.toMatch(/(?:^|\s):?description=/)
      }
    }

    expect(joined).toMatch(/credencial.*exposta|exposta.*rota/i)
    // Consequência crítica de kill DTE permanece legível no card de emergência.
    expect(joined).toMatch(/bloqueia novas consultas/i)
  })

  it('mantém URLs antigas como redirects para as seções canônicas', () => {
    const redirects = [
      ['pages/admin/serpro/contracts.vue', '/admin/serpro/configuration', 'contracts'],
      ['pages/admin/serpro/catalog.vue', '/admin/serpro/configuration', 'coverage'],
      ['pages/admin/serpro/usage.vue', '/admin/serpro', 'usage'],
      ['pages/admin/serpro/rollout.vue', '/admin/serpro', 'rollout']
    ] as const

    for (const [path, destination, section] of redirects) {
      const page = readFileSync(resolve(APP, path), 'utf8')
      expect(page).toContain('definePageMeta({')
      expect(page).toContain(`path: '${destination}'`)
      expect(page).toContain(`query: { section: '${section}' }`)
    }
  })

  it('mantém catálogo, consumo e rollout fail-closed quando a leitura falha', () => {
    const catalog = readFileSync(resolve(APP, 'pages/admin/serpro/catalog.vue'), 'utf8')
    expect(catalog).toContain('v-if="!loading && !loadError && !filtered.length"')
    expect(catalog).toMatch(/watch\(environment,[\s\S]*?rows\.value = \[\][\s\S]*?void load\(\)/)

    const usage = readFileSync(resolve(APP, 'pages/admin/serpro/usage.vue'), 'utf8')
    const periodWatcher = usage.match(/watch\(\[year, month\], \(\) => \{([\s\S]*?)\n\}\)/)?.[1] || ''
    expect(periodWatcher).toContain('clearPeriodSnapshot()')
    expect(periodWatcher).toContain('loadSeq++')
    expect(periodWatcher).not.toContain('load()')
    expect(usage).toContain('const periodLoaded = ref(false)')
    expect(usage).toMatch(/function clearPeriodSnapshot\(\) \{\s+loading\.value = false/)
    expect(usage).toContain(':disabled="!periodLoaded || loading"')
    expect(usage).toContain('v-else-if="periodLoaded"')

    const rollout = readFileSync(resolve(APP, 'pages/admin/serpro/rollout.vue'), 'utf8')
    expect(rollout).toContain('const approvalsError = ref<string | null>(null)')
    expect(rollout).toContain('rollout.value = null')
    expect(rollout).not.toContain('rollout.value = deriveFromHealth(null)')
    expect(rollout).toContain('v-else-if="approvalsError"')
    expect(rollout).not.toContain('aria-labelledby=')
  })

  it('isola configuração por ambiente e não oferece kill switch com estado desconhecido', () => {
    const configuration = readFileSync(resolve(APP, 'pages/admin/serpro/configuration.vue'), 'utf8')
    expect(configuration).toContain('function resetEnvironmentState()')
    expect(configuration).toContain('clearUpload()')
    expect(configuration).toContain('requestedEnvironment !== environment.value')
    expect(configuration).toContain(':to="`/admin/offices/${o.office_id}`"')
    expect(configuration).not.toContain('to="/settings"')

    const overview = readFileSync(resolve(APP, 'pages/admin/serpro/index.vue'), 'utf8')
    expect(overview).toContain('const killStateKnown = computed')
    expect(overview).toContain('v-if="killStateKnown"')
    expect(overview).toContain('kill.value = null')
    expect(overview).toContain('\'Indisponível\'')
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

import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

/** Contrato da conta dos usuários do escritório e do alias legado da plataforma. */
describe('admin owner surface contract', () => {
  const legacyPage = readFileSync(
    resolve(__dirname, '../../app/pages/admin/owner/index.vue'),
    'utf8'
  )
  const page = readFileSync(
    resolve(__dirname, '../../app/pages/conta/index.vue'),
    'utf8'
  )
  const officeProfile = readFileSync(
    resolve(__dirname, '../../app/components/settings/OfficeProfileSection.vue'),
    'utf8'
  )
  const shell = readFileSync(
    resolve(__dirname, '../../app/pages/conta.vue'),
    'utf8'
  )
  const accountNavigation = readFileSync(
    resolve(__dirname, '../../app/utils/account-navigation.ts'),
    'utf8'
  )
  const nav = readFileSync(
    resolve(__dirname, '../../app/utils/navigation.ts'),
    'utf8'
  )
  const api = readFileSync(
    resolve(__dirname, '../../app/composables/api/createAuthApi.ts'),
    'utf8'
  )
  const userMenu = readFileSync(
    resolve(__dirname, '../../app/components/UserMenu.vue'),
    'utf8'
  )

  it('expõe perfil genérico sem campos exclusivos do proprietário', () => {
    expect(shell).toContain('data-testid="account-panel"')
    expect(shell).toContain('title="Conta"')
    expect(shell).toContain('accountNavigationTree(me.value)')
    expect(shell).toContain('SectionNavigation')
    expect(accountNavigation).toContain('label: \'Perfil\'')
    expect(accountNavigation).toContain('to: \'/conta\'')
    expect(accountNavigation).toContain('label: \'Escritório\'')
    expect(accountNavigation).toContain('to: \'/conta/escritorio\'')
    expect(officeProfile).toContain('title="Perfil do escritório"')
    expect(page).toContain('data-testid="account-profile-form"')
    expect(page).toContain('api.account.update')
    expect(page).toContain('<UForm')
    expect(page).not.toContain('api.platform.owner')
    expect(page).not.toContain('default_office_id')
    expect(page).not.toContain('Escritório padrão')
    expect(legacyPage).toContain('navigateTo(\'/admin/offices\'')
    expect(legacyPage).not.toContain('api.platform.owner')
  })

  it('separa o perfil pessoal do escritório e mantém Conta fora do grupo Admin', () => {
    expect(nav).toContain('accountNavigationItems(user)')
    expect(accountNavigation).toContain('id: \'account-profile\'')
    expect(accountNavigation).toContain('to: \'/conta\'')
    expect(nav).not.toContain('id: \'platform-owner\'')
    expect(nav).not.toContain('to: \'/admin/owner\'')
    expect(userMenu).toContain('canAccessPlatformAdmin.value')
    expect(userMenu).toContain('icon: \'i-lucide-shield\'')
    expect(userMenu).toContain('disabled: true')
    expect(userMenu).toContain('label: \'Conta\'')
    expect(userMenu).toContain('to: \'/conta\'')
  })

  it('API de conta atualiza somente a identidade própria', () => {
    expect(api).toContain('account: {')
    expect(api).toContain('\'/api/v1/account\'')
    expect(api).toContain('method: \'PATCH\'')
  })
})

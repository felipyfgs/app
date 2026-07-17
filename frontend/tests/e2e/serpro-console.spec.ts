import { test, expect } from '@playwright/test'
import { installApiFixtures, installPlatformApiFixtures } from './support/api-fixtures'

/**
 * Smoke de rotas SERPRO (console platform + checklist tenant).
 * Fixtures sintéticas apenas — sem material produtivo.
 */
test.describe('SERPRO console e checklist (rotas)', () => {
  test.beforeEach(async ({ page }) => {
    await installApiFixtures(page, 'ADMIN')
  })

  test('settings unificado expõe perfil e certificado (sem checklist técnico)', async ({ page }) => {
    await page.goto('/settings')
    await expect(page.getByTestId('page-navbar')).toBeVisible()
    await expect(page.getByTestId('settings-office-unified')).toBeVisible({ timeout: 15_000 })
    await expect(page.getByTestId('settings-profile-section')).toBeVisible()
    await expect(page.getByTestId('settings-credential-section')).toBeVisible()
    await expect(page.getByTestId('serpro-onboarding-checklist')).toHaveCount(0)
  })

  test('proxies legado redireciona para settings unificado', async ({ page }) => {
    await page.goto('/settings/proxies')
    await expect(page).toHaveURL(/\/settings\/?$/, { timeout: 15_000 })
    await expect(page.getByTestId('settings-office-unified')).toBeVisible({ timeout: 15_000 })
  })

  test('console platform nega ADMIN sem is_platform_admin (redirect ou denied)', async ({ page }) => {
    await page.goto('/admin/serpro')
    // Office ADMIN (sem PLATFORM_ADMIN) → middleware redireciona /admin/* para settings.
    await expect(page).toHaveURL(/\/settings/, { timeout: 15_000 })
    await expect(page.getByTestId('settings-panel')).toBeVisible()
  })

  test('health lista filtros SERPRO na toolbar', async ({ page }) => {
    await page.goto('/health')
    await expect(page.getByTestId('page-navbar')).toBeVisible()
    // Select de tipo inclui opções SERPRO (valor no DOM/items)
    await expect(page.getByLabel('Filtrar por tipo')).toBeVisible()
  })
})

test.describe('SERPRO configuração global (PLATFORM_ADMIN)', () => {
  test.beforeEach(async ({ page }) => {
    await installPlatformApiFixtures(page)

    await page.route('**/api/v1/platform/serpro/**', async (route) => {
      const pathname = new URL(route.request().url()).pathname
      if (pathname.endsWith('/configuration')) {
        return route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            data: {
              environment: 'TRIAL',
              endpoints: {
                oauth_token_url: 'https://autenticacao.sapi.serpro.gov.br/authenticate',
                api_base_url: 'https://gateway.apiserpro.serpro.gov.br/integra-contador/v1',
                role_type: 'TERCEIROS'
              },
              contract: null,
              active_credential_version: null,
              pending_credential_versions: [],
              credential_history: [],
              external_gates: [],
              external_gates_blocking: false,
              usage_limits: {
                config: {
                  cycle_start_day: 1,
                  alert_percent: 80,
                  global_limit_quantity: null
                },
                office_limits: [],
                usage: { allowed: false, alert_reached: false }
              },
              kill_switch: { global: { active: false, source: null }, solutions: {} },
              pending_offices: { count: 0, items: [] },
              summary: {
                has_active_credential: false,
                has_pending_credential: false,
                configuration_ready: false,
                kill_switch_active: false,
                usage_allowed: false
              }
            }
          })
        })
      }
      if (pathname.includes('/contracts')) {
        return route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({ data: [] })
        })
      }
      return route.fallback()
    })
  })

  test('página Configuração carrega sem material secreto', async ({ page }) => {
    await page.goto('/admin/serpro/configuration')
    await expect(page.getByTestId('admin-serpro-configuration')).toBeVisible({ timeout: 15_000 })
    await expect(page.getByTestId('serpro-config-summary')).toBeVisible()
    await expect(page.getByTestId('serpro-config-credentials')).toBeVisible()
    const body = await page.locator('body').innerText()
    expect(body).not.toMatch(/BEGIN PRIVATE|consumer_secret|vault_object/i)
  })

  test('aba Contratos aponta para Configuração', async ({ page }) => {
    await page.goto('/admin/serpro/contracts')
    await expect(page.getByTestId('admin-serpro-contracts')).toBeVisible({ timeout: 15_000 })
    await expect(page.getByTestId('admin-serpro-contracts-go-config')).toBeVisible()
    await page.getByTestId('admin-serpro-contracts-go-config').click()
    await expect(page).toHaveURL(/\/admin\/serpro\/configuration/, { timeout: 15_000 })
  })
})

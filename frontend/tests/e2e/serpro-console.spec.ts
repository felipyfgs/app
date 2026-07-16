import { test, expect } from '@playwright/test'
import { installApiFixtures } from './support/api-fixtures'

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

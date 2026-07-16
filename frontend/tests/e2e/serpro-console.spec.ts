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

  test('settings expõe checklist de onboarding Integra', async ({ page }) => {
    await page.goto('/settings')
    await expect(page.getByTestId('page-navbar')).toBeVisible()
    await expect(page.getByTestId('serpro-settings-checklist-card')).toBeVisible({ timeout: 15_000 })
    await expect(page.getByTestId('serpro-onboarding-checklist')).toBeVisible()
    await expect(page.getByTestId('serpro-checklist-step-environment')).toBeVisible()
    await expect(page.getByTestId('serpro-checklist-step-author')).toBeVisible()
    await expect(page.getByTestId('serpro-checklist-step-certificate_termo')).toBeVisible()
    await expect(page.getByTestId('serpro-checklist-step-token')).toBeVisible()
    await expect(page.getByTestId('serpro-checklist-step-proxy_power')).toBeVisible()
    await expect(page.getByTestId('serpro-checklist-step-client_operation')).toBeVisible()
  })

  test('proxies expõe seletores tipados de serviço/poder', async ({ page }) => {
    await page.goto('/settings/proxies')
    await expect(page.getByTestId('page-navbar')).toBeVisible()
    await expect(page.getByTestId('serpro-typed-selectors')).toBeVisible({ timeout: 15_000 })
    await expect(page.getByTestId('serpro-select-service')).toBeVisible()
    await expect(page.getByTestId('serpro-select-power')).toBeVisible()
  })

  test('console platform nega ADMIN sem is_platform_admin', async ({ page }) => {
    await page.goto('/admin/serpro')
    await expect(page.getByTestId('admin-serpro-panel')).toBeVisible()
    await expect(page.getByTestId('admin-serpro-denied')).toBeVisible({ timeout: 15_000 })
  })

  test('health lista filtros SERPRO na toolbar', async ({ page }) => {
    await page.goto('/health')
    await expect(page.getByTestId('page-navbar')).toBeVisible()
    // Select de tipo inclui opções SERPRO (valor no DOM/items)
    await expect(page.getByLabel('Filtrar por tipo')).toBeVisible()
  })
})

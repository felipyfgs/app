import { test, expect } from '@playwright/test'
import { installApiFixtures } from './support/api-fixtures'

/**
 * Smoke: settings unificado (office ADMIN) + reserva de /admin à plataforma.
 */
test.describe('Settings unificado e admin plataforma', () => {
  test('ADMIN office vê settings unificado (perfil/A1)', async ({ page }) => {
    await installApiFixtures(page, 'ADMIN')
    await page.goto('/settings')
    await expect(page.getByTestId('page-navbar')).toBeVisible()
    await expect(page.getByTestId('settings-panel')).toBeVisible()
    await expect(page.getByTestId('settings-office-unified')).toBeVisible({ timeout: 15_000 })
    await expect(page.getByTestId('settings-profile-section')).toBeVisible()
    await expect(page.getByTestId('settings-consent-section')).toBeVisible()
    await expect(page.getByTestId('settings-credential-section')).toBeVisible()
    await expect(page.getByTestId('settings-schedules-section')).toBeVisible()
    // Sem checklist técnico Integra Contador
    await expect(page.getByTestId('serpro-onboarding-checklist')).toHaveCount(0)
  })

  test('ADMIN office em /admin é redirecionado para settings', async ({ page }) => {
    await installApiFixtures(page, 'ADMIN')
    await page.goto('/admin')
    await expect(page).toHaveURL(/\/settings/, { timeout: 15_000 })
  })

  test('lista de clientes tem coluna Procuração (header)', async ({ page }) => {
    await installApiFixtures(page, 'ADMIN')
    await page.goto('/clients')
    await expect(page.getByTestId('data-table')).toBeVisible({ timeout: 15_000 })
    await expect(page.getByRole('columnheader', { name: /Procuração/i })).toBeVisible()
  })
})

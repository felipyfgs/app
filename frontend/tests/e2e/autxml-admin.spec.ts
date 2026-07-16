import { test, expect } from '@playwright/test'
import { installApiFixtures } from './support/api-fixtures'

/**
 * Smoke de presença das superfícies autXML/import com sessão ADMIN sintética.
 * Onboarding autXML/sync permanece em Sincronizações; /admin é plataforma.
 */
test.describe('autXML e importações (rotas)', () => {
  test.beforeEach(async ({ page }) => {
    await installApiFixtures(page, 'ADMIN')
  })

  test('office ADMIN em /admin é redirecionado para settings', async ({ page }) => {
    await page.goto('/admin')
    await expect(page).toHaveURL(/\/settings/, { timeout: 15_000 })
    await expect(page.getByTestId('settings-panel')).toBeVisible()
  })

  test('histórico de importações é navegável', async ({ page }) => {
    await page.goto('/docs/imports')
    await expect(page.getByTestId('page-navbar')).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Importações XML/ZIP', exact: true })).toBeVisible()
  })

  test('sincronizações exibe card autXML', async ({ page }) => {
    await page.goto('/syncs')
    await expect(page.getByRole('heading', { name: 'Sincronizações', exact: true })).toBeVisible()
    await expect(page.getByText('Sincronização central autXML (escritório)', { exact: true })).toBeVisible()
    await expect(page.getByText('NF-e 55 · autXML', { exact: true })).toBeVisible()
  })
})

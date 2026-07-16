import { test, expect } from '@playwright/test'
import { installApiFixtures } from './support/api-fixtures'

/**
 * Smoke de presença das superfícies autXML/import com sessão ADMIN sintética.
 */
test.describe('autXML e importações (rotas)', () => {
  test.beforeEach(async ({ page }) => {
    await installApiFixtures(page, 'ADMIN')
  })

  test('admin expõe onboarding autXML quando autenticado como ADMIN', async ({ page }) => {
    await page.goto('/admin')
    await expect(page.getByRole('heading', { name: 'Administração', exact: true })).toBeVisible()
    await expect(page.getByText('Onboarding autXML por estabelecimento', { exact: true })).toBeVisible()
    await expect(page.getByText('autXML cobre NF-e 55 e não é retroativo', { exact: true })).toBeVisible()
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

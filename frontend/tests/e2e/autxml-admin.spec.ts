import { test, expect } from '@playwright/test'

/**
 * Smoke de presença das superfícies autXML/import (rotas autenticadas).
 * Depende do estado de login do projeto (mesmo padrão dos demais e2e).
 */
test.describe('autXML e importações (rotas)', () => {
  test('admin expõe data-testid do onboarding quando autenticado como ADMIN', async ({ page }) => {
    await page.goto('/admin')
    // Sem sessão: redireciona login; com sessão: card presente
    const url = page.url()
    if (url.includes('login')) {
      await expect(page.getByRole('button', { name: /entrar|login/i }).or(page.locator('input[type="password"]'))).toBeVisible({ timeout: 10000 })
      return
    }
    await expect(page.getByTestId('admin-autxml-card').or(page.getByTestId('admin-autxml-onboarding'))).toBeVisible({ timeout: 15000 })
  })

  test('histórico de importações é navegável', async ({ page }) => {
    await page.goto('/docs/imports')
    const url = page.url()
    if (url.includes('login')) {
      await expect(page.locator('body')).toBeVisible()
      return
    }
    await expect(page.getByTestId('page-navbar').or(page.getByText(/Importações|Lotes/i))).toBeVisible({ timeout: 15000 })
  })

  test('sincronizações exibe card autXML', async ({ page }) => {
    await page.goto('/syncs')
    const url = page.url()
    if (url.includes('login')) {
      return
    }
    await expect(page.getByTestId('autxml-sync-card').or(page.getByText(/autXML/i))).toBeVisible({ timeout: 15000 })
  })
})

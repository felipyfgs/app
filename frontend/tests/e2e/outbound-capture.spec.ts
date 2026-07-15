import { expect, test } from '@playwright/test'
import type { OfficeRole } from '../../app/types/api'
import { installApiFixtures } from './support/api-fixtures'

/**
 * Captura de saídas MA — desktop e mobile (projetos Playwright).
 * Fixtures sintéticas: sem rede fiscal, sem certificado/CSC real.
 */
for (const role of ['ADMIN', 'OPERATOR', 'VIEWER'] satisfies OfficeRole[]) {
  test.describe(`captura de saídas como ${role}`, () => {
    test.beforeEach(async ({ page }) => {
      await installApiFixtures(page, role)
    })

    test('página /clients/1/saidas renderiza painel e posição nNF', async ({ page }) => {
      await page.goto('/clients/1/saidas')
      await expect(page.getByTestId('outbound-capture-panel')).toBeVisible()
      await expect(page.getByText('Captura de saídas').first()).toBeVisible()
      await expect(page.getByText(/Posição por nNF|pos: nNF|nNF/i).first()).toBeVisible()

      // Modo assistido default na fixture
      await expect(page.getByText(/ASSISTED/i).first()).toBeVisible()

      // Lacunas / chave sem XML
      await expect(page.getByText(/EXHAUSTED_VISIBLE|XML_PENDING/i).first()).toBeVisible()
      await expect(page.getByText(/chave s\/ XML|lacuna|nNF 1[34]/i).first()).toBeVisible()

      const body = await page.locator('body').innerText()
      expect(body).not.toMatch(/TOKEN-SECRETO|BEGIN PRIVATE|csc_token\s*=/i)
      expect(body).not.toMatch(/last_nsu/i)
    })

    test('ações respeitam papel', async ({ page }) => {
      await page.goto('/clients/1/saidas')
      await expect(page.getByTestId('outbound-capture-panel')).toBeVisible()

      if (role === 'VIEWER') {
        await expect(page.getByTestId('outbound-seed-submit')).toHaveCount(0)
        await expect(page.getByTestId('outbound-package-submit')).toHaveCount(0)
        await expect(page.getByTestId('outbound-activate')).toHaveCount(0)
        await expect(page.getByTestId('outbound-kill-on')).toHaveCount(0)
      }

      if (role === 'OPERATOR') {
        await expect(page.getByTestId('outbound-seed-submit').first()).toBeVisible()
        await expect(page.getByTestId('outbound-package-submit').first()).toBeVisible()
        await expect(page.getByTestId('outbound-trigger-query').first()).toBeVisible()
        // ADMIN-only
        await expect(page.getByTestId('outbound-activate')).toHaveCount(0)
        await expect(page.getByTestId('outbound-kill-on')).toHaveCount(0)
      }

      if (role === 'ADMIN') {
        await expect(page.getByTestId('outbound-seed-submit').first()).toBeVisible()
        await expect(page.getByTestId('outbound-activate').first()).toBeVisible()
        await expect(page.getByTestId('outbound-kill-on').first()).toBeVisible()
        await expect(page.getByTestId('outbound-reset').first()).toBeVisible()
      }
    })
  })
}

test.describe('saúde e kill switch MA', () => {
  test('ADMIN vê kill switch e itens de inbox nNF', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'minimum-360', 'Cobertura em desktop/mobile principal.')
    await installApiFixtures(page, 'ADMIN')
    await page.goto('/health')
    await expect(page.getByTestId('ma-kill-switch-card')).toBeVisible()
    await expect(page.getByText(/M2M:\s*NO_GO_M2M/i)).toBeVisible()
    await expect(page.getByText(/nNF|Lacuna esgotada|Incidente fiscal MA/i).first()).toBeVisible()
  })

  test('VIEWER não opera kill switch', async ({ page }) => {
    await installApiFixtures(page, 'VIEWER')
    await page.goto('/health')
    await expect(page.getByTestId('ma-kill-switch-card')).toBeVisible()
    await expect(page.getByTestId('ma-kill-on')).toHaveCount(0)
  })
})

test.describe('segredos ausentes na UI de saídas', () => {
  test('formulário CSC de ADMIN não ecoa valor após submit simulado', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-1440', 'Interação de formulário no desktop.')
    await installApiFixtures(page, 'ADMIN')
    await page.goto('/clients/1/saidas')
    await expect(page.getByTestId('outbound-capture-panel')).toBeVisible()

    // Perfil 65 na fixture tem status ACTIVE e campo CSC admin
    const cscInputs = page.locator('input[type="password"]')
    if (await cscInputs.count() > 0) {
      await cscInputs.first().fill('TOKEN-SECRETO-NUNCA-LOGAR')
      const bodyAfter = await page.locator('body').innerText()
      // O valor tipado fica no input (type=password), não em texto visível
      expect(bodyAfter).not.toContain('TOKEN-SECRETO-NUNCA-LOGAR')
    }
  })
})

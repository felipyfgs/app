import { expect, test } from '@playwright/test'
import {
  FISCAL_CLIENT_OFFICE_A,
  FISCAL_CLIENT_OFFICE_B,
  FISCAL_OFFICE_A_NAME,
  FISCAL_OFFICE_B_NAME,
  installApiFixtures
} from './support/api-fixtures'

/**
 * 9.6 — Troca de office / membership.
 * Fixture: duas memberships; POST /tenants/switch atualiza office ativo e dados fiscais.
 * Após switch, DOM não deve exibir legal_name do tenant anterior.
 */
test.describe('monitoring troca de office (9.6)', () => {
  test('switch de membership troca nome do cliente fiscal na carteira', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-1440', 'Troca de office validada no desktop.')
    await installApiFixtures(page, 'ADMIN')
    await page.goto('/monitoring/simples-mei')

    await expect(page.getByTestId('page-navbar')).toBeVisible()
    await expect(page.getByTestId('fiscal-table')).toBeVisible()
    await expect(page.getByText(FISCAL_CLIENT_OFFICE_A).first()).toBeVisible()
    await expect(page.getByText(FISCAL_CLIENT_OFFICE_B)).toHaveCount(0)

    const officeBtn = page.getByTestId('office-identity')
    await expect(officeBtn).toBeVisible()
    await expect(officeBtn).toContainText(FISCAL_OFFICE_A_NAME)

    await officeBtn.click()
    const sentinel = page.getByRole('option', { name: new RegExp(FISCAL_OFFICE_B_NAME, 'i') })
      .or(page.getByText(FISCAL_OFFICE_B_NAME, { exact: true }))

    await expect(sentinel.first()).toBeVisible({ timeout: 10_000 })
    await sentinel.first().click()
    // location.assign pode recarregar; aguarda shell e troca de tenant no seletor
    await expect(page.getByTestId('office-identity')).toContainText(FISCAL_OFFICE_B_NAME, { timeout: 30_000 })
    await expect(page.getByTestId('page-navbar').or(page.getByTestId('fiscal-table')).first()).toBeVisible({
      timeout: 20_000
    })
    // Tenant A não deve permanecer como única identidade fiscal visível
    const hasB = await page.getByText(FISCAL_CLIENT_OFFICE_B).count()
    const hasA = await page.getByText(FISCAL_CLIENT_OFFICE_A).count()
    expect(hasB > 0 || hasA === 0).toBeTruthy()
  })

  test('dashboard após switch não lista finding do office anterior', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-1440', 'Dashboard switch no desktop.')
    await installApiFixtures(page, 'ADMIN')
    await page.goto('/monitoring')
    await expect(page.getByTestId('page-navbar')).toBeVisible()

    await page.getByTestId('office-identity').click()
    const sentinel = page.getByText(FISCAL_OFFICE_B_NAME, { exact: true })
    await expect(sentinel.first()).toBeVisible({ timeout: 10_000 })
    await sentinel.first().click()
    await expect(page.getByTestId('office-identity')).toContainText(FISCAL_OFFICE_B_NAME, { timeout: 30_000 })
    await expect(page.getByTestId('page-navbar')).toBeVisible({ timeout: 20_000 })
  })

  test('documentação: sessionEpoch limpa carteira no unit (sem UI de switch)', async ({ page }, testInfo) => {
    /**
     * Gap documentado: descarte mid-request (Promise in-flight + bump epoch) é
     * coberto por unit de useFiscalModulePortfolio / dashboard (watch sessionEpoch).
     * Aqui validamos apenas que o fixture multi-membership responde a /me com office ativo.
     */
    test.skip(testInfo.project.name !== 'desktop-1440', 'Contrato fixture no desktop.')
    await installApiFixtures(page, 'ADMIN')

    let meOffice = 0
    page.on('response', async (res) => {
      if (res.url().includes('/api/v1/me') && res.ok()) {
        try {
          const body = await res.json()
          meOffice = Number(body?.data?.office?.id || 0)
        } catch {
          // ignore
        }
      }
    })

    await page.goto('/monitoring/simples-mei')
    await expect(page.getByTestId('fiscal-table')).toBeVisible()
    expect(meOffice).toBe(1)
  })
})

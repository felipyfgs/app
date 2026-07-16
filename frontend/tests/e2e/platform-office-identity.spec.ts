import { expect, test } from '@playwright/test'
import {
  GENERIC_ACCOUNTANT_OFFICE_NAME,
  installPlatformApiFixtures,
  PLATFORM_OFFICE_NAME
} from './support/api-fixtures'

test.describe('identidade PLATFORM_ADMIN e escritórios demo', () => {
  test.beforeEach(async ({ page }) => {
    await installPlatformApiFixtures(page)
  })

  test('separa visualmente o perfil global do escritório ativo', async ({ page }) => {
    await page.goto('/admin')
    await expect(page.getByTestId('admin-platform-panel')).toBeVisible()

    const identity = page.getByTestId('office-identity')
    await expect(identity).toBeVisible()
    await expect(identity).toContainText('PLATFORM_ADMIN')
    await expect(identity).toContainText(PLATFORM_OFFICE_NAME)
    await expect(identity).toHaveAttribute('data-privileged', 'true')
    await expect(identity).toHaveAttribute('data-office-name', PLATFORM_OFFICE_NAME)

    await identity.click()
    await expect(page.getByRole('menuitemcheckbox', { name: new RegExp(PLATFORM_OFFICE_NAME) })).toBeVisible()
    await expect(page.getByRole('menuitemcheckbox', { name: new RegExp(GENERIC_ACCOUNTANT_OFFICE_NAME) })).toBeVisible()
    await expect(page.getByText('Perfil PLATFORM_ADMIN', { exact: true })).toBeVisible()
    await expect(page.getByText(`Atuando em: ${PLATFORM_OFFICE_NAME}`, { exact: true })).toBeVisible()
  })

  test('troca explicitamente para o Contador Genérico sem criar terceira opção', async ({ page }) => {
    await page.goto('/admin')

    await page.getByTestId('office-identity').click()
    await page.getByRole('menuitemcheckbox', { name: new RegExp(GENERIC_ACCOUNTANT_OFFICE_NAME) }).click()

    const identity = page.getByTestId('office-identity')
    await expect(identity).toContainText('PLATFORM_ADMIN')
    await expect(identity).toContainText(GENERIC_ACCOUNTANT_OFFICE_NAME)
    await expect(identity).toHaveAttribute('data-office-name', GENERIC_ACCOUNTANT_OFFICE_NAME)

    await identity.click()
    await expect(page.getByRole('menuitemcheckbox')).toHaveCount(2)
    await expect(page.getByText('Office Sentinela Demo', { exact: true })).toHaveCount(0)
    await expect(page.getByText('Demo Work Sentinel', { exact: true })).toHaveCount(0)
  })
})

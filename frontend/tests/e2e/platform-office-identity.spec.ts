import { expect, test } from '@playwright/test'
import type { Page } from '@playwright/test'
import {
  GENERIC_ACCOUNTANT_OFFICE_NAME,
  installPlatformApiFixtures,
  PLATFORM_OFFICE_NAME
} from './support/api-fixtures'

async function revealOfficeIdentity(page: Page, projectName: string) {
  if (projectName !== 'desktop-1440') {
    await page.getByRole('button', { name: 'Abrir barra lateral' }).click()
  }

  // O shell responsivo mantém a sidebar desktop oculta no DOM e abre outra no slideover.
  // A consulta por papel ignora a cópia oculta e seleciona o gatilho operável.
  return page.getByRole('button', { name: /^Perfil PLATFORM_ADMIN\./ })
}

test.describe('identidade PLATFORM_ADMIN e escritórios demo', () => {
  test.describe.configure({ timeout: 60_000 })

  test.beforeEach(async ({ page }) => {
    await installPlatformApiFixtures(page)
  })

  test('mantém o Office ativo em uma linha e o contexto discreto no rodapé', async ({ page }, testInfo) => {
    await page.goto('/admin')
    await expect(page.getByTestId('admin-platform-panel')).toBeVisible()

    const identity = await revealOfficeIdentity(page, testInfo.project.name)
    await expect(identity).toBeVisible()
    await expect(identity).toContainText(PLATFORM_OFFICE_NAME)
    await expect(identity).not.toContainText('PLATFORM_ADMIN')
    await expect(identity).toHaveAttribute('data-privileged', 'true')
    await expect(identity).toHaveAttribute('data-office-name', PLATFORM_OFFICE_NAME)
    await expect(identity).toHaveAttribute('aria-label', new RegExp(`Perfil PLATFORM_ADMIN\\. Escritório ativo: ${PLATFORM_OFFICE_NAME}`))

    const label = identity.locator('[data-slot="value"]')
    await expect(label).toHaveText(PLATFORM_OFFICE_NAME)
    await expect(label).toHaveCSS('white-space', 'nowrap')
    await expect(identity.locator('[data-slot="trailingIcon"]')).toBeVisible()
    expect(await identity.evaluate(el => el.scrollWidth <= el.clientWidth + 1)).toBe(true)

    await identity.click()
    const search = page.getByRole('combobox', { name: 'Buscar escritório por nome ou slug' })
    const platformOption = page.getByRole('option', { name: new RegExp(PLATFORM_OFFICE_NAME) })
    const accountantOption = page.getByRole('option', { name: new RegExp(GENERIC_ACCOUNTANT_OFFICE_NAME) })
    await expect(search).toBeVisible()
    await expect(platformOption).toBeVisible()
    await expect(accountantOption).toBeVisible()
    const context = page.getByTestId('office-selector-context')
    await expect(context).toHaveAttribute('data-context-style', 'compact')
    await expect(context).toContainText('Plataforma')
    await expect(context).not.toContainText('Atuando em:')
    await expect(context.locator('.sr-only')).toHaveText(`Perfil PLATFORM_ADMIN. Escritório ativo: ${PLATFORM_OFFICE_NAME}.`)

    const triggerBox = await identity.boundingBox()
    const contentBox = await search.locator('xpath=ancestor::*[@data-slot="content"]').boundingBox()
    expect(triggerBox).not.toBeNull()
    expect(contentBox).not.toBeNull()
    expect(contentBox!.width).toBeGreaterThan(triggerBox!.width)

    await search.fill('contador')
    await expect(accountantOption).toBeVisible()
    await expect(platformOption).toHaveCount(0)
  })

  test('troca explicitamente para o Contador Genérico sem criar terceira opção', async ({ page }, testInfo) => {
    await page.goto('/admin')

    const initialIdentity = await revealOfficeIdentity(page, testInfo.project.name)
    await initialIdentity.click()
    const navigated = page.waitForEvent('framenavigated', frame => frame === page.mainFrame())
    await page.getByRole('option', { name: new RegExp(GENERIC_ACCOUNTANT_OFFICE_NAME) }).click()
    await navigated

    const identity = await revealOfficeIdentity(page, testInfo.project.name)
    await expect(identity).toContainText(GENERIC_ACCOUNTANT_OFFICE_NAME)
    await expect(identity).not.toContainText('PLATFORM_ADMIN')
    await expect(identity).toHaveAttribute('data-office-name', GENERIC_ACCOUNTANT_OFFICE_NAME)

    await identity.click()
    await expect(page.getByRole('option')).toHaveCount(2)
    const context = page.getByTestId('office-selector-context')
    await expect(context.getByText('Plataforma', { exact: true })).toBeVisible()
    await expect(context.getByText(GENERIC_ACCOUNTANT_OFFICE_NAME, { exact: true })).toBeVisible()
    await expect(context).not.toContainText('Atuando em:')
    await expect(page.getByText('Office Sentinela Demo', { exact: true })).toHaveCount(0)
    await expect(page.getByText('Demo Work Sentinel', { exact: true })).toHaveCount(0)
  })
})

/**
 * Superfície UI do canal SVRS NFC-e na Sincronização do cliente.
 * Arquétipo Settings — sem HTML/XML remoto; fixtures sintéticas.
 */
import { expect, test } from '@playwright/test'
import { installApiFixtures } from './support/api-fixtures'

test.describe('SVRS NFC-e panel', () => {
  test.beforeEach(async ({ page }) => {
    await installApiFixtures(page, 'ADMIN')
  })

  test('card XML NFC-e via SVRS na sincronização (desktop/mobile)', async ({ page }) => {
    await page.goto('/clients/1/sincronizacao')
    await expect(page.getByTestId('svrs-nfce-panel')).toBeVisible({ timeout: 15_000 })
    await expect(page.getByText('XML NFC-e via SVRS').first()).toBeVisible()
    await expect(page.getByText(/Sem RPA|HTML remoto/i).first()).toBeVisible()
    await expect(page.getByTestId('svrs-nfce-backlog')).toBeVisible()
    await expect(page.locator('iframe')).toHaveCount(0)
    const html = await page.content()
    expect(html).not.toMatch(/<nfeProc|BEGIN CERTIFICATE|downloadXml\(/i)
  })
})

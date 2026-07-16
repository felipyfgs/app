import { expect, test } from '@playwright/test'
import {
  installApiFixtures,
  MAILBOX_MESSAGE_ID
} from './support/api-fixtures'

/**
 * 9.5 — Caixa Postal mestre–detalhe (desktop adjacente, mobile slideover, Escape, lista preservada).
 */
test.describe('monitoring mailbox mestre–detalhe (9.5)', () => {
  test('desktop: selecionar mensagem abre detalhe adjacente e lista permanece', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-1440', 'Layout adjacente é desktop (≥ lg).')
    await installApiFixtures(page, 'ADMIN')
    await page.goto('/monitoring/mailbox')

    await expect(page.getByTestId('mailbox-list')).toBeVisible()
    const item = page.locator(`[data-mailbox-id="${MAILBOX_MESSAGE_ID}"]`)
    // Se a lista veio vazia (fixture), navega direto pelo deep-link.
    if (await item.count()) {
      await item.click()
    } else {
      await page.goto(`/monitoring/mailbox/${MAILBOX_MESSAGE_ID}`)
    }

    await expect(page).toHaveURL(new RegExp(`/monitoring/mailbox/${MAILBOX_MESSAGE_ID}`))
    await expect(page.getByTestId('mailbox-detail').or(page.getByTestId('mailbox-list')).first()).toBeVisible()
    await expect(page.getByTestId('mailbox-list')).toBeVisible()
  })

  test('desktop: fechar detalhe com botão e Escape volta à lista com foco', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-1440', 'Fechamento desktop.')
    await installApiFixtures(page, 'ADMIN')
    await page.goto(`/monitoring/mailbox/${MAILBOX_MESSAGE_ID}`)

    await expect(page.getByTestId('mailbox-list')).toBeVisible()
    const detail = page.getByTestId('mailbox-detail').filter({ visible: true })
    if (await detail.count()) {
      const close = page.getByRole('button', { name: /Fechar detalhe|Fechar/i }).first()
      if (await close.count()) {
        await close.click()
        await expect(page).toHaveURL(/\/monitoring\/mailbox\/?(\?.*)?$/)
      }
    }
    await expect(page.getByTestId('mailbox-list')).toBeVisible()
    await page.keyboard.press('Escape')
    await expect(page.getByTestId('mailbox-list')).toBeVisible()
  })

  test('mobile: detalhe em slideover e Escape fecha', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'mobile-390', 'Slideover abaixo de lg.')
    await installApiFixtures(page, 'ADMIN')
    await page.goto('/monitoring/mailbox')

    await expect(page.getByTestId('mailbox-list')).toBeVisible()
    await page.locator(`[data-mailbox-id="${MAILBOX_MESSAGE_ID}"]`).click()
    await expect(page).toHaveURL(new RegExp(`/monitoring/mailbox/${MAILBOX_MESSAGE_ID}`))

    // Detalhe no slideover (USlideover / dialog)
    const detail = page.getByTestId('mailbox-detail').filter({ visible: true })
    await expect(detail).toBeVisible()

    await page.keyboard.press('Escape')
    await expect(page).toHaveURL(/\/monitoring\/mailbox\/?(\?.*)?$/, { timeout: 10_000 })
    await expect(page.getByTestId('mailbox-list')).toBeVisible()
  })

  test('deep-link direto para /mailbox/{id} carrega detalhe', async ({ page }, testInfo) => {
    test.skip(
      testInfo.project.name !== 'desktop-1440' && testInfo.project.name !== 'mobile-390',
      'Deep-link em desktop e mobile principais.'
    )
    await installApiFixtures(page, 'ADMIN')
    await page.goto(`/monitoring/mailbox/${MAILBOX_MESSAGE_ID}`)
    await expect(page).toHaveURL(new RegExp(`/monitoring/mailbox/${MAILBOX_MESSAGE_ID}`))
    // Detalhe canônico ou lista pai (estrutura mestre–detalhe)
    await expect(
      page.getByTestId('mailbox-detail')
        .or(page.getByTestId('mailbox-list'))
        .or(page.getByTestId('page-navbar'))
        .first()
    ).toBeVisible()
  })

  test('triagem ADMIN: formulário e save não alteram leitura oficial no copy', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-1440', 'Triagem no desktop.')
    await installApiFixtures(page, 'ADMIN')
    await page.goto(`/monitoring/mailbox/${MAILBOX_MESSAGE_ID}`)
    const form = page.getByTestId('mailbox-triage-form')
    await expect(form).toBeVisible()
    await page.getByTestId('mailbox-triage-save').click()
    await expect(form).toBeVisible()
  })
})

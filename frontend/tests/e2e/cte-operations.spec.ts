import { expect, test } from '@playwright/test'
import {
  FISCAL_OFFICE_B_NAME,
  installApiFixtures
} from './support/api-fixtures'

/** Cold-start do Vite/SPA no container pode ultrapassar 20s no 1º paint. */
const SPA_READY = 60_000

async function gotoCteReady(page: import('@playwright/test').Page, path: string) {
  await page.goto(path, { waitUntil: 'domcontentloaded', timeout: SPA_READY })
  // Aguarda o shell autenticado sem OR amplo (evita strict-mode com heading+tabela).
  await expect(page.getByTestId('office-identity')).toBeVisible({ timeout: SPA_READY })
}

test.describe('operações CT-e', () => {
  // Overlay Vite/HMR e cold-start do dev server ocasionalmente mascaram o 1º teste.
  // SPA cold-start + fixtures superam o default 30s com frequência.
  test.describe.configure({ retries: 1, timeout: SPA_READY * 2 })

  for (const role of ['ADMIN', 'OPERATOR', 'VIEWER'] as const) {
    test(`${role} consulta contexto CT-e no catálogo sem material sensível`, async ({ page }, testInfo) => {
      test.skip(testInfo.project.name !== 'desktop-1440', 'Matriz de papel validada uma vez no desktop.')
      await installApiFixtures(page, role)
      await gotoCteReady(page, '/docs/catalog?kind=CTE')

      await expect(page).toHaveURL(/\/docs\/catalog/, { timeout: SPA_READY })
      await expect(page.getByTestId('cte-catalog-context')).toBeVisible({ timeout: SPA_READY })
      await expect(page.getByText('Contexto CT-e', { exact: true })).toBeVisible()
      await expect(page.getByText('O autXML não é retroativo')).toBeVisible()
      await expect(page.getByTestId('cte-office-cnpj')).toContainText('12.ABC.345/6789-00')
      await expect(page.getByTestId('cte-a1-metadata')).toContainText(/Válido até/i)
      await expect(page.locator('body')).not.toContainText(/PRIVATE KEY|senha do certificado|BEGIN CERTIFICATE|vault_object/i)
      // Nunca oferecer botão de portal automático.
      await expect(page.getByRole('button', { name: /abrir portal|gov\.br|CAPTCHA/i })).toHaveCount(0)

      const adminAction = page.getByRole('link', { name: 'Administrar identidade' })
      if (role === 'ADMIN') await expect(adminAction).toBeVisible()
      else await expect(adminAction).toHaveCount(0)

      // Navegação não aponta para a rota antiga de Configurações.
      await expect(page.locator('a[href="/settings/cte"]')).toHaveCount(0)
    })

    test(`${role} vê pendências com ações conforme papel`, async ({ page }, testInfo) => {
      test.skip(testInfo.project.name !== 'desktop-1440', 'Matriz de papel validada uma vez no desktop.')
      await installApiFixtures(page, role)
      await gotoCteReady(page, '/docs/catalog?kind=CTE')

      await expect(page.getByTestId('cte-pending-panel')).toBeVisible({ timeout: SPA_READY })
      await expect(page.getByTestId('cte-pending-item')).toBeVisible()
      await expect(page.getByText('Emitente sem vínculo no escritório')).toBeVisible()
      await expect(page.getByRole('button', { name: /abrir portal|gov\.br/i })).toHaveCount(0)

      // aria-label completo: "Marcar pendência {id} como resolvida"
      const resolveBtn = page.getByRole('button', { name: /Marcar pendência .* como resolvida|Marcar resolvido/i })
      if (role === 'VIEWER') {
        await expect(page.getByText('Somente leitura')).toBeVisible()
        await expect(resolveBtn).toHaveCount(0)
      } else {
        await expect(resolveBtn).toBeVisible()
        await resolveBtn.click()
        await expect(page.getByTestId('cte-pending-item')).toHaveCount(0, { timeout: 10_000 })
      }
    })
  }

  test('/settings/cte redireciona para o catálogo filtrado', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-1440', 'Redirect legado validado no desktop.')
    await installApiFixtures(page, 'OPERATOR')
    await page.goto('/settings/cte', { waitUntil: 'domcontentloaded', timeout: SPA_READY })
    await expect(page).toHaveURL(/\/docs\/catalog\?.*kind=CTE/, { timeout: SPA_READY })
    await expect(page.getByTestId('cte-catalog-context')).toBeVisible({ timeout: SPA_READY })
    // Não permanece em shell de Configurações.
    await expect(page.getByTestId('settings-panel')).toHaveCount(0)
    await expect(page.getByTestId('cte-onboarding-page')).toHaveCount(0)
  })

  test('troca de escritório descarta o CNPJ CT-e anterior', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-1440', 'Isolamento validado no desktop.')
    // switchTo faz location.assign (reload completo) — precisa de teto maior que o default 30s.
    test.setTimeout(SPA_READY * 2)
    await installApiFixtures(page, 'ADMIN')
    await gotoCteReady(page, '/docs/catalog?kind=CTE')
    await expect(page.getByTestId('cte-catalog-context')).toBeVisible({ timeout: SPA_READY })
    await expect(page.getByTestId('cte-office-cnpj')).toContainText('12.ABC.345/6789-00')
    await expect(page.getByTestId('cte-pending-item')).toBeVisible()

    await page.getByTestId('office-identity').click()
    await page.getByText(FISCAL_OFFICE_B_NAME, { exact: true }).first().click()
    // Reload pós-troca: re-hidrata o catálogo com kind=CTE e novo tenant.
    await expect(page.getByTestId('office-identity')).toContainText(FISCAL_OFFICE_B_NAME, { timeout: SPA_READY })
    await expect(page).toHaveURL(/\/docs\/catalog/, { timeout: SPA_READY })
    await expect(page.getByTestId('cte-catalog-context')).toBeVisible({ timeout: SPA_READY })
    await expect(page.getByTestId('cte-office-cnpj')).toContainText('98.XYZ.765/4321-00', { timeout: SPA_READY })
    await expect(page.getByTestId('cte-office-cnpj')).not.toContainText('12.ABC.345/6789-00')
    await expect(page.getByText('Nenhuma pendência CT-e aberta')).toBeVisible({ timeout: SPA_READY })
  })

  test('sincronizações distingue canais e deep-link documental para o catálogo', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-1440', 'Superfície validada no desktop.')
    await installApiFixtures(page, 'VIEWER')
    await gotoCteReady(page, '/syncs')
    await expect(page.getByTestId('cte-channel-health')).toBeVisible({ timeout: SPA_READY })
    await expect(page.getByText('CT-e dos clientes', { exact: true })).toBeVisible()
    await expect(page.getByText('CT-e autXML do escritório', { exact: true })).toBeVisible()
    await expect(page.getByTestId('cte-client-channel-state')).toContainText(/Ocioso|Ativo/i, { timeout: SPA_READY })
    await expect(page.getByTestId('cte-office-channel-state')).toContainText(/Quiet|fila/i)
    await expect(page.locator('a[href="/settings/cte"]')).toHaveCount(0)
    await expect(page.getByRole('link', { name: 'Abrir documentos CT-e' })).toHaveAttribute(
      'href',
      /\/docs\/catalog\?kind=CTE/
    )
  })

  test('catálogo misto e filtrado por CT-e com cobertura e sem segredos', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-1440', 'Superfície validada no desktop.')
    await installApiFixtures(page, 'OPERATOR')
    await gotoCteReady(page, '/docs/catalog')

    await expect(page.getByTestId('data-table')).toBeVisible({ timeout: SPA_READY })
    await expect(page.getByRole('button', { name: 'CT-e nº 57', exact: true })).toBeVisible()
    await expect(page.getByText('Oficial redigido').first()).toBeVisible()

    await page.getByRole('button', { name: /Filtros/i }).click()
    await expect(page.getByText('Cobertura CT-e', { exact: true })).toBeVisible()
    await page.getByLabel('Filtrar por cobertura CT-e').click()
    await expect(page.getByRole('option', { name: 'Lacuna histórica' })).toBeVisible()
    await expect(page.getByRole('option', { name: 'Sem atividade observada' })).toBeVisible()
    await expect(page.getByRole('option', { name: 'Sem atividade confirmada' })).toHaveCount(0)
    await expect(page.getByRole('option', { name: 'Canal degradado' })).toHaveCount(0)
    await page.keyboard.press('Escape')

    await page.getByRole('button', { name: 'CT-e nº 57', exact: true }).click()
    await expect(page.getByText('Visão oficial redigida via autXML', { exact: true })).toBeVisible({ timeout: SPA_READY })
    await expect(page.locator('body')).not.toContainText(/PRIVATE KEY|BEGIN CERTIFICATE/i)
  })

  test('lote misto NF-e/CT-e mostra rótulos de modelo', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-1440', 'Superfície validada no desktop.')
    await installApiFixtures(page, 'ADMIN')
    await gotoCteReady(page, '/docs/imports/batch-fixture-001')

    await expect(page.getByTestId('batch-items-table')).toBeVisible({ timeout: SPA_READY })
    await expect(page.getByText('CT-e · modelo 57', { exact: true })).toBeVisible()
    await expect(page.getByText('NF-e · modelo 55', { exact: true }).first()).toBeVisible()
    await expect(page.locator('body')).not.toContainText(/BEGIN CERTIFICATE|PRIVATE KEY/i)
  })
})

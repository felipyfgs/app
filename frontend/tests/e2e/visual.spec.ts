import { expect, test } from '@playwright/test'
import { installApiFixtures, NOTE_ACCESS_KEY, stabilizeVisualPage } from './support/api-fixtures'

async function openStable(page: Parameters<typeof installApiFixtures>[0], path: string, heading: string) {
  await page.goto(path)
  await expect(page.getByRole('heading', { name: heading, exact: true })).toBeVisible()
  await stabilizeVisualPage(page)
}

test.describe('regressão visual por zonas', () => {
  test.beforeEach(async ({ page }) => {
    await installApiFixtures(page, 'ADMIN')
  })

  test('shell, navbar, toolbar e conteúdo do dashboard', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'minimum-360', 'Snapshots aprovados usam desktop e mobile de 390 px.')
    await openStable(page, '/', 'Dashboard')

    if (testInfo.project.name === 'desktop-1440') {
      await expect(page.getByTestId('shell-sidebar')).toHaveScreenshot('shell-sidebar.png')
    }
    await expect(page.getByTestId('page-navbar')).toHaveScreenshot('dashboard-navbar.png')
    await expect(page.getByTestId('page-toolbar')).toHaveScreenshot('dashboard-toolbar.png')
    await expect(page.getByTestId('home-stats')).toHaveScreenshot('dashboard-stats.png')
    await expect(page.getByTestId('home-operations')).toHaveScreenshot('dashboard-operations.png')
    await expect(page.getByTestId('home-totals')).toHaveScreenshot('dashboard-totals.png')
  })

  test('tabela e modal básico de clientes', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'minimum-360', 'Snapshots aprovados usam desktop e mobile de 390 px.')
    await openStable(page, '/clients', 'Clientes')

    await expect(page.getByTestId('page-navbar')).toHaveScreenshot('clients-navbar.png')
    await expect(page.getByTestId('data-table')).toHaveScreenshot('clients-table.png')

    await page.getByTestId('page-navbar').getByRole('button', { name: 'Novo cliente' }).click()
    const dialog = page.getByRole('dialog', { name: 'Novo cliente' })
    await expect(dialog).toBeVisible()
    await expect(dialog).toHaveScreenshot('clients-create-modal.png')
  })

  test('detalhe fiscal sanitizado', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-1440', 'O detalhe adjacente é a zona canônica de desktop.')
    await openStable(page, `/docs/${NOTE_ACCESS_KEY}`, 'Documentos')

    const detail = page.getByTestId('note-detail')
    await expect(detail).toBeVisible()
    await expect(detail).toHaveScreenshot('notes-detail.png')
  })

  for (const list of [
    { path: '/exports', heading: 'Exportações', slug: 'exports', action: 'Nova exportação' },
    { path: '/syncs', heading: 'Sincronizações', slug: 'syncs', action: 'Ver detalhes da execução' }
  ] as const) {
    test(`lista e overlay de ${list.heading}`, async ({ page }, testInfo) => {
      test.skip(testInfo.project.name === 'minimum-360', 'Snapshots aprovados usam desktop e mobile de 390 px.')
      await openStable(page, list.path, list.heading)

      await expect(page.getByTestId('page-navbar')).toHaveScreenshot(`${list.slug}-navbar.png`)
      await expect(page.getByTestId('data-table')).toHaveScreenshot(`${list.slug}-table.png`)

      await page.getByRole('button', { name: list.action }).first().click()
      const dialog = page.getByRole('dialog')
      await expect(dialog).toBeVisible()
      await expect(dialog).toHaveScreenshot(`${list.slug}-overlay.png`)
    })
  }

  test('settings de cliente e administração', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'minimum-360', 'Snapshots aprovados usam desktop e mobile de 390 px.')
    await openStable(page, '/clients/1', 'Cliente Demonstração Segura')
    await expect(page.getByTestId('settings-panel')).toHaveScreenshot('client-settings.png')

    await page.getByRole('link', { name: 'Certificado A1', exact: true }).click()
    await page.getByRole('button', { name: 'Substituir' }).click()
    const credentialDialog = page.getByRole('dialog', { name: 'Enviar certificado A1' })
    await expect(credentialDialog).toBeVisible()
    await expect(credentialDialog).toHaveScreenshot('client-credential-modal.png')

    await openStable(page, '/admin', 'Administração')
    await expect(page.getByTestId('settings-panel')).toHaveScreenshot('admin-settings.png')
  })

  test('catálogo e detalhe móvel de notas', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'minimum-360', 'Snapshots aprovados usam desktop e mobile de 390 px.')
    await openStable(page, '/docs', 'Documentos')
    await expect(page.getByTestId('page-navbar')).toHaveScreenshot('notes-navbar.png')
    await expect(page.getByTestId('data-table')).toHaveScreenshot('notes-table.png')

    if (testInfo.project.name === 'mobile-390') {
      await page.getByRole('button', { name: 'Abrir nota' }).first().click()
      await expect(page).toHaveURL(new RegExp(`/docs/${NOTE_ACCESS_KEY}`))
      const dialog = page.getByRole('dialog')
      await expect(dialog).toBeVisible()
      await expect(dialog).toHaveScreenshot('notes-detail-mobile.png')
    }
  })
})

test.describe('regressão visual escura', () => {
  test('shell e dashboard em modo escuro', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-1440', 'Baseline escura representativa do desktop.')
    await installApiFixtures(page, 'ADMIN', 'dark')
    await openStable(page, '/', 'Dashboard')

    await expect(page.locator('html')).toHaveClass(/dark/)
    await expect(page.getByTestId('shell-sidebar')).toHaveScreenshot('shell-sidebar-dark.png')
    await expect(page.getByTestId('home-stats')).toHaveScreenshot('dashboard-stats-dark.png')
  })
})

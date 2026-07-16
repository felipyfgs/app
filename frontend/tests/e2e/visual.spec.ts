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
    await expect(page.getByTestId('home-work-kpis')).toBeVisible()
    const operations = page.getByTestId('home-operations')
    const totals = page.getByTestId('home-totals')
    await expect(operations).toBeVisible()
    await expect(totals).toBeVisible()
    await expect(operations).toHaveScreenshot('dashboard-operations.png')
    await expect(totals).toHaveScreenshot('dashboard-totals.png')
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

  test('detalhe fiscal sanitizado em modal', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-1440', 'O modal fiscal é validado uma vez no desktop.')
    await page.goto(`/docs/${NOTE_ACCESS_KEY}`)

    const detail = page.getByTestId('note-detail')
    await expect(detail).toBeVisible()
    await stabilizeVisualPage(page)
    await expect(detail).toHaveScreenshot('notes-detail.png')
  })

  for (const list of [
    {
      path: '/exports',
      heading: 'Exportações',
      slug: 'exports',
      action: 'Pedir ZIP',
      overlay: 'Pedir pacote ZIP'
    },
    {
      path: '/syncs',
      heading: 'Sincronizações',
      slug: 'syncs',
      action: 'Ver detalhes da execução',
      overlay: 'Execução #51'
    }
  ] as const) {
    test(`lista e overlay de ${list.heading}`, async ({ page }, testInfo) => {
      test.skip(testInfo.project.name === 'minimum-360', 'Snapshots aprovados usam desktop e mobile de 390 px.')
      await openStable(page, list.path, list.heading)

      await expect(page.getByTestId('page-navbar')).toHaveScreenshot(`${list.slug}-navbar.png`)
      await expect(page.getByTestId('data-table')).toHaveScreenshot(`${list.slug}-table.png`)

      await page.getByRole('button', { name: list.action }).first().click()
      const dialog = page.getByRole('dialog').filter({
        has: page.getByText(list.overlay, { exact: true })
      })
      await expect(dialog).toBeVisible()
      await expect(dialog).toHaveScreenshot(`${list.slug}-overlay.png`)
    })
  }

  test('settings de cliente e administração', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'minimum-360', 'Snapshots aprovados usam desktop e mobile de 390 px.')
    await openStable(page, '/clients/1', 'Cliente Demonstração Segura (MATRIZ)')
    await expect(page.getByTestId('settings-panel')).toHaveScreenshot('client-settings.png')

    await page.getByTestId('client-section-tabs')
      .getByRole('link', { name: 'Certificado A1', exact: true })
      .click()
    await page.getByRole('button', { name: 'Substituir' }).click()
    const credentialDialog = page.getByRole('dialog', { name: 'Enviar certificado A1' })
    await expect(credentialDialog).toBeVisible()
    await expect(credentialDialog).toHaveScreenshot('client-credential-modal.png')

    // Configuração unificada do escritório (antes em /admin para office ADMIN).
    await openStable(page, '/settings', 'Configurações')
    await expect(page.getByTestId('settings-panel')).toHaveScreenshot('admin-settings.png')
  })

  test('catálogo e detalhe móvel de notas', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'minimum-360', 'Snapshots aprovados usam desktop e mobile de 390 px.')
    await page.goto('/docs/catalog')
    const navbar = page.getByTestId('page-navbar')
    await expect(navbar).toBeVisible()
    await expect(navbar).toContainText('Documentos')
    await stabilizeVisualPage(page)
    await expect(navbar).toHaveScreenshot('notes-navbar.png')
    const table = page.locator('#dashboard-panel-docs').getByRole('table')
    await expect(table).toHaveScreenshot('notes-table.png')

    if (testInfo.project.name === 'mobile-390') {
      const noteAction = page.getByRole('button', { name: 'NFS-e nº 1001', exact: true })
      await expect(noteAction).toHaveAttribute('title', NOTE_ACCESS_KEY)
      await noteAction.click()
      await expect(page).toHaveURL(new RegExp(`/docs/${NOTE_ACCESS_KEY}`))
      const dialog = page.getByRole('dialog')
      await expect(dialog).toBeVisible()
      await expect(dialog).toHaveScreenshot('notes-detail-mobile.png')
    }
  })

  for (const list of [
    { path: '/health', heading: 'Saúde operacional', slug: 'health' },
    { path: '/docs/imports', heading: 'Importações XML/ZIP', slug: 'imports' },
    { path: '/closing', heading: 'Fechamento de saídas', slug: 'closing' }
  ] as const) {
    test(`superfície tabular de ${list.heading}`, async ({ page }, testInfo) => {
      test.skip(testInfo.project.name === 'minimum-360', 'A largura de 360 px é validada pela suíte autenticada.')
      await openStable(page, list.path, list.heading)

      await expect(page.getByTestId('page-navbar')).toHaveScreenshot(`${list.slug}-navbar.png`)
      const table = page.getByRole('table').first()
      await expect(table).toHaveScreenshot(`${list.slug}-table.png`)
    })
  }
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

import { expect, test } from '@playwright/test'
import { installApiFixtures, stabilizeVisualPage } from './support/api-fixtures'
import { MAILBOX_MESSAGE_ID } from './support/monitoring-fixtures'
// MAILBOX_MESSAGE_ID = FISCAL_MAILBOX_MESSAGE_ID (9001)

/** Carteiras e superfícies críticas do hub de monitoramento. */
const MONITORING_ROUTES = [
  { path: '/monitoring', heading: 'Dashboard Fiscal', slug: 'monitoring-dashboard', zones: ['navbar', 'toolbar', 'kpis'] as const },
  { path: '/monitoring/simples-mei', heading: 'Simples Nacional / MEI', slug: 'simples-mei', zones: ['navbar', 'toolbar', 'table'] as const },
  { path: '/monitoring/dctfweb', heading: 'DCTFWeb / MIT', slug: 'dctfweb', zones: ['navbar', 'toolbar', 'table'] as const },
  { path: '/monitoring/installments', heading: 'Parcelamentos', slug: 'installments', zones: ['navbar', 'toolbar', 'table'] as const },
  { path: '/monitoring/sitfis', heading: 'Situação Fiscal', slug: 'sitfis', zones: ['navbar', 'toolbar', 'table'] as const },
  { path: '/monitoring/mailbox', heading: 'Caixas Postais', slug: 'mailbox', zones: ['navbar', 'toolbar', 'list'] as const },
  { path: '/monitoring/declarations', heading: 'Declarações', slug: 'declarations', zones: ['navbar', 'toolbar', 'table'] as const },
  { path: '/monitoring/guides', heading: 'Guias', slug: 'guides', zones: ['navbar', 'toolbar', 'table'] as const },
  { path: '/monitoring/fgts', heading: 'FGTS (parcial eSocial)', slug: 'fgts', zones: ['navbar', 'toolbar', 'table'] as const },
  { path: '/monitoring/clients/1', heading: 'Cliente Demonstração Segura', slug: 'client-detail', zones: ['panel'] as const }
] as const

async function openStable(
  page: Parameters<typeof installApiFixtures>[0],
  path: string,
  heading: string
) {
  await page.goto(path)
  await expect(page.getByRole('heading', { name: heading, exact: true })).toBeVisible({ timeout: 20_000 })
  await stabilizeVisualPage(page)
}

async function noDocumentOverflow(page: Parameters<typeof installApiFixtures>[0]) {
  const overflow = await page.evaluate(() => {
    const el = document.scrollingElement || document.documentElement
    return el.scrollWidth > el.clientWidth + 1
  })
  expect(overflow).toBe(false)
}

test.describe('monitoramento — regressão visual por zonas', () => {
  test.beforeEach(async ({ page }) => {
    await installApiFixtures(page, 'ADMIN')
  })

  for (const route of MONITORING_ROUTES) {
    test(`${route.slug}: zonas críticas`, async ({ page }, testInfo) => {
      test.skip(
        testInfo.project.name === 'minimum-360',
        'Snapshots aprovados usam desktop-1440 e mobile-390; overflow em 9.8.'
      )
      if (route.slug === 'mailbox') {
        await page.goto(route.path)
        await expect(page).toHaveURL(/\/monitoring\/mailbox\/?(?:\?.*)?$/)
        await expect(page.getByTestId('mailbox-list').filter({ visible: true })).toBeVisible({
          timeout: 20_000
        })
        await stabilizeVisualPage(page)
      } else {
        await openStable(page, route.path, route.heading)
      }

      if (route.zones.includes('navbar')) {
        await expect(page.getByTestId('page-navbar').filter({ visible: true }).first()).toHaveScreenshot(`${route.slug}-navbar.png`)
      }
      if (route.zones.includes('toolbar')) {
        const toolbar = page.getByTestId('page-toolbar').filter({ visible: true })
        if (await toolbar.count()) {
          await expect(toolbar.first()).toHaveScreenshot(`${route.slug}-toolbar.png`)
        }
      }
      if (route.zones.includes('kpis')) {
        await expect(page.getByTestId('fiscal-kpis')).toHaveScreenshot(`${route.slug}-kpis.png`)
      }
      if (route.zones.includes('banner')) {
        const banner = page.getByTestId('fgts-partial-banner')
        if (await banner.count()) {
          await expect(banner).toHaveScreenshot(`${route.slug}-banner.png`)
        }
      }
      if (route.zones.includes('table')) {
        // Algumas tabelas responsivas mantêm uma cópia não renderizada no DOM.
        // O baseline deve representar a superfície efetivamente exibida.
        const table = page.getByTestId('fiscal-table').filter({ visible: true })
        if (await table.count()) {
          await expect(table.first()).toHaveScreenshot(`${route.slug}-table.png`)
        }
      }
      if (route.zones.includes('list')) {
        const list = page.getByTestId('mailbox-list').filter({ visible: true })
        await expect(list).toBeVisible()
        await expect(list).toHaveScreenshot(`${route.slug}-list.png`)
      }
      if (route.zones.includes('panel')) {
        await expect(page.getByTestId('settings-panel')).toHaveScreenshot(`${route.slug}-panel.png`)
      }
    })
  }

  test('mailbox detalhe e overlay mobile', async ({ page }, testInfo) => {
    test.skip(
      testInfo.project.name === 'minimum-360',
      'Snapshots de detalhe em desktop e mobile-390.'
    )
    await page.goto(`/monitoring/mailbox/${MAILBOX_MESSAGE_ID}`)
    const detail = page.getByTestId('mailbox-detail').filter({ visible: true })
    await expect(detail).toBeVisible({ timeout: 20_000 })
    await expect(detail.getByRole('heading', { level: 1 })).toBeVisible()
    await stabilizeVisualPage(page)
    await expect(detail).toHaveScreenshot('mailbox-detail.png')
  })

  test('sitfis slideover de achados (desktop)', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-1440', 'Overlay de achados validado no desktop.')
    await openStable(page, '/monitoring/sitfis', 'Situação Fiscal')
    const openBtn = page
      .getByTestId('fiscal-table')
      .filter({ visible: true })
      .getByRole('button', { name: 'Achados', exact: true })
      .first()
    await expect(openBtn).toBeVisible()
    await openBtn.click()
    const dialog = page.getByRole('dialog')
    await expect(dialog).toBeVisible({ timeout: 10_000 })
    await stabilizeVisualPage(page)
    await expect(dialog).toHaveScreenshot('sitfis-findings-overlay.png')
  })
})

test.describe('monitoramento — overflow 360px', () => {
  test.beforeEach(async ({ page }) => {
    await installApiFixtures(page, 'ADMIN')
  })

  for (const route of MONITORING_ROUTES) {
    test(`${route.slug} sem overflow horizontal do documento`, async ({ page }, testInfo) => {
      test.skip(testInfo.project.name !== 'minimum-360', 'Inspeção dedicada ao projeto minimum-360.')
      await page.goto(route.path)
      await expect(page.getByRole('heading', { name: route.heading, exact: true })).toBeVisible({
        timeout: 20_000
      })
      await stabilizeVisualPage(page)
      await noDocumentOverflow(page)
    })
  }

  test('dctfweb mantém título, tabs e ações acessíveis no mobile', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'minimum-360', 'Inspeção dedicada ao projeto minimum-360.')
    await page.goto('/monitoring/dctfweb')

    await expect(page.getByRole('heading', { name: 'DCTFWeb / MIT', exact: true })).toBeVisible({
      timeout: 20_000
    })
    await expect(page.getByTestId('monitoring-module-nav')).toBeVisible()
    await expect(page.getByTestId('portfolio-actions-mobile')).toBeVisible()
    await expect(page.getByTestId('action-add-client')).toBeHidden()

    const search = page.getByTestId('fiscal-filter-q')
    const searchBox = await search.boundingBox()
    expect(searchBox?.width).toBeGreaterThanOrEqual(300)

    const filtersToggle = page.getByTestId('mobile-filters-toggle')
    await expect(filtersToggle).toHaveAttribute('aria-expanded', 'false')
    await expect(page.getByTestId('fiscal-filter-situation')).toBeHidden()
    await filtersToggle.click()
    await expect(filtersToggle).toHaveAttribute('aria-expanded', 'true')

    const stackedFilters = [
      page.getByTestId('fiscal-filter-situation'),
      page.getByTestId('fiscal-filter-competence')
    ]
    for (const filter of stackedFilters) {
      await expect(filter).toBeVisible()
      const box = await filter.boundingBox()
      expect(box?.width).toBeGreaterThanOrEqual(300)
    }

    const controlsFitViewport = await page.getByTestId('page-toolbar').evaluate((toolbar) => {
      const viewportWidth = document.documentElement.clientWidth
      return [...toolbar.querySelectorAll<HTMLElement>('input, button')]
        .filter(element => element.getClientRects().length > 0)
        .every((element) => {
          const rect = element.getBoundingClientRect()
          return rect.left >= 0 && rect.right <= viewportWidth + 1
        })
    })
    expect(controlsFitViewport).toBe(true)
    await noDocumentOverflow(page)
  })

  test('mailbox detalhe sem overflow horizontal', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'minimum-360', 'Inspeção dedicada ao projeto minimum-360.')
    await page.goto(`/monitoring/mailbox/${MAILBOX_MESSAGE_ID}`)
    const detail = page.getByTestId('mailbox-detail').filter({ visible: true })
    await expect(detail).toBeVisible({ timeout: 20_000 })
    await expect(detail.getByRole('heading', { level: 1 })).toBeVisible()
    await stabilizeVisualPage(page)
    await noDocumentOverflow(page)
  })
})

test.describe('monitoramento — filtros responsivos', () => {
  test.beforeEach(async ({ page }) => {
    await installApiFixtures(page, 'ADMIN')
  })

  test('dctfweb mantém filtros abertos no desktop', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-1440', 'Inspeção dedicada ao desktop.')
    await page.goto('/monitoring/dctfweb')

    await expect(page.getByRole('heading', { name: 'DCTFWeb / MIT', exact: true })).toBeVisible({
      timeout: 20_000
    })
    await expect(page.getByTestId('mobile-filters-toggle')).toBeHidden()
    await expect(page.getByTestId('fiscal-filter-situation')).toBeVisible()
    await expect(page.getByTestId('fiscal-filter-competence')).toBeVisible()
  })
})

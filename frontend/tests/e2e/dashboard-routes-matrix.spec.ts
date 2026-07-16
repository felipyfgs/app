/**
 * Matriz Playwright canônica (tarefa 12.3+): visita páginas autenticadas
 * em desktop e mobile com fixtures sintéticas.
 */
import { test, expect } from '@playwright/test'
import { installApiFixtures } from './support/api-fixtures'

const DESKTOP = { width: 1440, height: 900 }
const MOBILE = { width: 390, height: 844 }

/** Rotas canônicas autenticadas (shell default). */
const ROUTES = [
  '/',
  '/clients',
  '/clients/dashboard',
  '/docs',
  '/docs/catalog',
  '/docs/imports',
  '/monitoring',
  '/monitoring/simples-mei',
  '/monitoring/mailbox',
  '/work',
  '/work/calendar',
  '/work/processes',
  '/closing',
  '/exports',
  '/syncs',
  '/health',
  '/settings',
  '/settings/usage'
] as const

const REDIRECTS = [
  { from: '/notes', expectPath: /\/docs/ },
  { from: '/notes/invalid-key', expectPath: /\/docs/ },
  { from: '/docs/import-batches', expectPath: /\/docs\/imports/ }
] as const

async function softAuthShell(page: import('@playwright/test').Page) {
  // Mesmo contrato de authenticated.spec.ts: role + fixtures sintéticas.
  await installApiFixtures(page, 'ADMIN', 'light', 'ready')
}

test.describe('matriz de rotas autenticadas', () => {
  for (const viewport of [
    { name: 'desktop', size: DESKTOP },
    { name: 'mobile', size: MOBILE }
  ]) {
    test.describe(viewport.name, () => {
      test.use({ viewport: viewport.size })

      for (const path of ROUTES) {
        test(`${path} carrega sem material sensível`, async ({ page }) => {
          await softAuthShell(page)
          await page.goto(path)
          await page.waitForLoadState('domcontentloaded')
          const body = await page.locator('body').innerText()
          expect(body).not.toMatch(/BEGIN (RSA |EC )?PRIVATE KEY/)
          expect(body).not.toMatch(/vault_object_id\s*[:=]/i)
          expect(body).not.toMatch(/consumer[_-]?secret\s*[:=]/i)
          expect(body).not.toMatch(/<\?xml[\s\S]{0,200}<(?:NFe|CTe|InfNFSe)\b/)
          // Sem overflow horizontal em mobile
          if (viewport.name === 'mobile') {
            const overflow = await page.evaluate(() =>
              document.documentElement.scrollWidth > document.documentElement.clientWidth + 2
            )
            expect(overflow).toBe(false)
          }
        })
      }
    })
  }
})

test.describe('redirects legados', () => {
  for (const { from, expectPath } of REDIRECTS) {
    test(`${from} redireciona`, async ({ page }) => {
      await softAuthShell(page)
      await page.goto(from)
      await expect(page).toHaveURL(expectPath)
    })
  }
})

test.describe('overflow 360px em superfícies densas', () => {
  test.use({ viewport: { width: 360, height: 800 } })

  for (const path of ['/login', '/clients', '/work/calendar', '/monitoring', '/docs/catalog'] as const) {
    test(`${path} sem scroll horizontal obrigatório`, async ({ page }) => {
      await softAuthShell(page)
      await page.goto(path)
      await page.waitForLoadState('domcontentloaded')
      const overflow = await page.evaluate(() =>
        document.documentElement.scrollWidth > document.documentElement.clientWidth + 2
      )
      expect(overflow).toBe(false)
    })
  }
})

test.describe('contratos de composição mobile', () => {
  test.use({ viewport: MOBILE })

  test('Clientes mantém KPIs, filtros e tabela no fluxo natural do mobile', async ({ page }) => {
    await softAuthShell(page)
    await page.goto('/clients')

    const kpis = page.getByTestId('clients-stats')
    const filters = page.locator('[data-dashboard-table-filters]')
    const table = page.getByTestId('data-table')
    await expect(kpis).toBeVisible()
    await expect(filters).toHaveAttribute('data-dashboard-table-filters-placement', 'body')
    await expect(table).toBeVisible()
    await expect(page.getByLabel(/Mais ações de /).first()).toBeVisible()

    const kpiBox = await kpis.boundingBox()
    const filtersBox = await filters.boundingBox()
    const tableBox = await table.boundingBox()
    expect(kpiBox?.height ?? Number.POSITIVE_INFINITY).toBeLessThan(300)
    expect(filtersBox?.y ?? 0).toBeGreaterThanOrEqual((kpiBox?.y ?? 0) + (kpiBox?.height ?? 0))
    expect(tableBox?.y ?? 0).toBeGreaterThanOrEqual((filtersBox?.y ?? 0) + (filtersBox?.height ?? 0))

    const dimensions = await table.evaluate(element => ({
      clientWidth: element.clientWidth,
      scrollWidth: element.scrollWidth,
      clientHeight: element.clientHeight,
      scrollHeight: element.scrollHeight,
      overflowY: getComputedStyle(element).overflowY
    }))
    expect(dimensions.scrollWidth).toBeLessThanOrEqual(dimensions.clientWidth + 1)
    expect(dimensions.overflowY).toBe('visible')
    expect(dimensions.scrollHeight).toBeLessThanOrEqual(dimensions.clientHeight + 1)

    const stickyHeader = await table.evaluate((element) => {
      const body = element.closest<HTMLElement>('[data-slot="body"]')
      const thead = element.querySelector<HTMLElement>('thead')
      body?.scrollTo({ top: 440, behavior: 'instant' })
      const headerRow = thead?.querySelector<HTMLElement>('tr')
      return {
        bodyTop: body?.getBoundingClientRect().top ?? 0,
        headerTop: thead?.getBoundingClientRect().top ?? Number.POSITIVE_INFINITY,
        zIndex: Number(getComputedStyle(thead!).zIndex),
        background: headerRow ? getComputedStyle(headerRow).backgroundColor : 'transparent'
      }
    })
    expect(stickyHeader.headerTop).toBeLessThanOrEqual(stickyHeader.bodyTop + 1)
    expect(stickyHeader.zIndex).toBeGreaterThanOrEqual(10)
    expect(stickyHeader.background).not.toBe('rgba(0, 0, 0, 0)')
  })

  test('Fila Work preserva título e separa tabs do navbar', async ({ page }) => {
    await softAuthShell(page)
    await page.goto('/work')

    const title = page.getByRole('heading', { name: 'Minha fila', exact: true }).first()
    await expect(title).toBeVisible()
    await expect(page.getByRole('tab', { name: 'Concluídas' })).toBeVisible()
    await expect(page.getByLabel('Buscar na fila')).toBeVisible()

    const titleBox = await title.boundingBox()
    expect(titleBox?.width ?? 0).toBeGreaterThan(60)
  })

  test('Calendário mantém título e controles operáveis em faixas distintas', async ({ page }) => {
    await softAuthShell(page)
    await page.goto('/work/calendar')

    const title = page.getByRole('heading', { name: 'Calendário operacional', exact: true }).first()
    await expect(title).toBeVisible()
    await expect(page.getByLabel('Período anterior').last()).toBeVisible()
    await expect(page.getByRole('tab', { name: 'Semana' }).last()).toBeVisible()
    await expect(page.getByLabel('Abrir painel do dia')).toBeVisible()

    const titleBox = await title.boundingBox()
    expect(titleBox?.width ?? 0).toBeGreaterThan(140)
  })

  test('Navegação fiscal não empurra a atualização para fora da viewport', async ({ page }) => {
    await softAuthShell(page)
    await page.goto('/monitoring')

    const refresh = page.getByLabel('Atualizar dashboard fiscal')
    await expect(refresh).toBeVisible()
    const box = await refresh.boundingBox()
    expect(box).not.toBeNull()
    expect((box?.x ?? 0) + (box?.width ?? 0)).toBeLessThanOrEqual(MOBILE.width)
  })
})

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

import { expect, test } from '@playwright/test'
import { installApiFixtures, NOTE_ACCESS_KEY, stabilizeVisualPage } from './support/api-fixtures'

const routes = [
  { slug: 'dashboard', path: '/', heading: 'Dashboard' },
  { slug: 'clients', path: '/clients', heading: 'Clientes' },
  { slug: 'client', path: '/clients/1', heading: 'Cliente Demonstração Segura' },
  { slug: 'docs', path: '/docs', heading: 'Documentos' },
  { slug: 'doc', path: `/docs/${NOTE_ACCESS_KEY}`, heading: 'Documentos' },
  { slug: 'exports', path: '/exports', heading: 'Exportações' },
  { slug: 'syncs', path: '/syncs', heading: 'Sincronizações' },
  { slug: 'admin', path: '/admin', heading: 'Administração' }
] as const

test('captura diagnóstica do frontend sem promover baseline', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== 'desktop-1440', 'Diagnóstico integral único em desktop.')
  await installApiFixtures(page, 'ADMIN')

  for (const route of routes) {
    await page.goto(route.path)
    await expect(page.getByRole('heading', { name: route.heading, exact: true })).toBeVisible()
    await stabilizeVisualPage(page)
    await page.screenshot({
      path: testInfo.outputPath(`diagnostico-${route.slug}.png`),
      fullPage: true
    })
  }
})

import { expect, test } from '@playwright/test'
import { installApiFixtures, NOTE_ACCESS_KEY, stabilizeVisualPage } from './support/api-fixtures'

const routes = [
  { slug: 'dashboard', path: '/', role: 'heading', name: 'Dashboard' },
  { slug: 'clients', path: '/clients', role: 'heading', name: 'Clientes' },
  { slug: 'client', path: '/clients/1', role: 'heading', name: 'Cliente Demonstração Segura (MATRIZ)' },
  { slug: 'docs', path: '/docs', role: 'heading', name: 'Documentos' },
  { slug: 'doc', path: `/docs/${NOTE_ACCESS_KEY}`, role: 'dialog', name: 'NFS-e nº 1001' },
  { slug: 'exports', path: '/exports', role: 'heading', name: 'Exportações' },
  { slug: 'syncs', path: '/syncs', role: 'heading', name: 'Sincronizações' },
  { slug: 'admin', path: '/admin', role: 'heading', name: 'Administração' }
] as const

test('captura diagnóstica do frontend sem promover baseline', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== 'desktop-1440', 'Diagnóstico integral único em desktop.')
  // O cenário percorre e captura oito superfícies; sob a matriz concorrente o
  // custo agregado excede legitimamente o timeout unitário padrão de 30 s.
  test.setTimeout(120_000)
  await installApiFixtures(page, 'ADMIN')

  for (const route of routes) {
    await page.goto(route.path)
    await expect(page.getByRole(route.role, { name: route.name, exact: true })).toBeVisible()
    await stabilizeVisualPage(page)
    await page.screenshot({
      path: testInfo.outputPath(`diagnostico-${route.slug}.png`),
      fullPage: true
    })
  }
})

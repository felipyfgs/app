import { expect, test } from '@playwright/test'
import type { OfficeRole } from '../../app/types/api'
import type { ListScenario } from './support/api-fixtures'
import { installApiFixtures } from './support/api-fixtures'

const hydrationTimeout = 60_000

const modules = [{
  path: '/monitoring/registrations',
  heading: 'Cadastro e vínculos',
  table: 'registrations-table',
  error: 'registrations-error',
  readyText: 'VINCULO-DEMO-001',
  emptyText: 'Nenhum vínculo projetado',
  refreshName: /Atualizar vínculos do cliente 1/
}, {
  path: '/monitoring/tax-processes',
  heading: 'Processos fiscais',
  table: 'tax-processes-table',
  error: 'tax-processes-error',
  readyText: 'PROC-DEMO-001',
  emptyText: 'Nenhum processo projetado',
  refreshName: /Atualizar processos do cliente 1/
}] as const

for (const module of modules) {
  for (const scenario of ['slow', 'empty', 'error'] satisfies ListScenario[]) {
    test(`${module.heading}: estado ${scenario}`, async ({ page }) => {
      test.setTimeout(90_000)
      await installApiFixtures(page, 'ADMIN', 'light', scenario)
      await page.goto(module.path)
      await expect(page.getByRole('heading', { name: module.heading, exact: true })).toBeVisible({ timeout: hydrationTimeout })

      if (scenario === 'slow') {
        await expect(page.getByRole('button', { name: 'Recarregar' })).toBeDisabled()
        await expect(page.getByTestId(module.table)).toContainText(module.readyText)
      } else if (scenario === 'empty') {
        await expect(page.getByText(module.emptyText, { exact: true })).toBeVisible()
      } else {
        await expect(page.getByTestId(module.error)).toContainText('Falha sintética sanitizada.')
      }

      const hasHorizontalOverflow = await page.evaluate(() =>
        document.documentElement.scrollWidth > window.innerWidth + 1
      )
      expect(hasHorizontalOverflow).toBe(false)
    })
  }
}

for (const role of ['VIEWER', 'OPERATOR', 'ADMIN'] satisfies OfficeRole[]) {
  test(`permissões e tenancy das listas como ${role}`, async ({ page }) => {
    test.setTimeout(90_000)
    const fiscalRequests: Array<{ url: string, body: string | null }> = []
    page.on('request', (request) => {
      if (request.url().includes('/api/v1/fiscal/')) {
        fiscalRequests.push({ url: request.url(), body: request.postData() })
      }
    })
    await installApiFixtures(page, role)

    for (const module of modules) {
      await page.goto(module.path)
      await expect(page.getByTestId(module.table)).toContainText(module.readyText, { timeout: hydrationTimeout })
      const refresh = page.getByRole('button', { name: module.refreshName })
      if (role === 'VIEWER') {
        await expect(refresh).toHaveCount(0)
      } else {
        const queued = page.waitForRequest(request => (
          request.method() === 'POST' && request.url().includes('/refresh')
        ))
        await refresh.click()
        await queued
      }
    }

    expect(fiscalRequests.length).toBeGreaterThan(0)
    for (const request of fiscalRequests) {
      expect(new URL(request.url).searchParams.has('office_id')).toBe(false)
      expect(request.body || '').not.toMatch(/office_id/i)
    }
  })

  test(`seções Settings como ${role}`, async ({ page }) => {
    test.setTimeout(90_000)
    await installApiFixtures(page, role)
    await page.goto('/monitoring/clients/1?tab=registrations')
    await expect(page.getByTestId('settings-panel')).toBeVisible({ timeout: hydrationTimeout })
    await expect(page.getByText('VINCULO-DEMO-001', { exact: true })).toBeVisible({ timeout: hydrationTimeout })

    await page.goto('/monitoring/clients/1?tab=tax_processes')
    await expect(page.getByText('PROC-DEMO-001', { exact: true })).toBeVisible({ timeout: hydrationTimeout })
  })
}

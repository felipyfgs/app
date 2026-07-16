import { expect, test } from '@playwright/test'
import {
  installApiFixtures,
  LIST_ERROR_MESSAGE,
  type ListScenario
} from './support/api-fixtures'

const lists = [
  {
    path: '/clients',
    panel: '#dashboard-panel-clients',
    heading: 'Clientes',
    empty: 'Nenhum cliente encontrado',
    error: LIST_ERROR_MESSAGE
  },
  {
    path: '/exports',
    panel: '#dashboard-panel-exports',
    heading: 'Exportações',
    empty: 'Nenhum ZIP ainda',
    error: LIST_ERROR_MESSAGE
  },
  {
    path: '/syncs',
    panel: '#dashboard-panel-syncs',
    heading: 'Sincronizações',
    empty: 'Nenhuma execução registrada',
    error: LIST_ERROR_MESSAGE
  }
] as const

for (const list of lists) {
  for (const scenario of ['empty', 'error', 'slow'] satisfies ListScenario[]) {
    test(`${list.heading} diferencia o estado ${scenario}`, async ({ page }, testInfo) => {
      test.skip(testInfo.project.name !== 'desktop-1440', 'Estados funcionais independem da largura.')
      await installApiFixtures(page, 'ADMIN', 'light', scenario)
      await page.goto(list.path)
      const panel = page.locator(list.panel)
      await expect(panel.getByRole('heading', { name: list.heading, exact: true })).toBeVisible()

      if (scenario === 'empty') {
        await expect(panel.getByRole('heading', { name: list.empty, exact: true })).toBeVisible()
        await expect(panel.getByText(list.error, { exact: true })).toHaveCount(0)
      } else if (scenario === 'error') {
        await expect(panel.getByText(list.error, { exact: true })).toBeVisible()
        await expect(panel.getByRole('heading', { name: list.empty, exact: true })).toHaveCount(0)
      } else {
        await expect(panel.getByTestId('data-table')).toBeVisible()
        await expect(panel.getByRole('heading', { name: list.empty, exact: true })).toBeHidden()
      }
    })
  }
}

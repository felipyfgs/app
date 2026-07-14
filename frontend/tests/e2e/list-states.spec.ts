import { expect, test } from '@playwright/test'
import { installApiFixtures, type ListScenario } from './support/api-fixtures'

const lists = [
  {
    path: '/clients',
    heading: 'Clientes',
    empty: 'Nenhum cliente encontrado',
    error: 'Não foi possível carregar clientes'
  },
  {
    path: '/exports',
    heading: 'Exportações',
    empty: 'Nenhuma exportação',
    error: 'Não foi possível carregar exportações'
  },
  {
    path: '/syncs',
    heading: 'Sincronizações',
    empty: 'Nenhuma execução registrada',
    error: 'Não foi possível carregar sincronizações'
  }
] as const

for (const list of lists) {
  for (const scenario of ['empty', 'error', 'slow'] satisfies ListScenario[]) {
    test(`${list.heading} diferencia o estado ${scenario}`, async ({ page }, testInfo) => {
      test.skip(testInfo.project.name !== 'desktop-1440', 'Estados funcionais independem da largura.')
      await installApiFixtures(page, 'ADMIN', 'light', scenario)
      await page.goto(list.path)
      await expect(page.getByRole('heading', { name: list.heading, exact: true })).toBeVisible()

      if (scenario === 'empty') {
        await expect(page.getByRole('heading', { name: list.empty, exact: true })).toBeVisible()
        await expect(page.getByText(list.error, { exact: true })).toHaveCount(0)
      } else if (scenario === 'error') {
        await expect(page.getByText(list.error, { exact: true })).toBeVisible()
        await expect(page.getByRole('heading', { name: list.empty, exact: true })).toHaveCount(0)
      } else {
        await expect(page.getByTestId('data-table')).toBeVisible()
        await expect(page.getByRole('heading', { name: list.empty, exact: true })).toBeHidden()
      }
    })
  }
}

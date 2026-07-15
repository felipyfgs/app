import { expect, test } from '@playwright/test'
import {
  installApiFixtures,
  NOTE_ACCESS_KEY,
  SECOND_NOTE_ACCESS_KEY,
  type ListScenario
} from './support/api-fixtures'

for (const scenario of ['empty', 'error', 'slow'] satisfies ListScenario[]) {
  test(`Documentos diferencia o estado ${scenario}`, async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-1440', 'Estados funcionais independem da largura.')
    await installApiFixtures(page, 'ADMIN', 'light', scenario)
    await page.goto('/docs')
    await expect(page.getByRole('heading', { name: 'Documentos', exact: true })).toBeVisible()

    if (scenario === 'empty') {
      await expect(page.getByRole('heading', { name: 'Nenhuma nota encontrada', exact: true })).toBeVisible()
      await expect(page.getByText('Não foi possível carregar documentos', { exact: true })).toHaveCount(0)
    } else if (scenario === 'error') {
      await expect(page.getByText('Não foi possível carregar documentos', { exact: true })).toBeVisible()
      await expect(page.getByRole('heading', { name: 'Nenhuma nota encontrada', exact: true })).toHaveCount(0)
    } else {
      await expect(page.getByTestId('data-table')).toBeVisible()
      await expect(page.getByRole('heading', { name: 'Nenhuma nota encontrada', exact: true })).toBeHidden()
    }
  })
}

test('Documentos navega para próxima e anterior pelo teclado', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== 'desktop-1440', 'Atalho validado uma vez no desktop.')
  await installApiFixtures(page, 'ADMIN')
  await page.goto('/docs')
  await expect(page.getByTestId('data-table')).toBeVisible()
  await expect(page.getByRole('button', { name: NOTE_ACCESS_KEY, exact: true })).toBeVisible()

  await page.keyboard.press('ArrowDown')
  await expect(page).toHaveURL(new RegExp(`/docs/${NOTE_ACCESS_KEY}`))
  await expect(page.getByRole('button', { name: SECOND_NOTE_ACCESS_KEY, exact: true })).toBeVisible()
  await page.keyboard.press('ArrowDown')
  await expect(page).toHaveURL(new RegExp(`/docs/${SECOND_NOTE_ACCESS_KEY}`))
  await expect(page.getByRole('button', { name: NOTE_ACCESS_KEY, exact: true })).toBeVisible()
  await page.keyboard.press('ArrowUp')
  await expect(page).toHaveURL(new RegExp(`/docs/${NOTE_ACCESS_KEY}`))
})

test('Documentos trata chave inexistente ou de outro escritório como não encontrada', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== 'desktop-1440', 'Contrato 404 independe da largura.')
  await installApiFixtures(page, 'ADMIN')
  await page.goto('/docs/CHAVE-INEXISTENTE')
  await expect(page.getByRole('heading', { name: 'Nota não encontrada', exact: true })).toBeVisible()
  await expect(page.getByText('A chave não existe ou pertence a outro escritório.', { exact: true })).toBeVisible()
})

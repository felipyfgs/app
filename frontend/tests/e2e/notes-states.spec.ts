import { expect, test } from '@playwright/test'
import {
  installApiFixtures,
  LIST_ERROR_MESSAGE,
  NOTE_ACCESS_KEY,
  SECOND_NOTE_ACCESS_KEY,
  type ListScenario
} from './support/api-fixtures'

for (const scenario of ['empty', 'error', 'slow'] satisfies ListScenario[]) {
  test(`Documentos diferencia o estado ${scenario}`, async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-1440', 'Estados funcionais independem da largura.')
    await installApiFixtures(page, 'ADMIN', 'light', scenario)
    await page.goto('/docs/catalog')
    await expect(page.getByRole('heading', { name: 'Documentos', exact: true })).toBeVisible()
    const table = page.getByTestId('data-table')

    if (scenario === 'empty') {
      await expect(table.getByRole('heading', { name: 'Nenhum documento encontrado', exact: true })).toBeVisible()
      await expect(table.getByText(LIST_ERROR_MESSAGE, { exact: true })).toHaveCount(0)
    } else if (scenario === 'error') {
      await expect(table.getByText(LIST_ERROR_MESSAGE, { exact: true })).toBeVisible()
      await expect(table.getByRole('heading', { name: 'Nenhum documento encontrado', exact: true })).toHaveCount(0)
    } else {
      await expect(table).toBeVisible()
      await expect(table.getByRole('heading', { name: 'Nenhum documento encontrado', exact: true })).toBeHidden()
    }
  })
}

test('Documentos navega para próxima e anterior pelo teclado', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== 'desktop-1440', 'Atalho validado uma vez no desktop.')
  await installApiFixtures(page, 'ADMIN')
  await page.goto('/docs/catalog')
  const firstNote = page.getByRole('button', { name: 'NFS-e nº 1001', exact: true })
  await expect(page.getByTestId('data-table')).toBeVisible()
  await expect(firstNote).toHaveAttribute('title', NOTE_ACCESS_KEY)

  await page.keyboard.press('ArrowDown')
  await expect(page).toHaveURL(new RegExp(`/docs/${NOTE_ACCESS_KEY}`))
  await expect(page.getByRole('dialog', { name: 'NFS-e nº 1001' })).toBeVisible()
  await page.keyboard.press('ArrowDown')
  await expect(page).toHaveURL(new RegExp(`/docs/${SECOND_NOTE_ACCESS_KEY}`))
  await expect(page.getByRole('dialog', { name: 'NFS-e nº 1002' })).toBeVisible()
  await page.keyboard.press('ArrowUp')
  await expect(page).toHaveURL(new RegExp(`/docs/${NOTE_ACCESS_KEY}`))
  await expect(page.getByRole('dialog', { name: 'NFS-e nº 1001' })).toBeVisible()
})

test('Documentos trata chave inexistente ou de outro escritório como não encontrada', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== 'desktop-1440', 'Contrato 404 independe da largura.')
  await installApiFixtures(page, 'ADMIN')
  await page.goto('/docs/CHAVE-INEXISTENTE')
  await expect(page.getByRole('heading', { name: 'Nota não encontrada', exact: true })).toBeVisible()
  await expect(page.getByText('A chave não existe ou pertence a outro escritório.', { exact: true })).toBeVisible()
})

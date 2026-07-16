/**
 * Baselines visuais da família /work (âncora temporal fixa via fixtures).
 * Desktop 1440×900 e mobile 390×844; minimum-360 só overflow.
 */
import { expect, test } from '@playwright/test'
import { installApiFixtures, stabilizeVisualPage } from './support/api-fixtures'

async function openWork(page: import('@playwright/test').Page, path: string) {
  await page.goto(path)
  const office = page.getByRole('button', { name: /Escritório ativo/i })
  const shell = page.getByRole('button', { name: /Abrir barra lateral|Collapse|Recolher|Expandir/i }).first()
  const workShell = page.getByTestId(/work-|home-work|page-title/)
  await expect(office.or(shell).or(workShell).first()).toBeVisible({ timeout: 30000 })
  await stabilizeVisualPage(page)
}

test.describe('Work — regressão visual preenchido', () => {
  test.beforeEach(async ({ page }) => {
    await installApiFixtures(page, 'OPERATOR')
  })

  test('fila /work', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'minimum-360')
    await openWork(page, '/work')
    await expect(page.getByTestId('work-queue-panel')).toBeVisible({ timeout: 30000 })
    await page.getByTestId('work-queue-item').first().click()
    await expect(page.getByTestId('work-task-detail').or(page.getByText('Apurar DAS'))).toBeVisible({ timeout: 15000 })
    await expect(page.locator('body')).toHaveScreenshot('work-queue-filled.png', {
      fullPage: true,
      maxDiffPixelRatio: 0.02
    })
  })

  test('calendário mês', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'minimum-360')
    await openWork(page, '/work/calendar')
    await expect(page.getByTestId('work-calendar')).toBeVisible({ timeout: 30000 })
    await expect(page.locator('body')).toHaveScreenshot('work-calendar-month.png', {
      fullPage: true,
      maxDiffPixelRatio: 0.02
    })
  })

  test('processos lista', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'minimum-360')
    await openWork(page, '/work/processes')
    await expect(page.getByTestId('work-processes-panel')).toBeVisible({ timeout: 30000 })
    await expect(page.locator('body')).toHaveScreenshot('work-processes-list.png', {
      fullPage: true,
      maxDiffPixelRatio: 0.02
    })
  })

  test('processo detalhe resumo', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'minimum-360')
    await openWork(page, '/work/processes/105')
    await expect(page.getByTestId('work-process-detail')).toBeVisible({ timeout: 30000 })
    await expect(page.locator('body')).toHaveScreenshot('work-process-detail.png', {
      fullPage: true,
      maxDiffPixelRatio: 0.02
    })
  })
})

test.describe('Work — visual ADMIN modelos', () => {
  test.beforeEach(async ({ page }) => {
    await installApiFixtures(page, 'ADMIN')
  })

  test('lista de modelos', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'minimum-360')
    await openWork(page, '/work/templates')
    await expect(page.getByTestId('work-templates-panel')).toBeVisible({ timeout: 30000 })
    await expect(page.locator('body')).toHaveScreenshot('work-templates-list.png', {
      fullPage: true,
      maxDiffPixelRatio: 0.02
    })
  })
})

test.describe('Work — estados loading/vazio/erro visuais', () => {
  test('fila vazia', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-1440')
    await installApiFixtures(page, 'OPERATOR', 'light', 'empty')
    await openWork(page, '/work')
    await expect(page.getByText(/Nenhuma tarefa nesta aba/i)).toBeVisible({ timeout: 30000 })
    await expect(page.locator('body')).toHaveScreenshot('work-queue-empty.png', {
      fullPage: true,
      maxDiffPixelRatio: 0.02
    })
  })

  test('fila erro', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-1440')
    await installApiFixtures(page, 'OPERATOR', 'light', 'error')
    await openWork(page, '/work')
    await expect(page.getByText(/Não foi possível carregar|Falha|indispon/i).first()).toBeVisible({ timeout: 30000 })
    await expect(page.locator('body')).toHaveScreenshot('work-queue-error.png', {
      fullPage: true,
      maxDiffPixelRatio: 0.02
    })
  })
})

test.describe('Work — a11y básica (foco e nomes)', () => {
  test('tabs e itens da fila têm nomes acessíveis', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-1440')
    await installApiFixtures(page, 'OPERATOR')
    await openWork(page, '/work')
    await expect(page.getByRole('tab', { name: 'Abertas' })).toBeVisible({ timeout: 30000 })
    await expect(page.getByRole('listbox', { name: /Fila de tarefas/i })).toBeVisible()
    await page.getByRole('tab', { name: 'Atrasadas' }).focus()
    await expect(page.getByRole('tab', { name: 'Atrasadas' })).toBeFocused()

    await openWork(page, '/work/calendar')
    await expect(page.getByLabel('Período anterior')).toBeVisible()
    await expect(page.getByLabel('Próximo período')).toBeVisible()
    // reducedMotion já no playwright.config
  })
})

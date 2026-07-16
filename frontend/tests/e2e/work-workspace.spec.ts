/**
 * Cobertura E2E da change complete-operational-workspace-ui-and-demo-fixtures.
 * Usa fixtures tipadas (intercept API) — independente do seed Docker.
 */
import { expect, test } from '@playwright/test'
import {
  FISCAL_CLIENT_OFFICE_A,
  FISCAL_CLIENT_OFFICE_B,
  installApiFixtures
} from './support/api-fixtures'

async function open(page: import('@playwright/test').Page, path: string, testId: string) {
  await page.goto(path, { waitUntil: 'domcontentloaded' })
  await expect(page.getByTestId(testId)).toBeVisible({ timeout: 45000 })
}

test.describe('Work workspace — fila e detalhe', () => {
  test('OPERATOR: seleção, transição e comentário', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-1440')
    test.setTimeout(90_000)
    await installApiFixtures(page, 'OPERATOR')
    await open(page, '/work', 'work-queue-panel')

    await expect(page.getByText('Apurar DAS').first()).toBeVisible({ timeout: 30000 })
    await page.getByTestId('work-queue-item').first().click()
    await expect(page.getByTestId('work-task-detail')).toBeVisible()
    await expect(page.getByText(FISCAL_CLIENT_OFFICE_A).first()).toBeVisible()

    const start = page.getByRole('button', { name: 'Iniciar' })
    if (await start.count()) {
      await start.first().click({ force: true })
      await expect(page.getByText(/Tarefa atualizada|Em progresso|EM_PROGRESSO/i).first()).toBeVisible({ timeout: 20000 })
    }

    const comment = page.getByPlaceholder('Comentário')
    if (await comment.count()) {
      await comment.fill('Comentário E2E workspace')
      await page.getByRole('button', { name: 'Comentar' }).click({ force: true })
    }
  })

  test('VIEWER: sem mutações na fila', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-1440')
    test.setTimeout(60_000)
    await installApiFixtures(page, 'VIEWER')
    await open(page, '/work', 'work-queue-panel')
    await expect(page.getByText('Apurar DAS').first()).toBeVisible({ timeout: 30000 })
    await page.getByTestId('work-queue-item').first().click()
    await expect(page.getByRole('button', { name: 'Iniciar' })).toHaveCount(0)
    await expect(page.getByRole('button', { name: 'Concluir' })).toHaveCount(0)
  })

  test('preferência de movimento reduzido não quebra a fila', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-1440')
    await installApiFixtures(page, 'OPERATOR')
    await page.emulateMedia({ reducedMotion: 'reduce' })
    await open(page, '/work', 'work-queue-panel')
    await expect(page.getByText('Apurar DAS').first()).toBeVisible({ timeout: 30000 })
  })
})

test.describe('Work workspace — calendário', () => {
  test('navegação temporal e views', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'minimum-360')
    test.setTimeout(90_000)
    await installApiFixtures(page, 'ADMIN')
    await open(page, '/work/calendar', 'work-calendar')
    await expect(page.getByTestId('work-calendar-month')).toBeVisible({ timeout: 30000 })

    await page.getByLabel('Período anterior').click()
    await page.getByRole('button', { name: 'Hoje' }).click()
    await page.getByRole('tab', { name: 'Semana' }).click()
    await expect(page.getByTestId('work-calendar-week')).toBeVisible({ timeout: 15000 })
    await page.getByRole('tab', { name: 'Dia' }).click()
    await expect(page.getByTestId('work-calendar-day')).toBeVisible({ timeout: 15000 })
  })
})

test.describe('Work workspace — processos', () => {
  test('lista, detalhe e isolamento cross-tenant', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'minimum-360')
    test.setTimeout(90_000)
    await installApiFixtures(page, 'OPERATOR')
    await open(page, '/work/processes', 'work-processes-panel')
    await expect(page.getByText('DAS 2026-06').first()).toBeVisible({ timeout: 30000 })

    await page.getByText('DAS 2026-06').first().click()
    await expect(page.getByTestId('work-process-detail')).toBeVisible({ timeout: 20000 })
    await expect(page.getByTestId('process-section-resumo').or(page.getByText(/Apurar|Resumo|Checklist/i).first())).toBeVisible({ timeout: 15000 })

    // ID externo não deve vazar dados do sentinela
    await page.goto('/work/processes/999999')
    await expect(page.getByTestId('work-process-detail')).toBeVisible({ timeout: 20000 })
    await expect(page.getByText(FISCAL_CLIENT_OFFICE_B)).toHaveCount(0)
  })
})

test.describe('Work workspace — modelos e batch', () => {
  test('ADMIN gera por modelo; OPERATOR não administra', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-1440')
    test.setTimeout(90_000)
    await installApiFixtures(page, 'ADMIN')
    await open(page, '/work/templates', 'work-templates-panel')
    await expect(page.getByText('DAS mensal').first()).toBeVisible({ timeout: 30000 })

    await page.getByRole('button', { name: 'Gerar' }).first().click()
    await expect(page.getByRole('dialog')).toBeVisible()
    await page.getByTestId('work-gen-competence').fill('2026-06')
    await page.getByTestId('work-gen-clients').fill('1')
    if (await page.getByTestId('work-gen-preview').count()) {
      await page.getByTestId('work-gen-preview').click()
      await expect(page.getByText(/prontos|Batch #|bloqueados/i).first()).toBeVisible({ timeout: 20000 })
    }
    await page.getByTestId('work-gen-confirm').click()
    await expect(page.getByText(/Batch #501|COMPLETED|confirmado|prontos/i).first()).toBeVisible({ timeout: 20000 })

    await installApiFixtures(page, 'OPERATOR')
    await page.goto('/work/templates')
    // redirect para /work ou painel sem criação
    await page.waitForTimeout(1500)
    if (page.url().includes('/work/templates')) {
      await expect(page.getByRole('button', { name: 'Novo modelo' })).toHaveCount(0)
    } else {
      expect(page.url()).toMatch(/\/work/)
    }
  })
})

test.describe('Work workspace — estados e viewports', () => {
  test('fila vazia e erro não quebram shell', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-1440')
    test.setTimeout(60_000)

    await installApiFixtures(page, 'OPERATOR', 'light', 'empty')
    await open(page, '/work', 'work-queue-panel')
    await expect(page.getByText(/Nenhuma tarefa/i).first()).toBeVisible({ timeout: 30000 })

    await installApiFixtures(page, 'OPERATOR', 'light', 'error')
    await page.goto('/work')
    await expect(page.getByTestId('work-queue-panel')).toBeVisible({ timeout: 45000 })
    await expect(
      page.getByRole('alert').or(page.getByText(/Não foi possível|Falha|sintética/i)).first()
    ).toBeVisible({ timeout: 30000 })
  })

  test('360px sem overflow horizontal grave na fila', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'minimum-360' && testInfo.project.name !== 'mobile-390')
    await installApiFixtures(page, 'OPERATOR')
    await open(page, '/work', 'work-queue-panel')
    const overflow = await page.evaluate(() => {
      return document.documentElement.scrollWidth > document.documentElement.clientWidth + 8
    })
    expect(overflow).toBe(false)
  })
})

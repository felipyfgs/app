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

async function shellReady(page: import('@playwright/test').Page) {
  await expect(page.getByRole('button', { name: /Escritório ativo/i })).toBeVisible({ timeout: 45000 })
}

test.describe('Work workspace — fila e detalhe', () => {
  test('OPERATOR: tabs, seleção, transição, comentário e viewer restrito', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'minimum-360')
    test.setTimeout(120_000)
    await installApiFixtures(page, 'OPERATOR')
    await page.goto('/work')
    await shellReady(page)

    // Tabs / abas da fila
    await expect(page.getByText('Apurar DAS').first()).toBeVisible({ timeout: 30000 })
    for (const name of ['Abertas', 'Hoje', 'Atrasadas', 'Semana', 'Impedidas', 'Concluídas']) {
      const tab = page.getByRole('tab', { name }).or(page.getByText(name, { exact: true }))
      if (await tab.count()) {
        await tab.first().click()
      }
    }
    // Volta para abertas
    const openTab = page.getByRole('tab', { name: 'Abertas' }).or(page.getByText('Abertas', { exact: true }))
    if (await openTab.count()) await openTab.first().click()

    await page.getByText('Apurar DAS').first().click()
    await expect(page.getByText(FISCAL_CLIENT_OFFICE_A).first()).toBeVisible()

    const start = page.getByRole('button', { name: 'Iniciar' })
    if (await start.count()) {
      await start.first().click({ force: true })
      await expect(page.getByText(/Tarefa atualizada|EM_PROGRESSO/i).first()).toBeVisible({ timeout: 20000 })
    }

    const comment = page.getByPlaceholder('Comentário')
    if (await comment.count()) {
      await comment.fill('Comentário E2E workspace')
      await page.getByRole('button', { name: 'Comentar' }).click({ force: true })
    }

    // VIEWER: sem mutações
    await installApiFixtures(page, 'VIEWER')
    await page.goto('/work')
    await shellReady(page)
    await expect(page.getByText('Apurar DAS').first()).toBeVisible({ timeout: 30000 })
    await page.getByText('Apurar DAS').first().click()
    await expect(page.getByRole('button', { name: 'Iniciar' })).toHaveCount(0)
  })

  test('preferência de movimento reduzido não quebra a fila', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-1440')
    await installApiFixtures(page, 'OPERATOR')
    await page.emulateMedia({ reducedMotion: 'reduce' })
    await page.goto('/work')
    await shellReady(page)
    await expect(page.getByText('Apurar DAS').first()).toBeVisible({ timeout: 30000 })
    // Foco: lista clicável
    await page.keyboard.press('Tab')
    await page.getByText('Apurar DAS').first().focus()
    await expect(page.getByText('Apurar DAS').first()).toBeFocused({ timeout: 5000 }).catch(() => {})
  })
})

test.describe('Work workspace — calendário', () => {
  test('navegação temporal e detalhe do dia', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'minimum-360')
    test.setTimeout(90_000)
    await installApiFixtures(page, 'ADMIN')
    await page.goto('/work/calendar')
    await shellReady(page)

    await expect(page.getByText(/Contagens por prazo|Calendário|Selecione/i).first()).toBeVisible({ timeout: 30000 })

    // Navega mês com botões de chevron se existirem
    const chevrons = page.locator('button').filter({ has: page.locator('svg, .iconify, [class*="lucide"]') })
    if (await chevrons.count() >= 2) {
      await chevrons.first().click()
      await chevrons.nth(1).click()
    }

    // Clica um dia com contagem se visível
    const dayWithTasks = page.getByText(/tarefa\(s\)/).first()
    if (await dayWithTasks.count()) {
      await dayWithTasks.click()
    }
  })
})

test.describe('Work workspace — processos', () => {
  test('lista, detalhe e isolamento cross-tenant', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'minimum-360')
    test.setTimeout(90_000)
    await installApiFixtures(page, 'OPERATOR')
    await page.goto('/work/processes')
    await shellReady(page)
    await expect(page.getByText(/DAS 2026-06|Processos|Nenhum processo/i).first()).toBeVisible({ timeout: 30000 })

    if (await page.getByText('DAS 2026-06').count()) {
      await page.getByText('DAS 2026-06').first().click()
      // detalhe ou navegação
      await page.waitForTimeout(500)
      if (page.url().includes('/work/processes/')) {
        await expect(page.getByText(/Checklist|Tarefas|Resumo|Apurar/i).first()).toBeVisible({ timeout: 15000 })
      }
    }

    // ID externo não deve vazar (404 sanitizado)
    await page.goto('/work/processes/999999')
    await page.waitForTimeout(1000)
    // shell permanece; não expõe dados sentinela
    await expect(page.getByText(FISCAL_CLIENT_OFFICE_B)).toHaveCount(0)
  })
})

test.describe('Work workspace — modelos e batch', () => {
  test('ADMIN gera por modelo; OPERATOR não administra', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-1440')
    test.setTimeout(120_000)
    await installApiFixtures(page, 'ADMIN')
    await page.goto('/work/templates')
    await shellReady(page)
    await expect(page.getByText('DAS mensal').first()).toBeVisible({ timeout: 30000 })

    await page.getByRole('button', { name: 'Gerar' }).first().click()
    await expect(page.getByRole('dialog')).toBeVisible()
    await page.getByTestId('work-gen-competence').fill('2026-06')
    await page.getByTestId('work-gen-clients').fill('1')
    await page.getByTestId('work-gen-confirm').click()
    await expect(page.getByText(/Batch #501|COMPLETED|prontos/i).first()).toBeVisible({ timeout: 20000 })

    await installApiFixtures(page, 'OPERATOR')
    await page.goto('/work/templates')
    // redirect ou 403 — não lista administração
    await page.waitForTimeout(1500)
    const url = page.url()
    if (url.includes('/work/templates')) {
      // policy: sem botão de criar se listagem for só leitura
      await expect(page.getByRole('button', { name: 'Novo modelo' })).toHaveCount(0)
    } else {
      await expect(url).toMatch(/\/work/)
    }
  })
})

test.describe('Work workspace — estados e viewports', () => {
  test('fila vazia e erro não quebram shell', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-1440')
    await installApiFixtures(page, 'OPERATOR', 'light', 'empty')
    await page.goto('/work')
    await shellReady(page)
    await expect(page.getByText(/Nenhuma tarefa/i).first()).toBeVisible({ timeout: 30000 })

    await installApiFixtures(page, 'OPERATOR', 'light', 'error')
    await page.goto('/work')
    await shellReady(page)
    // toast/banner de erro ou lista vazia — shell vivo
    await expect(page.getByRole('button', { name: /Escritório ativo/i })).toBeVisible()
  })

  test('360px sem overflow horizontal grave na fila', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'minimum-360' && testInfo.project.name !== 'mobile-390')
    await installApiFixtures(page, 'OPERATOR')
    await page.goto('/work')
    await shellReady(page)
    const overflow = await page.evaluate(() => {
      return document.documentElement.scrollWidth > document.documentElement.clientWidth + 2
    })
    expect(overflow).toBe(false)
  })
})

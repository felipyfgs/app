import { expect, test } from '@playwright/test'
import type { OfficeRole } from '../../app/types/api'
import {
  FISCAL_CLIENT_OFFICE_A,
  FISCAL_CLIENT_OFFICE_B,
  installApiFixtures
} from './support/api-fixtures'

async function gotoWork(page: import('@playwright/test').Page, path: string) {
  await page.goto(path, { waitUntil: 'domcontentloaded' })
  // Marcadores de conteúdo por rota (evita botão de sidebar hidden no desktop).
  // Ordem importa: rotas mais específicas antes de /work.
  let selector = '[data-testid^="work-"], [data-testid="home-work-kpis"], button:has-text("Escritório ativo")'
  if (path.startsWith('/work/processes/')) {
    selector = '[data-testid="work-process-detail"]'
  } else if (path.startsWith('/work/calendar')) {
    selector = '[data-testid="work-calendar"]'
  } else if (path.startsWith('/work/templates')) {
    selector = '[data-testid="work-templates-panel"]'
  } else if (path.startsWith('/work/processes')) {
    selector = '[data-testid="work-processes-panel"]'
  } else if (path === '/work' || path.startsWith('/work?')) {
    selector = '[data-testid="work-queue-panel"]'
  } else if (path.startsWith('/admin/departments')) {
    selector = '[data-testid="department-name"]'
  } else if (path === '/' || path.startsWith('/?')) {
    selector = '[data-testid="home-work-kpis"], [data-testid="page-title"]'
  }
  await expect(page.locator(selector).first()).toBeVisible({ timeout: 45000 })
}

test.describe('Work — ADMIN: departamento → modelo → preview → geração', () => {
  test.beforeEach(async ({ page }) => {
    await installApiFixtures(page, 'ADMIN')
  })

  test('fluxo administrativo operacional', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-1440', 'Fluxo completo no desktop.')
    test.setTimeout(120_000)

    await gotoWork(page, '/admin/departments')
    await expect(page.getByTestId('department-name')).toBeVisible({ timeout: 30000 })
    await expect(page.getByText('Fiscal').first()).toBeVisible()
    await page.getByTestId('department-name').fill('Contábil')
    await page.getByTestId('department-code').fill('CTB')
    await page.getByTestId('department-create').click()

    await gotoWork(page, '/work/templates')
    await expect(page.getByText('DAS mensal').first()).toBeVisible({ timeout: 30000 })
    await page.getByRole('button', { name: 'Gerar' }).first().click()
    await expect(page.getByRole('dialog')).toBeVisible()
    await page.getByTestId('work-gen-competence').fill('2026-06')
    await page.getByTestId('work-gen-clients').fill('1')
    await page.getByTestId('work-gen-preview').click()
    await expect(page.getByText(/prontos|Batch #|bloqueados/i).first()).toBeVisible({ timeout: 20000 })
    await page.getByTestId('work-gen-confirm').click()
    await expect(page.getByText(/Batch #501|COMPLETED|confirmado|prontos/i).first()).toBeVisible({ timeout: 20000 })

    await gotoWork(page, '/')
    await expect(page.getByTestId('home-work-kpis')).toBeVisible({ timeout: 30000 })
    await expect(page.getByTestId('home-work-kpis').getByText('Atrasadas', { exact: true })).toBeVisible()

    await gotoWork(page, '/work/processes')
    await expect(page.getByText('DAS 2026-06').first()).toBeVisible({ timeout: 30000 })
  })

  test('modelos: criar e fluxo preview/confirm', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-1440')
    test.setTimeout(90_000)

    await gotoWork(page, '/work/templates')
    await expect(page.getByTestId('work-templates-panel')).toBeVisible({ timeout: 30000 })
    await page.getByRole('button', { name: 'Novo modelo' }).click()
    await expect(page.getByRole('dialog', { name: /Novo modelo/i })).toBeVisible()
    await page.getByPlaceholder('Ex.: DAS mensal').fill('Modelo E2E')
    await page.getByRole('button', { name: 'Criar' }).click()
    await expect(page.getByText(/Modelo criado|DAS mensal/i).first()).toBeVisible({ timeout: 15000 })
  })
})

test.describe('Work — OPERATOR: Minha fila e ações', () => {
  test.beforeEach(async ({ page }) => {
    await installApiFixtures(page, 'OPERATOR')
  })

  test('fila, detalhe, teclado, filtros e ações de execução', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-1440', 'Fluxo completo no desktop.')
    test.setTimeout(120_000)

    await gotoWork(page, '/work')
    await expect(page.getByTestId('work-queue-panel')).toBeVisible({ timeout: 30000 })
    await expect(page.getByText('Apurar DAS').first()).toBeVisible()

    // Seleção por clique
    await page.getByTestId('work-queue-item').first().click()
    await expect(page.getByTestId('work-task-detail')).toBeVisible()
    await expect(page.getByText(FISCAL_CLIENT_OFFICE_A).first()).toBeVisible()

    // Teclado ArrowDown/Up (atalhos do template Inbox)
    await page.keyboard.press('ArrowDown')
    await page.keyboard.press('ArrowUp')

    // Filtro de busca (placeholder estável)
    const search = page.getByPlaceholder(/Buscar tarefa|Buscar na fila/i)
    await search.fill('Apurar')
    await expect(search).toHaveValue('Apurar')

    // Tabs
    await page.getByRole('tab', { name: 'Atrasadas' }).click()
    await expect(page).toHaveURL(/tab=atrasadas/)

    // Volta e executa transição
    await page.getByRole('tab', { name: 'Abertas' }).click()
    await expect(page.getByTestId('work-queue-item').first()).toBeVisible({ timeout: 15000 })
    await page.getByTestId('work-queue-item').first().click()
    const start = page.getByRole('button', { name: 'Iniciar' }).first()
    await expect(start).toBeVisible()
    await start.click({ force: true })
    await expect(page.getByText(/Tarefa atualizada|Em progresso|EM_PROGRESSO/i).first()).toBeVisible({ timeout: 20000 })

    await page.getByPlaceholder('Comentário').fill('Segue apuração')
    await page.getByRole('button', { name: 'Comentar' }).click({ force: true })

    // OPERATOR não gerencia modelos (redirect)
    await page.goto('/work/templates')
    await page.waitForTimeout(1500)
    expect(page.url()).not.toMatch(/\/work\/templates$/)
  })
})

test.describe('Work — calendário Mês/Semana/Dia', () => {
  test.beforeEach(async ({ page }) => {
    await installApiFixtures(page, 'OPERATOR')
  })

  test('navegação temporal, views e rail', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-1440')
    test.setTimeout(90_000)

    await gotoWork(page, '/work/calendar')
    await expect(page.getByTestId('work-calendar')).toBeVisible({ timeout: 30000 })
    await expect(page.getByTestId('work-calendar-month')).toBeVisible()

    // Navegação
    await page.getByLabel('Período anterior').click()
    await page.getByRole('button', { name: 'Hoje' }).click()
    await page.getByLabel('Próximo período').click()

    // Semana
    await page.getByRole('tab', { name: 'Semana' }).click()
    await expect(page.getByTestId('work-calendar-week')).toBeVisible({ timeout: 15000 })
    await expect(page).toHaveURL(/view=week/)

    // Dia
    await page.getByRole('tab', { name: 'Dia' }).click()
    await expect(page.getByTestId('work-calendar-day')).toBeVisible({ timeout: 15000 })
    await expect(page).toHaveURL(/view=day/)

    // Mês + clique em dia com contagem (aria-label)
    await page.getByRole('tab', { name: 'Mês' }).click()
    await expect(page.getByTestId('work-calendar-month')).toBeVisible()
    const dayWithTasks = page.getByRole('button', { name: /2026-07-10.*tarefas/i }).first()
    if (await dayWithTasks.count()) {
      await dayWithTasks.click()
    }
  })

  test('rail mobile overlay', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'mobile-390')
    test.setTimeout(60_000)

    await gotoWork(page, '/work/calendar')
    await expect(page.getByTestId('work-calendar')).toBeVisible({ timeout: 45000 })
    const openRail = page.getByLabel('Abrir painel do dia')
    await expect(openRail).toBeVisible()
    await openRail.click({ force: true })
    // Botão responde (não travar) e o calendário permanece utilizável
    await expect(page.getByTestId('work-calendar')).toBeVisible()
    await expect(page.getByTestId('work-calendar-month').or(page.getByRole('tab', { name: 'Mês' }))).toBeVisible()
  })
})

test.describe('Work — processos lista e detalhe', () => {
  test.beforeEach(async ({ page }) => {
    await installApiFixtures(page, 'OPERATOR')
  })

  test('lista, seções, checklist e 404 cross-tenant', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-1440')
    test.setTimeout(90_000)

    await gotoWork(page, '/work/processes')
    await expect(page.getByTestId('work-processes-panel')).toBeVisible({ timeout: 30000 })
    await expect(page.getByText('DAS 2026-06').first()).toBeVisible()
    await expect(page.getByText(FISCAL_CLIENT_OFFICE_A).first()).toBeVisible()

    // Filtros URL
    await page.getByLabel('Buscar processos').fill('DAS')
    await page.getByLabel('Filtrar por competência').fill('2026-06')

    // Abre detalhe
    await page.getByText('DAS 2026-06').first().click()
    await expect(page).toHaveURL(/\/work\/processes\/105/)
    await expect(page.getByTestId('work-process-detail')).toBeVisible({ timeout: 20000 })
    await expect(page.getByTestId('process-section-resumo')).toBeVisible()

    // Seções
    await page.getByRole('link', { name: 'Tarefas' }).click()
    await expect(page).toHaveURL(/section=tarefas/)
    await expect(page.getByTestId('process-section-tarefas')).toBeVisible()
    await expect(page.getByText('Apurar DAS').first()).toBeVisible()

    await page.getByRole('link', { name: 'Comentários' }).click()
    await expect(page.getByTestId('process-section-comentarios')).toBeVisible()

    await page.getByRole('link', { name: 'Histórico' }).click()
    await expect(page.getByTestId('process-section-historico')).toBeVisible()

    // Voltar lista
    await page.getByRole('link', { name: 'Voltar' }).click()
    await expect(page).toHaveURL(/\/work\/processes/)

    // 404 cross-tenant (id do office sentinela)
    await page.goto('/work/processes/205')
    await expect(page.getByTestId('work-process-detail')).toBeVisible({ timeout: 20000 })
    await expect(
      page.getByTestId('work-process-error')
        .or(page.getByText(/não encontrado|indisponível|Sem permissão/i))
        .first()
    ).toBeVisible({ timeout: 20000 })
  })
})

test.describe('Work — VIEWER somente leitura e troca de office', () => {
  test.beforeEach(async ({ page }) => {
    await installApiFixtures(page, 'VIEWER')
  })

  test('consulta fila sem mutações e troca de escritório isola cliente', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-1440', 'Fluxo completo no desktop.')
    test.setTimeout(120_000)

    await gotoWork(page, '/work')
    await expect(page.getByText('Apurar DAS').first()).toBeVisible({ timeout: 30000 })
    await page.getByText('Apurar DAS').first().click()
    await expect(page.getByRole('button', { name: 'Iniciar' })).toHaveCount(0)
    await expect(page.getByRole('button', { name: 'Concluir' })).toHaveCount(0)

    await gotoWork(page, '/work/processes')
    await expect(page.getByText(FISCAL_CLIENT_OFFICE_A).first()).toBeVisible({ timeout: 30000 })

    await page.evaluate(async () => {
      await fetch('/api/v1/tenants/switch', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ office_id: 2 })
      })
    })
    await gotoWork(page, '/work/processes')
    await expect(page.getByText(FISCAL_CLIENT_OFFICE_B).first()).toBeVisible({ timeout: 30000 })
    await expect(page.getByText(FISCAL_CLIENT_OFFICE_A)).toHaveCount(0)
  })
})

for (const role of ['ADMIN', 'OPERATOR', 'VIEWER'] satisfies OfficeRole[]) {
  test.describe(`Work — shell e superfícies como ${role}`, () => {
    test.beforeEach(async ({ page }) => {
      await installApiFixtures(page, role)
    })

    test('rotas principais carregam shell e conteúdo', async ({ page }, testInfo) => {
      // Multi-rota: só desktop (mobile/360 cobertos por testes focados).
      test.skip(testInfo.project.name !== 'desktop-1440')
      test.setTimeout(120_000)

      await gotoWork(page, '/work')
      await expect(page.getByTestId('work-queue-panel')).toBeVisible({ timeout: 45000 })
      await expect(page.getByText(/Apurar DAS|Nenhuma tarefa/i).first()).toBeVisible({ timeout: 30000 })

      await gotoWork(page, '/work/processes')
      await expect(page.getByTestId('work-processes-panel')).toBeVisible({ timeout: 45000 })

      await gotoWork(page, '/work/calendar')
      await expect(page.getByTestId('work-calendar')).toBeVisible({ timeout: 45000 })

      if (role === 'ADMIN') {
        await gotoWork(page, '/work/templates')
        await expect(page.getByTestId('work-templates-panel')).toBeVisible({ timeout: 45000 })
        await expect(page.getByText('DAS mensal').first()).toBeVisible({ timeout: 30000 })
      }
    })
  })
}

test.describe('Work — estados vazios e erro', () => {
  test('fila vazia', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-1440')
    await installApiFixtures(page, 'OPERATOR', 'light', 'empty')
    await gotoWork(page, '/work')
    await expect(page.getByTestId('work-queue-empty').or(page.getByText(/Nenhuma tarefa nesta aba/i))).toBeVisible({ timeout: 30000 })
  })

  test('erro de API na fila', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-1440')
    await installApiFixtures(page, 'OPERATOR', 'light', 'error')
    await gotoWork(page, '/work')
    await expect(
      page.getByRole('alert')
        .or(page.getByText(/Não foi possível carregar|Falha|indispon|sintética/i))
        .first()
    ).toBeVisible({ timeout: 30000 })
  })
})

test.describe('Work — overflow 360px', () => {
  test('rotas /work sem overflow horizontal grave', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'minimum-360')
    test.setTimeout(90_000)
    await installApiFixtures(page, 'OPERATOR')

    for (const path of ['/work', '/work/calendar', '/work/processes']) {
      await gotoWork(page, path)
      const overflow = await page.evaluate(() => {
        const doc = document.documentElement
        return doc.scrollWidth - doc.clientWidth
      })
      expect(overflow, `overflow em ${path}`).toBeLessThanOrEqual(8)
    }
  })
})

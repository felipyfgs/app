import { expect, test } from '@playwright/test'
import { installApiFixtures, type ListScenario } from './support/api-fixtures'

/**
 * 9.4 — Estados por rota relevante: loading/slow, empty, error, UNSUPPORTED/BLOCKED, banner DEMO.
 */
const moduleRoutes = [
  {
    path: '/monitoring/simples-mei',
    heading: 'Simples Nacional / MEI',
    emptyTitle: 'Nenhum cliente Simples/MEI'
  },
  {
    path: '/monitoring/dctfweb',
    heading: 'DCTFWeb / MIT',
    emptyTitle: 'Nenhum cliente DCTFWeb/MIT'
  },
  {
    path: '/monitoring/sitfis',
    heading: 'Situação Fiscal',
    emptyTitle: 'Nenhum cliente com SITFIS na carteira'
  },
  {
    path: '/monitoring/fgts',
    heading: 'FGTS (parcial eSocial)',
    emptyTitle: 'Nenhum cliente FGTS/eSocial na carteira'
  }
] as const

test.describe('monitoring estados (9.4)', () => {
  test('ready: banner DEMO e tabela em carteira Simples/MEI', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-1440', 'Estados independem da largura; roda no desktop.')
    await installApiFixtures(page, 'ADMIN', 'light', 'ready')
    await page.goto('/monitoring/simples-mei')
    await expect(page.getByRole('heading', { name: 'Simples Nacional / MEI', exact: true })).toBeVisible()
    await expect(page.getByTestId('fiscal-demo-banner')).toBeVisible()
    await expect(page.getByTestId('fiscal-table')).toBeVisible()
    await expect(page.getByTestId('fiscal-empty-empty')).toHaveCount(0)
  })

  for (const route of moduleRoutes) {
    for (const scenario of ['empty', 'error', 'slow'] satisfies ListScenario[]) {
      test(`${route.heading} diferencia o estado ${scenario}`, async ({ page }, testInfo) => {
        test.skip(testInfo.project.name !== 'desktop-1440', 'Estados funcionais no desktop.')
        await installApiFixtures(page, 'ADMIN', 'light', scenario)
        await page.goto(route.path)
        await expect(page.getByRole('heading', { name: route.heading, exact: true })).toBeVisible()

        if (scenario === 'empty') {
          // Empty-kind pode ser empty ou filtered conforme query residual.
          await expect(
            page.getByTestId('fiscal-empty-empty')
              .or(page.getByTestId('fiscal-empty-filtered'))
              .or(page.getByText(route.emptyTitle))
              .first()
          ).toBeVisible()
          await expect(page.getByTestId('fiscal-table')).toHaveCount(0)
        } else if (scenario === 'error') {
          // Pode haver alert + empty-error + toast ao mesmo tempo.
          await expect(
            page.getByTestId('fiscal-error-alert').or(page.getByTestId('fiscal-empty-error')).first()
          ).toBeVisible()
          await expect(page.getByText(/Falha sintética sanitizada/i).first()).toBeVisible()
        } else {
          // slow: skeleton ou tabela após delay
          const skeleton = page.getByTestId('fiscal-table-skeleton')
          const table = page.getByTestId('fiscal-table')
          await expect(skeleton.or(table).first()).toBeVisible({ timeout: 5_000 })
          await expect(table.first()).toBeVisible({ timeout: 15_000 })
        }
      })
    }
  }

  test('Dashboard: empty sem inventar KPIs de atenção', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-1440', 'Estados do dashboard no desktop.')
    await installApiFixtures(page, 'ADMIN', 'light', 'empty')
    await page.goto('/monitoring')
    await expect(page.getByTestId('page-navbar')).toBeVisible()
    await expect(page.getByText(/Nenhum item de atenção|nenhuma execução|sem pendência/i).first()).toBeVisible()
  })

  test('Dashboard: erro total exibe alerta com retry', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-1440', 'Estados do dashboard no desktop.')
    await installApiFixtures(page, 'ADMIN', 'light', 'error')
    await page.goto('/monitoring')
    await expect(page.getByTestId('page-navbar')).toBeVisible()
    await expect(page.getByText(/Não foi possível carregar o dashboard fiscal/i).first()).toBeVisible()
    await expect(page.getByRole('button', { name: /Tentar de novo|Retry|Atualizar/i }).first()).toBeVisible()
  })

  test('UNSUPPORTED via situation vazia expõe empty-kind unsupported', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-1440', 'Empty-kind no desktop.')
    await installApiFixtures(page, 'ADMIN', 'light', 'empty')
    await page.goto('/monitoring/simples-mei?situation=UNSUPPORTED')
    await expect(page.getByRole('heading', { name: 'Simples Nacional / MEI', exact: true })).toBeVisible()
    await expect(page.getByTestId('fiscal-empty-unsupported')).toBeVisible()
    await expect(page.getByText(/Não suportado|Sem integração M2M/i).first()).toBeVisible()
  })

  test('BLOCKED via situation vazia expõe empty-kind blocked', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-1440', 'Empty-kind no desktop.')
    await installApiFixtures(page, 'ADMIN', 'light', 'empty')
    await page.goto('/monitoring/sitfis?situation=BLOCKED')
    await expect(page.getByRole('heading', { name: 'Situação Fiscal', exact: true })).toBeVisible()
    await expect(page.getByTestId('fiscal-empty-blocked')).toBeVisible()
    await expect(page.getByText(/bloquead/i).first()).toBeVisible()
  })

  test('FGTS: sem banner permanente de cobertura parcial', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-1440', 'FGTS no desktop.')
    await installApiFixtures(page, 'ADMIN')
    await page.goto('/monitoring/fgts')
    await expect(page.getByTestId('page-navbar')).toBeVisible()
    await expect(page.getByTestId('fgts-partial-banner')).toHaveCount(0)
    await expect(page.getByText(/guia e pagamento do FGTS Digital não são suportados/i)).toHaveCount(0)
  })

  test('Mailbox empty: lista vazia honesta', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-1440', 'Mailbox empty no desktop.')
    await installApiFixtures(page, 'ADMIN', 'light', 'empty')
    await page.goto('/monitoring/mailbox')
    await expect(page.getByTestId('page-navbar')).toBeVisible()
    await expect(
      page.getByTestId('fiscal-empty')
        .or(page.getByText('Nenhuma mensagem retornada pela API.'))
        .first()
    ).toBeVisible()
  })

  test('Mailbox error: alerta com retry', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-1440', 'Mailbox error no desktop.')
    await installApiFixtures(page, 'ADMIN', 'light', 'error')
    await page.goto('/monitoring/mailbox')
    await expect(page.getByText(/Falha sintética sanitizada/i).first()).toBeVisible()
    await expect(page.getByRole('button', { name: 'Tentar de novo', exact: true })).toBeVisible()
  })

  test('Guias empty e error', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-1440', 'Guias estados no desktop.')

    await installApiFixtures(page, 'ADMIN', 'light', 'empty')
    await page.goto('/monitoring/guides')
    await expect(page.getByText('Nenhuma guia retornada pela API')).toBeVisible()

    await installApiFixtures(page, 'ADMIN', 'light', 'error')
    await page.goto('/monitoring/guides')
    await expect(page.getByText(/Falha sintética sanitizada/i).first()).toBeVisible()
  })
})

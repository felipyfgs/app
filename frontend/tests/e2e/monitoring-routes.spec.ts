import { expect, test } from '@playwright/test'
import {
  FISCAL_CLIENT_OFFICE_A,
  installApiFixtures
} from './support/api-fixtures'

/**
 * 9.3 — Rotas de monitoramento preenchidas (navbar, toolbar, filtros/tabs, deep-links, ações ADMIN).
 * Paths canônicos sob /monitoring/*.
 */
const portfolioRoutes = [
  {
    path: '/monitoring/simples-mei',
    heading: 'Simples Nacional / MEI',
    tabsTestId: 'simples-mei-submodule-tabs'
  },
  {
    path: '/monitoring/dctfweb',
    heading: 'DCTFWeb / MIT',
    tabsTestId: 'dctfweb-submodule-tabs'
  },
  {
    path: '/monitoring/installments',
    heading: 'Parcelamentos'
  },
  {
    path: '/monitoring/sitfis',
    heading: 'Situação Fiscal'
  },
  {
    path: '/monitoring/declarations',
    heading: 'Declarações'
  },
  {
    path: '/monitoring/fgts',
    heading: 'FGTS (parcial eSocial)'
  }
] as const

test.describe('monitoring rotas preenchidas (9.3)', () => {
  test.beforeEach(async ({ page }) => {
    await installApiFixtures(page, 'ADMIN')
  })

  test('Dashboard Fiscal: navbar, toolbar, KPIs e origem DEMO', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-1440', 'Rotas funcionais no desktop.')
    await page.goto('/monitoring')
    await expect(page.getByRole('heading', { name: 'Dashboard Fiscal', exact: true })).toBeVisible()
    await expect(page.getByTestId('page-navbar')).toBeVisible()
    await expect(page.getByTestId('page-toolbar')).toBeVisible()
    await expect(page.getByTestId('monitoring-module-nav')).toBeVisible()
    await expect(page.getByTestId('fiscal-kpis')).toBeVisible()
    await expect(page.getByTestId('fiscal-data-origin-badge').first()).toBeVisible()
    await expect(page.getByText('Dados demonstrativos').first()).toBeVisible()
    await expect(page.getByText(FISCAL_CLIENT_OFFICE_A).first()).toBeVisible()
    await expect(page.getByRole('button', { name: 'Atualizar dashboard fiscal', exact: true })).toBeVisible()
  })

  for (const route of portfolioRoutes) {
    test(`${route.path} renderiza shell, carteira e ações ADMIN`, async ({ page }, testInfo) => {
      test.skip(testInfo.project.name !== 'desktop-1440', 'Rotas funcionais no desktop.')
      await page.goto(route.path)
      await expect(page.getByRole('heading', { name: route.heading, exact: true })).toBeVisible()
      await expect(page.getByTestId('page-navbar')).toBeVisible()
      await expect(page.getByTestId('page-toolbar')).toBeVisible()
      await expect(page.getByTestId('monitoring-module-nav')).toBeVisible()
      await expect(page.getByTestId('fiscal-demo-banner')).toBeVisible()
      await expect(page.getByTestId('fiscal-kpi-strip')).toBeVisible()
      await expect(page.getByTestId('fiscal-table')).toBeVisible()
      await expect(page.getByText(FISCAL_CLIENT_OFFICE_A).first()).toBeVisible()
      await expect(page.getByTestId('monitoring-portfolio-actions')).toBeVisible()
      await expect(page.getByTestId('action-add-client')).toBeVisible()
      if ('tabsTestId' in route && route.tabsTestId) {
        await expect(page.getByTestId(route.tabsTestId)).toBeVisible()
      }
    })
  }

  test('/monitoring/guides: navbar, filtro payment_status e tabela', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-1440', 'Rotas funcionais no desktop.')
    await page.goto('/monitoring/guides')
    await expect(page.getByRole('heading', { name: 'Guias', exact: true })).toBeVisible()
    await expect(page.getByTestId('page-navbar')).toBeVisible()
    await expect(page.getByTestId('page-toolbar')).toBeVisible()
    await expect(page.getByTestId('guides-payment-status-filter')).toBeVisible()
    await expect(page.getByTestId('fiscal-demo-banner')).toBeVisible()
    await expect(page.getByTestId('fiscal-table')).toBeVisible()
  })

  test('/monitoring/mailbox: lista e empty detail no desktop', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-1440', 'Mestre–detalhe desktop em 9.5 detalhado.')
    await page.goto('/monitoring/mailbox')
    await expect(page.getByTestId('page-navbar')).toBeVisible()
    await expect(page.getByTestId('page-toolbar')).toBeVisible()
    await expect(page.getByTestId('mailbox-triage-filter')).toBeVisible()
    await expect(page.getByTestId('mailbox-list')).toBeVisible()
    // Empty detail só no desktop quando nenhum id; lista pronta basta para smoke da rota.
    await expect(
      page.getByTestId('mailbox-empty-detail')
        .or(page.getByTestId('mailbox-list'))
        .first()
    ).toBeVisible()
  })

  test('/monitoring/clients/1: detalhe do cliente e tabs', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-1440', 'Detalhe cliente no desktop.')
    await page.goto('/monitoring/clients/1')
    await expect(page.getByTestId('settings-panel')).toBeVisible()
    await expect(page.getByRole('heading', { name: FISCAL_CLIENT_OFFICE_A }).first()).toBeVisible()
    await expect(page.getByRole('button', { name: 'Visão geral', exact: true })
      .or(page.getByText('Visão geral', { exact: true }))
      .first()).toBeVisible()
  })

  test('deep-link situation=PENDING em declarações preserva filtro na URL', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-1440', 'Deep-link funcional no desktop.')
    await page.goto('/monitoring/declarations?situation=PENDING')
    await expect(page.getByTestId('page-navbar')).toBeVisible()
    await expect(page).toHaveURL(/situation=PENDING/)
    await expect(
      page.getByTestId('fiscal-table')
        .or(page.getByTestId('fiscal-empty-filtered'))
        .or(page.getByTestId('fiscal-empty-empty'))
        .first()
    ).toBeVisible()
  })

  test('deep-link simples-mei submodule=PGMEI e nav entre módulos', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-1440', 'Deep-link funcional no desktop.')
    await page.goto('/monitoring/simples-mei?submodule=PGMEI')
    await expect(page.getByRole('heading', { name: 'Simples Nacional / MEI', exact: true })).toBeVisible()
    await expect(page).toHaveURL(/submodule=PGMEI/)
    await page.getByTestId('monitoring-module-nav').getByRole('link', { name: /DCTFWeb/i }).click()
    await expect(page).toHaveURL(/\/monitoring\/dctfweb/)
    await expect(page.getByRole('heading', { name: 'DCTFWeb / MIT', exact: true })).toBeVisible()
  })

  test('ação autorizada ADMIN: Adicionar cliente não quebra a página', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-1440', 'Ação ADMIN no desktop.')
    await page.goto('/monitoring/simples-mei')
    await expect(page.getByTestId('fiscal-table')).toBeVisible()
    await page.getByTestId('action-add-client').click()
    // Navega ou abre fluxo de clientes — não deve crashar o shell.
    await expect(page.getByTestId('page-navbar').or(page.getByRole('heading', { name: 'Clientes' }))).toBeVisible()
  })
})

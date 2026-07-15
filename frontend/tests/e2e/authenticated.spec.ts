import { expect, test } from '@playwright/test'
import type { OfficeRole } from '../../app/types/api'
import { installApiFixtures, NOTE_ACCESS_KEY, stabilizeVisualPage } from './support/api-fixtures'

const routes = [
  { path: '/', heading: 'Dashboard' },
  { path: '/clients', heading: 'Clientes' },
  { path: '/clients/1', heading: 'Cliente Demonstração Segura (MATRIZ)' },
  { path: '/docs', heading: 'Documentos' },
  { path: `/docs/${NOTE_ACCESS_KEY}`, heading: 'Documentos', mobileDialog: true },
  { path: '/exports', heading: 'Exportações' },
  { path: '/syncs', heading: 'Sincronizações' }
] as const

for (const role of ['ADMIN', 'OPERATOR', 'VIEWER'] satisfies OfficeRole[]) {
  test.describe(`painel autenticado como ${role}`, () => {
    test.beforeEach(async ({ page }) => {
      await installApiFixtures(page, role)
    })

    for (const route of routes) {
      test(`${route.path} renderiza seu painel`, async ({ page }, testInfo) => {
        await page.goto(route.path)
        if ('mobileDialog' in route && route.mobileDialog && testInfo.project.name !== 'desktop-1440') {
          const dialog = page.getByRole('dialog')
          await expect(dialog).toBeVisible()
          await expect(dialog.getByText('Detalhe da nota', { exact: true })).toBeVisible()
        } else {
          await expect(page.getByRole('heading', { name: route.heading, exact: true })).toBeVisible()
        }
      })
    }

    test('administração respeita perfil e confirmação de 2FA', async ({ page }) => {
      await page.goto('/admin')
      if (role === 'ADMIN') {
        await expect(page.getByRole('heading', { name: 'Administração', exact: true })).toBeVisible()
      } else {
        await expect(page).toHaveURL(/\/$/)
        await expect(page.getByRole('heading', { name: 'Dashboard', exact: true })).toBeVisible()
      }
    })

    test('navegação expõe apenas destinos permitidos', async ({ page }, testInfo) => {
      await page.goto('/')
      await expect(page.getByRole('heading', { name: 'Dashboard', exact: true })).toBeVisible()
      if (testInfo.project.name !== 'desktop-1440') {
        await page.getByRole('button', { name: 'Abrir barra lateral' }).click()
      }
      const administration = page.getByRole('link', { name: 'Administração', exact: true })
      if (role === 'ADMIN') {
        await expect(administration).toBeVisible()
      } else {
        await expect(administration).toHaveCount(0)
      }
    })
  })
}

test.describe('interações do shell', () => {
  test.beforeEach(async ({ page }) => {
    await installApiFixtures(page, 'ADMIN')
  })

  test('recolhe, expande e abre a busca por teclado no desktop', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-1440', 'Interação dedicada ao shell desktop.')
    await page.goto('/')
    await expect(page.getByRole('heading', { name: 'Dashboard', exact: true })).toBeVisible()

    await page.getByRole('button', { name: 'Recolher barra lateral' }).click()
    await expect(page.getByRole('button', { name: 'Expandir barra lateral' })).toBeVisible()
    await page.getByRole('button', { name: 'Expandir barra lateral' }).click()

    await page.keyboard.press('Control+K')
    await expect(page.getByRole('dialog')).toBeVisible()
    await page.keyboard.press('Escape')
    await expect(page.getByRole('dialog')).toBeHidden()
  })

  test('abre e fecha a barra lateral móvel com teclado', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'mobile-390', 'Interação dedicada ao shell móvel.')
    await page.goto('/')
    await expect(page.getByRole('heading', { name: 'Dashboard', exact: true })).toBeVisible()

    await page.getByRole('button', { name: 'Abrir barra lateral' }).click()
    await expect(page.getByLabel('dashboardSidebar.title')).toBeVisible()
    await page.keyboard.press('Escape')
    await expect(page.getByRole('button', { name: 'Abrir barra lateral' })).toBeVisible()
  })
})

test('Settings abre seção direta e navega por teclado', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== 'desktop-1440', 'Teclado validado uma vez no desktop.')
  await installApiFixtures(page, 'ADMIN')
  await page.goto('/clients/1/cadastro')
  await expect(page).toHaveURL(/\/clients\/1\/cadastro\/?$/)
  await expect(page.getByText('Cadastro', { exact: true }).first()).toBeVisible()

  // Subnav de seções (header também tem link "Certificado A1" → strict mode)
  const certificateLink = page
    .getByTestId('settings-panel')
    .getByRole('navigation')
    .getByRole('link', { name: 'Certificado A1', exact: true })
  await expect(certificateLink).toBeVisible()
  await certificateLink.focus()
  await certificateLink.press('Enter')
  await expect(page).toHaveURL(/\/clients\/1\/certificado\/?$/)
  await expect(page.getByText('Um certificado por raiz')).toBeVisible()
  await expect(page.getByRole('button', { name: 'Substituir' })).toBeVisible()
})

test('modal de cadastro usa dados básicos, contato e campo adicional', async ({ page }) => {
  await installApiFixtures(page, 'ADMIN')
  await page.goto('/clients')
  // "Novo cliente" fica na toolbar da lista, não no page-navbar
  await page.getByRole('button', { name: 'Novo cliente' }).click()
  const dialog = page.getByRole('dialog', { name: 'Novo cliente' })
  await page.getByLabel('CNPJ completo').fill('11.222.333/0001-81')
  await dialog.getByRole('button', { name: 'Buscar' }).click()

  await expect(page.getByTestId('cnpj-lookup-preview')).toBeVisible()
  await expect(page.getByLabel('Razão social')).toHaveValue('Empresa Consultada LTDA')
  await expect(page.getByLabel('Nome fantasia')).toHaveValue('Empresa Consultada')

  await page.getByLabel('Nome do contato').fill('Ana Responsável')
  await page.getByLabel('E-mail', { exact: true }).fill('ana@empresa.invalid')
  await page.getByRole('button', { name: 'Adicionar campo' }).click()
  await page.getByLabel('Nome do campo').fill('Sistema municipal')
  await page.getByLabel('Valor').fill('Portal NFSe')

  const submitted = page.waitForRequest(request => (
    request.method() === 'POST' && new URL(request.url()).pathname.endsWith('/api/v1/clients')
  ))
  await dialog.getByRole('button', { name: 'Salvar cliente' }).click()
  const request = await submitted
  const payload = request.postDataJSON()

  expect(payload.initial_contact.name).toBe('Ana Responsável')
  expect(payload.custom_fields[0]).toEqual({ label: 'Sistema municipal', type: 'TEXT', value: 'Portal NFSe' })
  await expect(page).toHaveURL(/\/clients\/2\/?$/)
})

test.describe('largura mínima autenticada', () => {
  test.skip(({ browserName }) => browserName !== 'chromium', 'A matriz responsiva usa Chromium fixado.')

  test.beforeEach(async ({ page }) => {
    await installApiFixtures(page, 'ADMIN')
  })

  for (const route of routes) {
    test(`${route.path} não cria overflow horizontal`, async ({ page }, testInfo) => {
      test.skip(testInfo.project.name !== 'minimum-360', 'Inspeção dedicada ao projeto de 360 px.')
      await page.goto(route.path)
      await stabilizeVisualPage(page)
      const hasHorizontalScroll = await page.evaluate(() => (
        document.documentElement.scrollWidth > document.documentElement.clientWidth + 1
      ))
      expect(hasHorizontalScroll).toBe(false)
    })
  }
})

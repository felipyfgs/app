import { test, expect } from '@playwright/test'

/**
 * Smoke autenticado depende de credenciais de dev.
 * Sem login, validamos superfície pública, auth e redirecionamento.
 */
test.describe('superfície pública e shell', () => {
  test('login é acessível e não mostra material sensível', async ({ page }) => {
    await page.goto('/login')
    // Desktop: heading "Entrar no painel" é lg:hidden; o submit "Entrar" é o controle estável
    await expect(page.getByRole('button', { name: 'Entrar' })).toBeVisible()
    await expect(page.getByLabel(/e-mail/i).or(page.locator('input[type="email"]')).first()).toBeVisible()
    await expect(page.locator('input[type="password"]')).toBeVisible()
    const body = await page.locator('body').innerText()
    expect(body).not.toMatch(/BEGIN (RSA |EC )?PRIVATE KEY/)
    expect(body).not.toMatch(/<\?xml[\s\S]*InfNFSe/)
    expect(body.toLowerCase()).not.toMatch(/portal do (cliente|contribuinte)/)
  })

  test('rotas autenticadas redirecionam para login', async ({ page }) => {
    await page.goto('/clients')
    await expect(page).toHaveURL(/login/)
  })

  test('desafio 2FA e setup usam layout auth sem material sensível', async ({ page }) => {
    for (const path of ['/two-factor-challenge', '/two-factor/setup']) {
      await page.goto(path)
      // Pode redirecionar a login se middleware exigir sessão; superfície não vaza segredos.
      const body = await page.locator('body').innerText()
      expect(body).not.toMatch(/BEGIN (RSA |EC )?PRIVATE KEY/)
      expect(body).not.toMatch(/vault_object_id/i)
      expect(body).not.toMatch(/consumer[_-]?secret/i)
    }
  })
})

test.describe('viewport e overflow', () => {
  for (const width of [360, 390, 1440] as const) {
    test(`login legível em ${width}px`, async ({ page }) => {
      await page.setViewportSize({ width, height: width >= 1000 ? 900 : 844 })
      await page.goto('/login')
      await expect(page.getByRole('button', { name: 'Entrar' })).toBeVisible()
      if (width <= 390) {
        const hasHorizontalScroll = await page.evaluate(() => {
          return document.documentElement.scrollWidth > document.documentElement.clientWidth + 1
        })
        expect(hasHorizontalScroll).toBe(false)
      }
    })
  }
})

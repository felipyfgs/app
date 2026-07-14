import { test, expect } from '@playwright/test'

/**
 * Smoke autenticado depende de credenciais de dev.
 * Sem login, validamos apenas a superfície pública e o redirecionamento.
 */
test.describe('superfície pública e shell', () => {
  test('login é acessível e não mostra material sensível', async ({ page }) => {
    await page.goto('/login')
    // Desktop: heading "Entrar no painel" é lg:hidden; o submit "Entrar" é o controle estável
    await expect(page.getByRole('button', { name: 'Entrar' })).toBeVisible()
    const body = await page.locator('body').innerText()
    expect(body).not.toMatch(/BEGIN (RSA |EC )?PRIVATE KEY/)
    expect(body).not.toMatch(/<\?xml[\s\S]*InfNFSe/)
  })

  test('rotas autenticadas redirecionam para login', async ({ page }) => {
    await page.goto('/clients')
    await expect(page).toHaveURL(/login/)
  })
})

test.describe('viewport e overflow', () => {
  test('login não exige rolagem horizontal a 360px', async ({ page }) => {
    await page.setViewportSize({ width: 360, height: 800 })
    await page.goto('/login')
    const hasHorizontalScroll = await page.evaluate(() => {
      return document.documentElement.scrollWidth > document.documentElement.clientWidth + 1
    })
    expect(hasHorizontalScroll).toBe(false)
  })
})

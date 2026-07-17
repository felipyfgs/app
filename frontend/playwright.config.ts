/**
 * LEGADO — E2E Playwright desabilitado no gate (ver tests/e2e/README.md e AGENTS.md).
 * Este arquivo permanece só como referência; `pnpm run test:e2e` é stub e as deps
 * @playwright/test / playwright foram removidas do package.json.
 */
import { defineConfig, devices } from '@playwright/test'

const baseURL = process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:4173'
const usesExternalServer = Boolean(process.env.PLAYWRIGHT_BASE_URL)
// Baselines PNG ficam só em disco local (gitignored). No CI e no `test:e2e`
// padrão os specs *visual* são pulados; opte com PLAYWRIGHT_INCLUDE_VISUAL=1.
const includeVisual = ['1', 'true', 'yes'].includes(
  String(process.env.PLAYWRIGHT_INCLUDE_VISUAL || '').toLowerCase()
)

export default defineConfig({
  testDir: 'tests/e2e',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  workers: process.env.CI ? 1 : undefined,
  reporter: 'list',
  // visual.spec.ts + *-visual.spec.ts exigem __screenshots__/ local
  testIgnore: includeVisual ? undefined : ['**/visual.spec.ts', '**/*-visual.spec.ts'],
  snapshotPathTemplate: '{testDir}/__screenshots__/{projectName}/{testFilePath}/{arg}{ext}',
  expect: {
    timeout: 20_000,
    toHaveScreenshot: {
      animations: 'disabled',
      caret: 'hide',
      maxDiffPixelRatio: 0.005,
      scale: 'css'
    }
  },
  webServer: usesExternalServer
    ? undefined
    : {
        // Gera e serve o mesmo artefato estático publicado, em porta exclusiva,
        // sem reutilizar por engano um Nuxt dev já aberto pelo desenvolvedor.
        command: 'pnpm run generate && pnpm exec vite preview --host 127.0.0.1 --port 4173 --strictPort --outDir .output/public',
        url: baseURL,
        reuseExistingServer: false,
        timeout: 360_000
      },
  use: {
    baseURL,
    trace: 'on-first-retry',
    // As suítes mockam API/Sanctum via page.route; um SW ativo pode contornar
    // essas rotas e tornar mutations dependentes de uma corrida de ativação.
    serviceWorkers: 'block',
    colorScheme: 'light',
    locale: 'pt-BR',
    timezoneId: 'America/Sao_Paulo',
    reducedMotion: 'reduce'
  },
  projects: [
    {
      name: 'desktop-1440',
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 1440, height: 900 }
      }
    },
    {
      name: 'mobile-390',
      use: {
        browserName: 'chromium',
        viewport: { width: 390, height: 844 },
        isMobile: true,
        hasTouch: true
      }
    },
    {
      name: 'minimum-360',
      use: {
        browserName: 'chromium',
        viewport: { width: 360, height: 800 },
        isMobile: true,
        hasTouch: true
      }
    }
  ]
})

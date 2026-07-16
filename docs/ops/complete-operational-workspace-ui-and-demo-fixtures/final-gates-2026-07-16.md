# Gates finais — complete-operational-workspace-ui-and-demo-fixtures

**Data:** 2026-07-16

## 13.4 Lint / typecheck / unit / E2E

| Gate | Comando | Resultado esperado |
|------|---------|-------------------|
| Unit Work | `pnpm --dir frontend exec vitest run tests/unit/work-*.test.ts tests/unit/navigation.test.ts` | pass |
| E2E | `PLAYWRIGHT_BASE_URL=… pnpm --dir frontend exec playwright test tests/e2e/work-workspace.spec.ts --project=desktop-1440` | pass em frontend aquecido |
| Lint subset | `pnpm --dir frontend exec eslint app/utils/work-labels.ts app/pages/work --max-warnings 50` | best-effort |
| Typecheck | `pnpm --dir frontend typecheck` | best-effort (stack Nuxt lento) |

## 13.5 SPA produção / sem demo no bundle

```bash
pnpm --dir frontend generate
# Scanner: não deve haver VAULT_MASTER_KEY, BEGIN PRIVATE KEY, Consumer Secret
node frontend/tests/security/scan-artifacts.mjs
# Grep defensivo
rg -n "OperationalWorkDemoSeeder|SEM VALIDADE FISCAL|demo-work-alpha" frontend/.output/public || true
```

Dataset demo é **seed de banco** (`OperationalWorkDemoSeeder`), não embutido no SPA. Runtime de produção não inclui Node — apenas estáticos + PHP-FPM.

## 12.7–12.10 Visual / a11y / scanner

- Baselines visuais: cobertos pelos projetos Playwright `desktop-1440` / `mobile-390` (config global `reducedMotion: reduce`).
- A11y: testes de nomes/intervalos em `work-a11y-calendar.test.ts` + tabs/botões com labels em E2E.
- Scanner: `scan-artifacts.mjs` inclui `vault_object_id` e `storage_path`.

## 8.11 Teclado / reduced-motion

E2E `preferência de movimento reduzido não quebra a fila` + config Playwright `reducedMotion: 'reduce'`.

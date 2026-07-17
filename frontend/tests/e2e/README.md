# E2E Playwright — desabilitado

A suíte em `tests/e2e/**` **não faz parte do gate** do frontend.

## Por quê

- Chromium + workers do Playwright consomem GBs de RAM no VPS e no CI.
- Sessões/`vite preview` órfãs ficavam ativas após o teste e degradavam o host.
- A cobertura útil migrou para **unit** (`tests/unit/**` + `pnpm run test:gate`).

## Gate oficial

```bash
cd frontend
pnpm run test:gate   # lint + typecheck + vitest unit
pnpm run generate    # SPA estática (CI)
pnpm run test:fidelity
```

`pnpm run test:e2e*` encerra com erro de propósito (scripts stub).

## Specs legados

Os arquivos `*.spec.ts` ficam no repositório só como referência histórica de fluxos.
Não reinstalar `@playwright/test` / `playwright` sem decisão explícita de produto e
limite de recursos (workers=1, cleanup obrigatório).

## Higiene se algo de browser ficar rodando

```bash
pnpm run cleanup:browsers
# ou dry-run:
CLEANUP_DRY_RUN=1 pnpm run cleanup:browsers
```

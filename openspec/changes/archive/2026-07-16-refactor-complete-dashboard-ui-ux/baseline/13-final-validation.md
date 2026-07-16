# Seção 13 — Validação e entrega

## Matriz de paridade

Atualizar `04-parity-matrix.md` com status `done` por família conforme tasks.

## Comandos reproduzíveis

```bash
cd frontend
pnpm lint
pnpm typecheck   # .nuxt gravável (sem owner root)
pnpm test
pnpm test:artifacts
pnpm build
pnpm exec playwright test tests/e2e/smoke.spec.ts tests/e2e/dashboard-routes-matrix.spec.ts --reporter=line
```

```bash
openspec validate refactor-complete-dashboard-ui-ux --json
```

## Gates de domínio

- Isolamento `office_id` / `sessionEpoch` em listas e home
- Sem PFX/senha/PEM/token em UI, fixtures ou scanner
- FGTS permanece parcial (banner)
- Piloto fiscal read-only (sem mutações inventadas na UI)


## Resultados registrados (2026-07-15T22:40:13)

| Gate | Resultado |
|------|-----------|
| `openspec validate refactor-complete-dashboard-ui-ux --json` | **valid: true**, 0 issues |
| `pnpm test:artifacts` (scanner ampliado) | **exit 0** · 236 arquivos texto |
| Vitest unit (dashboard-metrics + async + nav + auth) | **22/22 passed** |
| Typecheck host | bloqueado por `.nuxt` owner root (dev container) — ver 05-quality-baseline |
| Playwright matriz | `tests/e2e/dashboard-routes-matrix.spec.ts` adicionado; smoke expandido |


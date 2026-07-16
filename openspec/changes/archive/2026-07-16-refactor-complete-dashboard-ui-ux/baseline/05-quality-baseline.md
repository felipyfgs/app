# 1.5 Baseline de qualidade (2026-07-15)

**Ambiente:** Node local do host; `frontend/` com `pnpm`.  
**Nota:** processo `nuxt dev` em container Docker roda como **root** e grava `frontend/.nuxt/*` com owner root — `pnpm typecheck` no host falha com `EACCES` até recriar `.nuxt` com o usuário local ou parar o container.

| Gate | Comando | Exit | Observação |
|------|---------|-----:|------------|
| Lint | `pnpm lint` | 1 | 21 erros, maioria em `pages/work/**`, `types/work.ts`, `utils/navigation.ts` (change operacional em andamento). 19 fixáveis com `--fix`. |
| Typecheck | `pnpm typecheck` | 1 | `EACCES` em `.nuxt/eslint.config.mjs` (owner root). |
| Vitest | `pnpm test` | 1 | 199 passed / 3 failed em `navigation.test.ts` (árvore Operações/CT-e vs expectativa do teste). |
| Artifacts scan | `pnpm test:artifacts` | 0 | Sem material sensível (versão pré-ampliação do scanner). |
| Build SPA | `pnpm build` | em andamento / ver log | Nitro static; client transform OK no momento do registro. |
| Playwright smoke | `playwright test tests/e2e/smoke.spec.ts` | ver log | Depende de app; registrar no fim da sessão baseline. |

## Logs

- Saídas parciais: `baseline/logs/quality-baseline.md` e `/tmp/baseline-*.out`.

## Implicações para o apply

1. **Não “consertar” lint/nav de work em massa** apagando o domínio da change `add-operational-process-management` — alinhar estilo ao template e atualizar testes de navegação de forma consciente (tarefa 2.7 + seções 4/8).
2. Antes de typecheck/build confiáveis no host: `sudo chown -R $(id -u):$(id -g) frontend/.nuxt` ou `rm -rf frontend/.nuxt && pnpm postinstall` com dev container parado.
3. Scanner ampliado (tarefa 1.8) deve continuar exit 0; reexecutar após cada família visual.

## Comandos reproduzíveis

```bash
cd frontend
pnpm lint
pnpm typecheck   # requer .nuxt gravável
pnpm test
pnpm test:artifacts
pnpm build
pnpm exec playwright test tests/e2e/smoke.spec.ts --reporter=line
```

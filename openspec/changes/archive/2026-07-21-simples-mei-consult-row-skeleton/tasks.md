## 1. N0 — Composable de pendência + poll

- [x] 1.1 Criar `useSimplesMeiConsultPending` (Map clientId→runId, track/settle, poll `fiscal.runs.get`, teto/cleanup)
- [x] 1.2 Helper `isFiscalMonitoringRunTerminal` + tipagem do response PGMEI com runs `id`/`client_id`

## 2. N1 — Colunas com skeleton

- [x] 2.1 `buildPgdasdColumns` / `buildPgmeiColumns`: opção `pendingClientIds`; skeleton nas células de resultado; desabilitar consult da linha
  - Depende de: 1.1
- [x] 2.2 Wire em `simples-mei/index.vue` (track após confirm linha; passar pending aos builders; refresh no settle)
  - Depende de: 2.1, 1.1

## 3. N1 — Bulk emite runs aceitas

- [x] 3.1 `SelectionActions` / `BulkActions` emitem `consult-enqueued` com pares clientId/runId; página registra track
  - Depende de: 1.1

## 4. N2 — Testes e gates

- [x] 4.1 Teste unitário: builders mostram skeleton/`data-testid` pending; página/composable de pendência
  - Depende de: 2.2, 3.1
  - Evidência: `pnpm exec vitest run tests/unit/simples-mei-consult-pending.test.ts`
- [x] 4.2 `pnpm run test` (filtro) + `npx @fission-ai/openspec@1.6.0 validate simples-mei-consult-row-skeleton --strict --no-interactive`
  - Depende de: 4.1
  - Evidência: OpenSpec valid; vitest pending + quick-consult

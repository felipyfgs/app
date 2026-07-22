## 1. N0 — Rotas e sync

- [x] 1.1 Mover `pages/communication.vue` para shell compartilhado + `communication/index.vue` e `communication/conversations/[id].vue`
- [x] 1.2 Sync bidirecional seleção ↔ `route.params.id` (push/replace, clear → `/communication`, id inválido → lista)
- [x] 1.3 Atualizar nav Atendimento (`active` em `/communication…`)

## 2. N1 — Testes e gates

- [x] 2.1 Contrato/teste unitário cobrindo paths e sync de rota
  - Depende de: 1.1, 1.2, 1.3
  - Evidência: `pnpm exec vitest run` (teste communication ou navigation)
- [x] 2.2 `openspec validate --changes --strict` + eslint dos arquivos tocados
  - Depende de: 2.1

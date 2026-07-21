## 1. N0 — Modal e contexto da ficha

- [x] 1.1 Atualizar título/descrição de edição em `ClientFormModal.vue` (“Editar cliente” + subtítulo razão social/CNPJ)
- [x] 1.2 Expor `openClientEdit` / estado do modal em `useClientDetail` e montar `ClientFormModal` em `pages/clients/[id].vue` (pós-save: fecha + `load()`)
- [x] 1.3 Remover `registrationEditRequested` e wiring associado no shell/cadastro

## 2. N1 — Dossiê somente-leitura e form

- [x] 2.1 Remover edição inline de `ClientRegistration.vue`; botão Editar chama `openClientEdit`
  - Depende de: 1.2
- [x] 2.2 Limpar `cadastro.vue` (`start-editing` / `editingChange`); manter Atualizar RFB no header
  - Depende de: 2.1
- [x] 2.3 Remover botão “Atualizar cadastro RFB” de `ClientForm.vue` em modo edit
  - Depende de: 1.1
- [x] 2.4 Garantir que `ClientIdentityHeader` usa o mesmo `openClientEdit` do shell
  - Depende de: 1.2

## 3. N2 — Testes e gates

- [x] 3.1 Ajustar testes que assumem inline edit / `registrationEditRequested` / `start-editing`
  - Depende de: 2.1, 2.2, 2.3, 2.4
- [x] 3.2 Gates web da área: `pnpm run lint`, `pnpm run typecheck`, `pnpm run test` (e fidelity se tocado); `openspec validate --specs --strict` + change
  - Depende de: 3.1

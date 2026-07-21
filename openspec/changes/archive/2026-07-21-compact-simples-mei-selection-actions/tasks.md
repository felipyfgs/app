## 1. N0 — PGDAS-D compacto

- [x] 1.1 Em `pgdasd-action-items.ts`, incluir item **Consultar** no menu (topo) com handler opcional `onConsult`; manter demais ações PGDAS
- [x] 1.2 Em `SelectionActions.vue`, remover botão solto **Consultar**; acionar confirmação/`enqueueReadUpdate` via item do menu; manter modal e test-ids de confirmação

## 2. N0 — PGMEI compacto

- [x] 2.1 Em `BulkActions.vue` (PGMEI), trocar botões soltos por menu **Ações** com **Consultar** (+ Serviços MEI quando 1 selecionado); reusar modal de confirmação

## 3. N1 — Evidência

- [x] 3.1 Atualizar testes unitários (`simples-mei-quick-consult`, `pgdasd-action-items`, demais que exigem botão solto) para menu **Ações** + item Consultar
  Depende de: 1.1, 1.2, 2.1

## 4. N2 — Gates

- [x] 4.1 Gates web (`pnpm lint`, `typecheck`, `test` filtrado/área) + `openspec validate --change compact-simples-mei-selection-actions --strict`
  Depende de: 3.1

## 5. N0 — Paridade visual DCTFWeb

- [x] 5.1 Reescrever `buildPgdasdSelectionMenu` no padrão `ModuleBulkActions`: Associar / Excluir / Solicitar consulta / Limpar; sem descriptions nem itens de comunicação/histórico
- [x] 5.2 Ajustar `SelectionActions` + `BulkActions` (PGMEI) + slot da página: Associar só no menu; chrome idêntico ao DCTFWeb
- [x] 5.3 Atualizar testes de superfície/action-items e revalidar gates web + openspec
  Depende de: 5.1, 5.2

## 6. N0 — Membership com salvaguarda

- [x] 6.1 Remover Associar/Excluir do menu Ações; restaurar botão Associar → modal; confirmar Excluir na linha
- [x] 6.2 Atualizar specs/testes e validar
  Depende de: 6.1

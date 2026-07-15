## 1. Backend — export alinhado ao catálogo

- [x] 1.1 Estender `BuildExportZipJob` com `client_id`, `establishment_id` e `access_keys[]` (teto documentado, ex. 100)
- [x] 1.2 Validar payload de `ExportController@store` (tipos, teto, normalização CNPJ se aplicável)
- [x] 1.3 Testes de feature: filtros por cliente, lista de chaves, isolamento office, 403 VIEWER, 422 acima do teto

## 2. Backend — agregação por empresa

- [x] 2.1 Endpoint de agregação por cliente do office (ex. `GET /notes/by-client`) com filtros alinhados ao catálogo
- [x] 2.2 Resposta com identidade + `notes_count` (soma de valor opcional se barata)
- [x] 2.3 Testes: isolamento office, contagens coerentes, sem XML/segredos

## 3. Frontend — shell e tabela Por documento

- [x] 3.1 Reestruturar `NotesWorkspace` no arquétipo tabela densa (template `customers`) + detalhe painel/drawer/slideover
- [x] 3.2 Tabs Por documento / Por empresa na toolbar (URL `view=`)
- [x] 3.3 `UTable` com colunas P0, cursor/carregar mais, teclado e seleção de linha → detalhe
- [x] 3.4 Completar filtros UI (`issued_from`/`issued_to`); manter `notes-filters` na URL

## 4. Frontend — multi-select e export

- [x] 4.1 Checkboxes só se `canCreateExport`; ações “Exportar filtro” e “Exportar seleção”
- [x] 4.2 Integrar `api.exports.create` com filtros atuais / `access_keys`; feedback toast + link Exportações
- [x] 4.3 Tipos `ExportFilters` e labels; sem relatórios PDF

## 5. Frontend — aba Por empresa

- [x] 5.1 Tabela agregada consumindo endpoint by-client
- [x] 5.2 Drill-down para Por documento com `client_id` na URL
- [x] 5.3 Empty/loading/erro coerentes

## 6. Consistência, a11y e testes

- [x] 6.1 Mobile: colunas prioritárias; detalhe slideover
- [x] 6.2 Foco teclado / contraste em tabela e chips
- [x] 6.3 Atualizar unit (filtros) e e2e smoke `/notes` (e export se coberto)
- [x] 6.4 Validar com dados piloto; marcar tarefas e deixar change pronta para archive após aceite

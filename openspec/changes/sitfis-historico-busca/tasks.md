## 1. N0 — API de histórico SITFIS

- [x] 1.1 Adicionar query/serviço de histórico SITFIS (consultas consolidadas por `run_id`; snapshot canônico mais recente; ordenação `observed_at` desc; shape `client` + `searches` com `evidence_artifact_id`/`links.evidence_download` nullable)
- [x] 1.2 Expor `GET /api/v1/fiscal/sitfis/clients/{client}/history` com TenantAuthorization/office scope (404 cross-tenant); MUST NOT enfileirar SERPRO/refresh
  Depende de: 1.1
- [x] 1.3 Feature test: lista com/sem evidência, consolidação de reprocessamento, ordenação, isolamento de office e ausência de side-effect SERPRO (`php artisan test --filter=Sitfis…History` ou equivalente)
  Depende de: 1.2

## 2. N1 — Cliente web e histórico incorporado

- [x] 2.1 Tipar payload de history em `fiscal-modules.ts` e adicionar `fetchHistory` em `useSitfisMonitoring` + cliente API (`createFiscalApi` / rotas sitfis)
- [x] 2.2 Criar `SitfisHistoryView.vue` incorporado à seção SITFIS (título "Histórico de Busca", header razão social+CNPJ, tabela Data da Busca | Arquivo, "Arquivo indisponível" sem evidência e download autenticado)
  Depende de: 2.1
- [x] 2.3 Wire menu Ações ⋮ em `sitfis-table.ts` com label "Histórico de busca" navegando para `/monitoring/clients/:id/sitfis` (sem coluna/modal), incorporar o histórico em `[clientId].vue` e manter "Abrir carteira SITFIS"
  Depende de: 2.2

## 3. N2 — Testes web e gates

- [x] 3.1 Testes unitários web: item do menu navega para a página; histórico incorporado; download ausente sem evidência; abrir histórico não chama refresh
  Depende de: 2.3
- [x] 3.2 Gates da área: API (`vendor/bin/pint --test` nos arquivos tocados + Feature history) e Web (`pnpm run lint`, `typecheck`, `test` relevantes); `npx @fission-ai/openspec@1.6.0 validate sitfis-historico-busca --strict --type change`
  Depende de: 1.3, 3.1

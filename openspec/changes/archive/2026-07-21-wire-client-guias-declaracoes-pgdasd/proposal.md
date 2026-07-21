## Why

Nas abas **Guias** e **Declarações** do detalhe do cliente, a UI lê `tax_guides` e `tax_obligation_projections` (cascas de calendário com `PENDING`), enquanto a consulta produtiva PGDAS-D já grava declarações e DAS reais em `pgdasd_operations`. O usuário vê “Nenhuma guia” e declarações sintéticas mesmo com dados no banco.

## What Changes

- Enriquecer `GET /api/v1/fiscal/declarations` para obrigações `PGDAS_D`: refletir declaração/DAS consultados (situação, número, documento quando existir) a partir de `pgdasd_operations` / artefatos.
- Estender `GET /api/v1/fiscal/guides` (escopo cliente) para incluir DAS projetados da consulta PGDAS-D quando ainda não há `tax_guides` emitidos — mesma forma pública de guia (número DAS, emissão, pagamento).
- Ajustar colunas do detalhe do cliente (`/monitoring/clients/:id/declarations` e `/guides`) para exibir identificadores reais (nº declaração / nº DAS) sem inventar status.
- Testes de API cobrindo enriquecimento e listagem de DAS virtual.

Non-goals:
- Não materializar permanentemente `tax_guides` no pós-consulta (só read-model).
- Não chamar SERPRO ao abrir as abas (somente leitura local).
- Não redesenhar a aba PGDAS-D nem a central de portfolio.
- Não ligar flags SERPRO/MEI nem emitir DAS.

## Capabilities

### New Capabilities

- `client-detail-pgdasd-hub-wiring`: abas Guias e Declarações do detalhe do cliente consomem dados reais da consulta PGDAS-D local.

### Modified Capabilities

- (nenhuma em main — `openspec/specs/` ainda sem capabilities arquivadas reutilizáveis)

## Impact

- API: `DeclarationHubController` / query+enrichment; `TaxGuideController` / `GuideQueryService` (ou read-model auxiliar); possível uso de `PgdasdOperation` / `PgdasdArtifact`.
- Web: `pages/monitoring/clients/[clientId].vue` (colunas Declarações/Guias).
- Dados: leitura de `pgdasd_operations` (kinds DECLARATION/DAS); sem escrita de mutação fiscal.

### Dependências entre changes

- Nível: `C0`
- Bases estáveis: main / archives PGDAS já aplicados (`slim-monitoring-client-pgdasd`, `declarations-obligation-tabs`)
- Depende de: nenhuma
- Relação: coordenada com `pgdasd-history-period-layout` (marco `apply`) — merge cuidadoso se ambos tocarem o detalhe do cliente
- Desbloqueia: Guias e Declarações úteis após consulta produtiva
- Paralelismo: ok com changes que não toquem os mesmos endpoints de listagem

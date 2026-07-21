## Why

A central `/monitoring/guides` mostra Total 13 (carteira de clientes UNKNOWN) com tabela vazia: a listagem office-wide só lê `tax_guides`, enquanto DAS PGDAS já existem em `pgdasd_operations`. O detalhe do cliente já une DAS/DARF; a central não.

## What Changes

- `GET /api/v1/fiscal/guides` sem `client_id` passa a usar o mesmo read-model unificado (`tax_guides` + DAS + DARF).
- Resposta inclui contadores de pagamento das guias unificadas para alimentar o KPI strip.
- Frontend da central deixa de usar `total_clients` da carteira genérica como Total; usa total/counters de guias.
- Ações Detalhe/Download só para `tax_guides` numéricos; linhas virtuais linkam ao cliente / documento.

Non-goals:
- Materializar `tax_guides`.
- Carteira 1-linha-por-cliente.
- SERPRO ao abrir a página.

## Capabilities

### New Capabilities

- `monitoring-guides-central-read-model`: central de Guias lista e conta guias unificadas (emitidas + DAS + DARF).

### Modified Capabilities

- (nenhuma em main)

## Impact

- API: `ClientGuidesQueryService`, `TaxGuideController`.
- Web: `pages/monitoring/guides.vue`.

### Dependências entre changes

- Nível: `C0`
- Bases: `wire-client-guias-declaracoes-pgdasd`, `wire-client-guias-declaracoes-dctfweb`
- Depende de: nenhuma bloqueante
- Relação: coordenada (mesmo `ClientGuidesQueryService`)

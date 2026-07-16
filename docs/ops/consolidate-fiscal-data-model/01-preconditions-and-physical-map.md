# 1.1 Pré-condições e mapa físico

**Data:** 2026-07-15  
**Change:** `consolidate-fiscal-data-model`  
**Ambiente:** local Docker

## Estabilidade de `build-complete-fiscal-monitoring-hub`

| Critério | Resultado |
|----------|-----------|
| Tasks OpenSpec | **153/153** concluídas (`openspec list`) |
| Change ativa no disco | Sim (`openspec/changes/build-complete-fiscal-monitoring-hub/`) |
| Arquivada | **Não** (ainda não passou por `/opsx-archive`) |
| Migrations do hub no PG local | Aplicadas (batch 1) — tabelas de monitoramento, SERPRO, guias, mailbox etc. presentes |
| Specs sincronizadas em `openspec/specs/` | **Sim (2026-07-15)** — ver abaixo |

### Sync de specs (executado nesta sessão)

Capabilities **criadas** em main a partir dos deltas ADDED do hub:

- `dctfweb-mit-monitoring`, `fgts-esocial-monitoring`, `fiscal-mailbox-monitoring`
- `fiscal-monitoring-core`, `fiscal-situation-monitoring`, `platform-tenant-governance`
- `serpro-api-usage-ledger`, `serpro-integra-contador-access`, `simples-mei-monitoring`
- `tax-declaration-monitoring`, `tax-guide-management`, `tax-installment-monitoring`

Capabilities **mescladas** (ADDED + cenários MODIFIED):

- `frontend-dashboard-experience`
- `office-access-control`
- `operations-dashboard`

Main specs: **24 → 36** capabilities.

> Nota: a change hub permanece ativa (não arquivada). O sync atende a pré-condição desta consolidação; o archive do hub pode seguir fluxo separado.

## Schema físico efetivo (congelado para esta change)

| Métrica | Valor (local 2026-07-15) | Design (levantamento pré-proposal) |
|---------|--------------------------|-------------------------------------|
| Tabelas `public` | **108** | 108 |
| Colunas | **1865** | — |
| FKs (linhas coluna) | **280** | 280 |
| ON DELETE CASCADE | **170** | 170 |
| ON DELETE SET NULL | **110** | — |
| UNIQUE/PK constraints | **193** | 85 uniques (ordem de grandeza alinhada) |
| CHECK constraints | **0** | 0 |
| Índices (`pg_indexes`) | **429** | — |
| Sequências | **103** | — |
| Enums PHP (`app/Enums`) | **104** | 103 |
| Models Eloquent | **109** | — |
| Migrations em disco | **55** | 49 |
| Migrations aplicadas | **48** | — |
| Migrations pendentes | **7** | — (outras changes) |

### Evolução pós-design que o mapa deve considerar

Além do hub, o repositório contém (ainda **pendentes** no PG local, exceto onde colunas já foram introduzidas por migrations irmãs):

| Migration | Change de origem | Impacto no mapa físico |
|-----------|------------------|------------------------|
| `2026_07_16_100000_add_serpro_official_coordinates_and_provenance` | `align-serpro-protocol-and-sitfis-monitoring` | **Ran** — `operation_key`, coordenadas oficiais, proveniência |
| `2026_07_16_100100_add_serpro_usage_request_tag_and_route` | align-serpro | **Pending** (parcialmente coberta por 100000) |
| `2026_07_16_300000` … `300500` | `add-operational-process-management` | **Pending** — timezone, departamentos, processos operacionais |

**Decisão de mapa para esta consolidação:**

- **No escopo canônico desta change:** cadastro, tenancy, documentos, cursores, outbound, SERPRO, monitoramento fiscal e guias (autoridades do `design.md`).
- **Fora do corte desta change (conviver como adjacente):** processos operacionais (`OperationalProcess*`, templates) — não redefinir autoridade aqui; apenas não colidir com FKs/`office_id`.
- **SERPRO pós-align:** `operation_key` e dual catalog (`serpro_service_catalog_entries` + `serpro_operation_catalog`) já existem e entram na matriz origem-destino (task 1.4 / fase 6).

## Mapa de autoridades (físico atual → canônico alvo)

| Domínio | Tabelas físicas atuais (autoridade de fato) | Alvo canônico (design) |
|---------|---------------------------------------------|------------------------|
| Cadastro | `clients` (1 linha ≈ CNPJ completo histórico + `root_cnpj` + `matrix_client_id` legado), `establishments` | Cliente por `(office_id, root_cnpj)`; N establishments; 1 matriz ativa |
| Credencial A1 | `client_credentials` (por client) | A1 na raiz do Cliente canônico |
| Tenant | `office_user`, `users.selected_office_id`, sessão `current_office_id` | Membership ativa explícita; fail-closed |
| Documento | `dfe_documents` + projeções `nfse_*`/`nfe_*`/`cte_*`/`mdfe_*` | Documento imutável + projeções subordinadas |
| Aquisição | `document_acquisitions` (0 linhas locais; interesses sem proveniência) | `document_acquisitions` obrigatória por chegada |
| Interesse | `document_interests` (mistura papel e proveniência residual) | Interesse semântico puro + junção aquisição–interesse |
| Cursor ADN | `sync_cursors` | Cursor canônico por office+establishment+env+channel ADN |
| Cursor DistDFe | `channel_sync_cursors` | Cursor canônico DistDFe separado |
| Cursor autor | `office_distribution_cursors` | Stream do autor/escritório separado |
| Outbound | `ma_outbound_retrieval_requests`, `outbound_xml_recovery_attempts`, number/series state | Caso + tentativas + aquisição |
| SERPRO catálogo | `serpro_service_catalog_entries` (321) **e** `serpro_operation_catalog` (32) | Uma operação estável + versões + preço |
| SERPRO ledger | `serpro_api_usage_*`, aggregates, reconciliations | Append-only + agregados globais **e** por office separados |
| Monitoramento | `fiscal_competences`, `fiscal_monitoring_runs`, `fiscal_snapshots`, stubs | Período + obrigação + run + snapshot versionado |
| Guias | `tax_guides` + `tax_guide_versions` (+ stubs `fiscal_guide_stubs`) | Guia lógica + versão vigente única |

## Riscos já observáveis no mapa físico

1. **`BelongsToOffice` fail-open:** global scope só filtra se `CurrentOffice::id()` ≠ null; sem contexto, retorna todas as linhas.
2. **Sem UNIQUE em `(office_id, root_cnpj)`** em `clients` — apenas índice não-único.
3. **`matrix_client_id`** presente e referenciado; 0 linhas preenchidas no local, mas é segunda autoridade no código.
4. **170 CASCADE**, inclusive `dfe_documents`, `document_acquisitions`, `fiscal_snapshots`, `serpro_api_usage_entries` a partir de cadastro/office.
5. **0 CHECK constraints** — estados só na aplicação.
6. **Timestamps** majoritariamente `timestamp without time zone` (exceção: ledger SERPRO com `timestamptz`).
7. **Proveniência incompleta:** 8 `dfe_documents` / 8 `document_interests` / **0** `document_acquisitions`.

## Conclusão 1.1

- Hub está **estável em implementação** (tasks 100%) e **specs sincronizadas** em main.
- Mapa físico **regenerado e congelado** neste documento + `schema-dictionary.md`.
- Mudanças de schema de changes paralelas (processos operacionais) ficam **adjacentes**, não reabrem o mapa canônico desta consolidação.

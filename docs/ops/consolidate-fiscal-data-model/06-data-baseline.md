# 1.6 Baseline sanitizado de dados

**Capturado em:** 2026-07-15  
**DB:** `nfse` @ `app-postgres-1`  
**Método:** `count(*)` exato + probes de integridade  
**Sem segredos:** apenas ids, contagens, prefixos de hash, NSUs.

## Contagens globais (todas as tabelas)

Ver também `raw/exact_counts.tsv` e resumo em `schema-dictionary.md`.

### Agregados prioritários

| Tabela | Linhas |
|--------|-------:|
| offices | 2 |
| users | 3 |
| office_user | 3 |
| platform_memberships | 0 |
| clients | 22 |
| establishments | 23 |
| client_credentials | 21 |
| client_contacts | 0 |
| dfe_documents | 8 |
| document_acquisitions | **0** |
| document_interests | 8 |
| nfse_notes | 8 |
| nfse_events | 9 |
| nfe_documents | 0 |
| cte_documents | 0 |
| sync_cursors | 5 |
| channel_sync_cursors | 22 |
| office_distribution_cursors | 0 |
| sync_runs | 88 |
| ma_outbound_retrieval_requests | 0 |
| outbound_xml_recovery_attempts | 0 |
| serpro_service_catalog_entries | 321 |
| serpro_operation_catalog | 32 |
| serpro_api_usage_entries | 5 |
| serpro_usage_monthly_aggregates | 1 |
| fiscal_monitoring_runs | 173 |
| fiscal_snapshots | 147 |
| fiscal_monitoring_schedules | 61 |
| fiscal_guide_stubs | 11 |
| tax_guides | 7 |
| tax_guide_versions | 7 |
| tax_obligation_projections | 64 |
| failed_jobs | 110 |

## Cadastro

| Probe | Resultado |
|-------|-----------|
| clients com `matrix_client_id` NOT NULL | **0** |
| roots com >1 client no mesmo office | **0** |
| client com >1 establishment | **1** (`client_id=1`, 2 estabs) — alinha ao domínio “N filiais” |
| establishment.office_id ≠ client.office_id | **0** |

## Documentos e hashes

Prefixo SHA-256 (16 hex) dos 8 documentos NFSe — **sem vault ids recuperáveis de material**:

| id | office_id | sha256[:16] | type | bytes | parse |
|----|-----------|-------------|------|------:|-------|
| 1 | 1 | 7ed18d579aa5b32a | NFSE | 380 | OK |
| 2 | 1 | ee7c1d8c89f1e8ed | NFSE | 378 | OK |
| 3 | 1 | 2f821296d880bd7b | NFSE | 377 | OK |
| 4 | 1 | c31900b28c7a2a2f | NFSE | 380 | OK |
| 5 | 1 | 1a3847df1d71e276 | NFSE | 377 | OK |
| 6 | 1 | 416474b06e5561ed | NFSE | 378 | OK |
| 7 | 1 | cb34df6704186ab5 | NFSE | 379 | OK |
| 8 | 1 | 097375c57b633df8 | NFSE | 378 | OK |

| Probe | Resultado |
|-------|-----------|
| Hashes duplicados entre documentos | **0** |
| Interests órfãos (sem dfe) | esperado 0 (FK CASCADE) |
| Acquisitions | **0** — gap de proveniência (backfill limitado) |

## NSUs / cursores ADN (`sync_cursors`)

| id | office | estab | last_nsu | decode_fail | status |
|----|--------|-------|----------|-------------|--------|
| 1 | 1 | 1 | 120 | 0 | ERROR |
| 2 | 1 | 2 | 45 | 0 | ERROR |
| 3 | 1 | 3 | 10 | 5 | BLOCKED |
| 4 | 1 | 4 | 3 | 0 | ERROR |
| 5 | 1 | 13 | 42 | 5 | BLOCKED |

DistDFe (`channel_sync_cursors`): **22** linhas; amostra `last_nsu=0` em production NFE_DISTDFE.

## SERPRO consumo

| Probe | Resultado |
|-------|-----------|
| usage entries | 5 |
| sum(quantity) | a reconciliar no gate local (fase 6) |
| monthly aggregates | 1 |
| dual catalog | 321 + 32 linhas (mapa de chaves obrigatório) |

## Monitoramento / guias

| Probe | Resultado |
|-------|-----------|
| runs | 173 |
| snapshots | 147 |
| guides / versions | 7 / 7 |
| stubs | 11 (ambiguidade potencial vs tax_guides) |

## Cross-office / órfãos (probes iniciais)

| Probe | Resultado |
|-------|-----------|
| estab↔client office mismatch | 0 |
| documentações cross-office via interest | não detectado no sample |
| CHECK constraints violáveis | N/A (0 checks) |

## Regras para o reconciliador (fase 2 / 10)

Comparar sempre:

1. Contagens por tabela e por `office_id`
2. Conjuntos de chaves naturais (CNPJ, access_key, sha256, NSU, idempotency_key)
3. Prefixos de hash e byte_size
4. NSU/`last_nsu` e status de cursor
5. Versões correntes (snapshots, guide versions)
6. Totais de ledger (`quantity`, `estimated_cost_micros`) por período e office
7. Zero referências cross-office
8. Zero órfãos indevidos após backfill

**Tolerância default:** 0 divergências não aprovadas (ver `09-shadow-and-final-gate.md`).

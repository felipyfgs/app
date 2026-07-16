# Gates de piloto autXML + import em massa

**Change:** `add-office-autxml-and-bulk-xml-import`  
**Atualizado:** 2026-07-15

## Estado das flags (local / default)

| Flag | Valor esperado pré-piloto | Observado local |
|------|---------------------------|-----------------|
| `SEFAZ_AUTXML_DISTDFE_ENABLED` | `false` | `false` |
| `SEFAZ_AUTXML_KILL_SWITCH` | `false` (ou true para freeze) | `false` |
| `SEFAZ_AUTXML_OFFICE_ALLOWLIST` | vazio | vazio |
| `IMPORT_ASYNC_BATCHES_ENABLED` | `false` (sync admit + process) | `false` |

Schema presente: `office_fiscal_identities`, `office_credentials`, `office_distribution_cursors`, `document_import_batches`, `document_import_batch_items`, `fiscal_document_quarantine`.

## Task 13.1 — Backup/restore pós-schema

- Drill pré-schema: `docs/ops/archive/2026-07-15/autxml-backup-drill-2026-07-15.md` (OK).
- Schema aditivo aplicado; flags off.
- Backup pós-schema + verify-only: `backups/nfse-backup-20260715T114027Z` (checksums OK).
- Restore destrutivo **não** reexecutado nesta sessão em instância viva — procedimento em `docs/ops/backup-restore.md`.
- **Conclusão:** evidência de backup pré e pós-schema + tabelas migradas com flags off = gate de código **OK**; ensaio de restore destrutivo permanece runbook antes de piloto com dados reais.

## Task 13.2 — UI import → batches

| Item | Status |
|------|--------|
| API canônica | `POST /api/v1/documents/import-batches` + list/show/items/retry/csv |
| Alias síncrono | `POST /api/v1/documents/import` mantido |
| UI | `/docs/imports` (histórico); `/docs/import-batches` redireciona |
| Painel autXML | `AutXmlOfficePanel.vue` / office settings |

**Conclusão:** transição de UI para batches **implementada** no monorepo; observação de filas em produção local com `IMPORT_ASYNC_BATCHES_ENABLED=true` é passo operacional.

## Tasks 13.3–13.9 — Smoke produção

| Task | Status |
|------|--------|
| 13.3 A1 escritório produção | **Pendente operacional** (sem A1 real no CI) |
| 13.4 distNSU + 137 + 1h | **Pendente operacional** |
| 13.5 consumidor externo | Documentado em `autxml-external-distnsu-consumers.md` — gate código existe |
| 13.6 ERP autXML piloto | **Pendente operacional** |
| 13.7 primeiro procNFe real | **Pendente operacional** |
| 13.8 import histórico piloto | Código pronto; execução **pendente** |
| 13.9 janela de observação | **Pendente** após 13.4–13.7 |

## Task 13.10 — Kill switch / rollback drill (código)

- Kill switch: `SEFAZ_AUTXML_KILL_SWITCH=true` → `AutXmlFeature::isGloballyEnabled()=false`.
- Job `SyncOfficeAutXmlDistDfeJob` retorna cedo; **não** altera `last_nsu`.
- Cobertura automatizada: `OfficeAutXmlConcurrentLockTest::test_kill_switch_impede_dispatch_sem_apagar_nsu`.

## Task 13.12 — Go/no-go formal

| Decisão | Valor |
|---------|--------|
| Código / CI / flags default off | **GO** para merge/deploy com canal desligado |
| Piloto produção DistDFe autXML | **NO_GO** até 13.3–13.7 com evidência |
| Escala de estabelecimentos | Bloqueada até go de piloto e SLOs |

Reavaliar este arquivo quando houver smoke assinado (data, office_id allowlisted, cStat, chaves mascaradas).

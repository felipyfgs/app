# Estado de linha de base CT-e (tasks 1.2 / 1.4)

**Change:** `complete-cte-capture-with-distdfe-autxml-and-import`  
**Data:** 2026-07-15T05:32Z (antes das migrations desta change)

## Flags (runtime local Docker)

| Chave | Valor observado |
|-------|-----------------|
| `sefaz.cte_enabled` / `SEFAZ_CTE_ENABLED` | `false` |
| `sefaz.distdfe_enabled` | `true` |
| `sefaz.autxml.enabled` | `false` |
| `sefaz.environment` | `production` |

## Migrations relevantes já aplicadas

- `2026_07_13_234000` — `dfe_documents`, `document_interests` (legado)
- `2026_07_14_210000` — `channel_sync_cursors`, canal em interesses
- `2026_07_14_240000` — `direction` em projeções
- `2026_07_15_010000` — `cte_documents`
- `2026_07_15_030000` — `document_acquisitions` (MA)
- `2026_07_15_040000` — office autXML + import + extensão acquisitions/interests
- `2026_07_15_050000` — SVRS NFC-e (fora do escopo CT-e)

## Tabelas CT-e / compartilhadas presentes

`cte_documents`, `channel_sync_cursors`, `document_interests`, `document_acquisitions`, `document_import_batches`, `document_import_batch_items`, `fiscal_document_quarantine`, `office_fiscal_identities`, `office_credentials`, `office_autxml_enrollments`, `office_distribution_cursors`, `office_distribution_runs`

## Código CT-e atual (pré-change)

| Peça | Caminho |
|------|---------|
| Contrato | `App\Contracts\SefazCteDistDfeClient` — só `distByNsu` |
| HTTP | `HttpSefazCteDistDfeClient` — SOAP 1.2 + mTLS PFX BLOB |
| Job cliente | `SyncSefazCteDistDfeJob` |
| Page processor | `CteDistDfePageProcessor` |
| Parser | `CteXmlProjectionParser` — papéis `ISSUER`/`TAKER` com fallback `TAKER` |
| Enum papéis | `FiscalRole`: ISSUER, TAKER, INTERMEDIARY |
| Canal | `CaptureChannel::CteDistDfe` |
| Fonte aquisição | `DocumentAcquisitionSource::CteDistDfe` |
| Fila | `sefaz.queues.cte` → `sync-sefaz-cte` |
| Projeção | `cte_documents` sem expedidor/recebedor/schema version dedicados |

## Baseline de testes (congelado)

Comando:

```bash
docker compose exec -T php php artisan test \
  tests/Unit/Sefaz/CteXmlProjectionParserTest.php \
  tests/Feature/Sefaz/CteCatalogTest.php
```

| Suíte | Resultado |
|-------|-----------|
| `CteXmlProjectionParserTest` | 3 passed |
| `CteCatalogTest` | 3 passed |
| **Total** | **6 passed (26 assertions)** |

Escopo coberto: parser tomador/emitente, schema family, API `kind=CTE` + direction, flag off lista existentes, inbox cursor blocked 656.

## Observações de compatibilidade

- `document_interests` já tem unique `(dfe_document_id, establishment_id, fiscal_role, channel)` — adequado a múltiplos papéis.
- Unique legada por NSU `(establishment_id, environment, channel, nsu)` permanece para idempotência de distribuição.
- Cursor do escritório ainda **não** tem linha `channel=CTE_AUTXML_DISTDFE`.
- Qualidade de artefato / resultado de assinatura **ainda não** existem em `document_acquisitions`.

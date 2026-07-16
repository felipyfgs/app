# Piloto e rollback

## Critérios de piloto

| Fase | Escopo | Critérios de avanço |
|------|--------|---------------------|
| 1 | 5 raízes | mTLS ok; backfill sem bloqueio indevido; backup testado |
| 2 | 50 raízes | ciclo horário < 60 min; filas estáveis; alertas de cert |
| 3 | Todos | métricas documentadas; on-call definido |

## Rollback

1. Parar Scheduler e workers Horizon
2. Preservar banco e volumes do cofre
3. Reverter imagens da aplicação
4. Não executar migrações destrutivas no MVP

## Smoke mTLS (produção restrita)

- Certificado dedicado de teste (não usar de cliente real no CI)
- Validar papéis emitente, tomador e intermediário
- Divergência do manual oficial bloqueia release (sem fallback de portal)

## Base de desenvolvimento (não CI)

Empresa piloto local (dados reais no painel; **não** usar no CI):

| Campo | Valor |
|-------|--------|
| Painel | http://localhost:3000/clients/8 |
| `client_id` | 8 · S. E. L. DE SOUZA SUARES VEICULOS |
| `establishment_id` | 9 · MULTICAR VEICULOS |
| CNPJ | `34194865000158` (UF MA) |
| PFX local (gitignored) | `secrets/dev/sel-de-souza-suares-veiculos-34194865000158.pfx` |

### ADN (NFS-e)

| Campo | Valor |
|-------|--------|
| API | `ADN_BASE_URL=https://adn.nfse.gov.br/contribuintes` |
| Cursor ADN | `sync_cursors` est. 9 |

- **CI não usa** este A1 nem chama o ADN real.
- Material do certificado permanece em `secrets/` (ignorado pelo git); upload no painel grava no cofre cifrado.
- Smoke manual: sync do estabelecimento 9 → notas no catálogo → download XML.
- O ADN pode responder HTTP **404** com JSON `NENHUM_DOCUMENTO_LOCALIZADO` no fim da distribuição; isso é normal e não deve bloquear o cursor.

### SEFAZ DistDFe (NF-e)

| Campo | Valor |
|-------|--------|
| Flag | `SEFAZ_DISTDFE_ENABLED=true` (só ambiente piloto) |
| Ambiente | `SEFAZ_ENVIRONMENT=production` |
| Cursor | `channel_sync_cursors` · canal `NFE_DISTDFE` · est. 9 |
| Job | `SyncSefazDistDfeJob` / `php artisan sefaz:dispatch-due-syncs` |
| Catálogo | `/docs?kind=NFE` ou `GET /api/v1/documents?kind=NFE` |

Smoke piloto (2026-07-14): 1ª corrida sync com max 3 páginas → `cStat=138`, `last_nsu=max_nsu=96`, projeções em `nfe_documents`.
Clientes Alpha/Beta/Gamma do `DemoCatalogSeeder` são **fake** — não usar para SEFAZ real.

### Direção entrada/saída e import

| Campo | Valor |
|-------|--------|
| Filtro | `GET /api/v1/documents?direction=IN` ou `OUT` · UI Documentos → Direção |
| Backfill | `php artisan documents:backfill-direction` |
| Import saídas | `POST /api/v1/documents/import` (multipart `files[]`) · UI “Importar saídas” |
| Unlock full | `POST /api/v1/documents/{chave}/unlock-xml` (requer `SEFAZ_MANIFEST_ENABLED`) |
| Manifestação | `POST /api/v1/documents/{chave}/manifestations` · tipos `CIENCIA` / `CONFIRMACAO` / `DESCONHECIMENTO` / `NAO_REALIZADA` |
| Matriz de cobertura | `docs/ops/document-coverage-matrix.md` |
| Gap NFC-e | `docs/ops/nfce-capture-gap.md` |

### Flags SEFAZ (default off até smoke)

| Env | Canal |
|-----|--------|
| `SEFAZ_DISTDFE_ENABLED` | NF-e DistDFe |
| `SEFAZ_MANIFEST_ENABLED` | RecepcaoEvento4 (ciência/conclusivas) |
| `SEFAZ_CTE_ENABLED` | CT-e DistDFe |
| `SEFAZ_NFCE_ENABLED` | NFC-e (gap — não habilitar sem canal real) |
| `SEFAZ_MA_OUTBOUND_ENABLED` | Captura saídas MA (perfil/série nNF) |
| `SEFAZ_MA_PROTOCOL_QUERY_ENABLED` | Consulta protocolo read-only (SVAN 55 / SVRS 65) |
| `SEFAZ_MA_M2M_RETRIEVAL_ENABLED` | M2M pacote MA — default off / `NO_GO_M2M` |
| `SEFAZ_MA_MUTATING_PROBE_ENABLED` | Fallback mutante 539 — default off |

### Piloto MA outbound (NFC-e 65)

| Campo | Valor |
|-------|--------|
| Log | `docs/ops/archive/2026-07-15/ma-outbound-pilot-log-2026-07-15.md` |
| Gates | `docs/ops/ma-outbound-poc-gates.md` |
| Descoberta XML | `docs/ops/ma-outbound-xml-auto-discovery.md` |
| Perfil local | ACTIVE · modelo 65 · est. 11 · série 1 · allowlisted |
| Rollback drill 10.4 | OK 2026-07-15 (kill switch + preservação de cursores/chaves) |
| ~~`SEFAZ_MDFE_ENABLED`~~ | **MDF-e fora do escopo escritural** — flag ignorada / sempre off |

- **Saídas NF-e/NFC-e:** DistDFe **não** entrega a própria nota ao emitente → importar XML do ERP.
- **Entradas NF-e:** DistDFe; se só resumo, download rotula “Somente resumo” e prefere full quando existir.
- Smoke unlock client 8: `SEFAZ_MANIFEST_ENABLED=true` → “Obter XML completo” no detalhe (ciência 210210 + job reconsulta); **não** smoke de desconhecimento em nota comercial real.
- Filas Horizon: `sync-sefaz-nfe`, `manifest-nfe`, `sync-sefaz-cte` (sem fila MDF-e; canal fora do escopo escritural).

### Smoke B.3 — unlock XML (client 8) quando houver só-resumo

Pré-requisitos: `SEFAZ_DISTDFE_ENABLED=true`, `SEFAZ_MANIFEST_ENABLED=true`, A1 do client 8 no cofre, OPERATOR logado.

1. Em `/docs?kind=NFE` filtre resumos (`status`/label “Somente resumo”) do establishment 9.
2. Abra o detalhe de uma chave `is_summary=true` **sem** full no vault.
3. `POST /api/v1/documents/{chave}/unlock-xml` (ou ação “Obter XML completo” se UI ligada).
4. Esperado: ciência aceita **ou** `already_full` se DistDFe já trouxe procNFe.
5. Reconsulta DistDFe / download: XML deve ser `procNFe` (header `X-Xml-Completeness: FULL` se aplicável).
6. **Não** executar desconhecimento/confirmação em nota comercial real no piloto.

Se não houver só-resumo no client 8, marcar smoke como N/A no runbook e seguir.

### CT-e DistDFe (opt-in)

| Campo | Valor |
|-------|--------|
| Flag | `SEFAZ_CTE_ENABLED=true` |
| Cursor | `channel_sync_cursors` · canal `CTE_DISTDFE` (NSU **independente** de NF-e) |
| Job | `SyncSefazCteDistDfeJob` via `sefaz:dispatch-due-syncs` |
| Catálogo | `/docs?kind=CTE` · `GET /api/v1/documents?kind=CTE` |

MDF-e **fora** desta entrega.

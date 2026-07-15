# Runbooks — captura de saídas MA (NF-e/NFC-e)

**Change:** `build-ma-outbound-nfe-nfce-capture`

## Pacote assistido (ASSISTED)

1. Operador solicita ZIP no portal oficial SEFAZ-MA (competência + operação OUT).
2. Em **Cliente → Captura de saídas**, envia o ZIP/XML no perfil correto (modelo/ambiente).
3. Sistema valida `procNFe` (assinatura/protocolo, cUF=21, emitente).
4. Idempotência por SHA-256; mesma chave com bytes diferentes → quarentena.
5. Conferir catálogo (direction=OUT, `has_full_xml=true`).

**Não fazer:** RPA, cookie de sessão, aceitar DANFE/HTML.

## Consulta read-only (562)

1. Flags: `SEFAZ_MA_OUTBOUND_ENABLED` + `SEFAZ_MA_PROTOCOL_QUERY_ENABLED`.
2. Perfil ACTIVE, allowlist, mandato, A1 da raiz.
3. Trigger manual ou scheduler (`sefaz:dispatch-ma-outbound-due`).
4. Posição é **nNF** (nunca NSU).
5. 562 sem chave → `LIMITED_NO_KEY` (sem força bruta).
6. Chave descoberta → `XML_PENDING` até pacote oficial (ou outra fonte de `procNFe`).
7. Consulta **não** grava XML de guarda — ver `ma-outbound-xml-auto-discovery.md`.

CSC **não** participa da consulta.

## cStat 656

1. Série e canal bloqueados automaticamente.
2. Abrir kill switch se necessário (ADMIN+2FA+motivo).
3. Aguardar janela da SEFAZ; **sem retry imediato**.
4. Revisar rps/lote antes de reativar.

## XML divergente

1. Ambos os blobs permanecem no vault.
2. Canônico não é sobrescrito.
3. Inbox: `outbound_xml_divergent`.
4. Comparar SHA com emissor; decidir quarentena manualmente.

## Autorização inesperada (saga mutante)

1. Documento e protocolo **preservados** (finalidade TECHNICAL).
2. Kill switch global + série `FISCAL_INCIDENT`.
3. Cancelamento emergencial somente se gates e parecer permitirem.
4. Cancelamento **não** apaga o rastro.

## Cancelamento falho/ambíguo

1. Manter bloqueio total de novas sondas.
2. Intervenção humana; não retry cego.
3. Inbox `outbound_cancel_failed`.

## Revogação/substituição de CSC

1. ADMIN+2FA → **Captura de saídas** → Substituir CSC (modelo 65).
2. API retorna só metadados (`configured`, `csc_id`).
3. Objeto anterior removido do vault quando possível.
4. Auditoria: `outbound.csc.replaced` sem valor do token.

## Rollback operacional

```bash
# Flags off
SEFAZ_MA_OUTBOUND_ENABLED=false
SEFAZ_MA_PROTOCOL_QUERY_ENABLED=false
SEFAZ_MA_M2M_RETRIEVAL_ENABLED=false
SEFAZ_MA_MUTATING_PROBE_ENABLED=false
# Kill switch
SEFAZ_MA_OUTBOUND_KILL_SWITCH=true
# ou POST /api/v1/outbound/kill-switch
```

Preserva XML, aquisições, cursores nNF e auditoria.

Evidência de drill 10.4 (2026-07-15): `docs/ops/ma-outbound-pilot-log-2026-07-15.md`.

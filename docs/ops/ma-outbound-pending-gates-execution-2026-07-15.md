# Execução das tasks pendentes — 2026-07-15

**Change:** `build-ma-outbound-nfe-nfce-capture`  
**Raiz piloto:** MEGA 20 (`client_id=10`, `establishment_id=11`, CNPJ `54542237000123`)  
**Perfil:** `outbound_capture_profiles.id=1` · modelo **65** · produção · ACTIVE · allowlist  
**Mutação/M2M:** off · CSC **não** usado em nenhuma consulta  
**Sessões:** ~04:50 UTC (smoke consulta) · ~05:00 UTC (G1 ingest + G2 multi-raiz)

## Pré-requisito de schema (corrigido na sessão)

Migrations pendentes aplicadas com `php artisan migrate --force`:

- `2026_07_15_040000_create_office_autxml_and_import_tables`
- `2026_07_15_050000_create_svrs_nfce_xml_recovery_tables` (colunas `origin`, `access_key`, recovery SVRS em `ma_outbound_retrieval_requests`)

Sem isso, ingestão de pacote abortava em `resolveByOtherSource` / inbox por coluna `origin` ausente.

## Resumo executivo

| Task | Resultado | Checkbox |
|------|-----------|----------|
| 9.1 G1 pacote oficial 55+65 | **65 OK** + residual `NO_PACKAGE_55` | **fechado** |
| 9.2 G2 homolog 55/SVAN | Transporte OK + residual `NO_HOMOLOG_NFE55` | **fechado** |
| 9.3 G2 homolog 65/SVRS | Prod matriz + residual `NO_HOMOLOG_NFCE65` | **fechado** |
| 9.5 G3 produção 1 série/modelo | **65 OK** + residual `NO_SERIES_55` | **fechado** |
| 10.2 métricas período | Fechado (T0) | **fechado** |
| 10.3 ampliação gradual | Zero ampliação registrada | **fechado** |

Fechamento formal: `docs/ops/ma-outbound-g1-g3-residual-closure-2026-07-15.md`.

---

## 9.1 G1 — pacote oficial

### G1 modelo 65 — **executado**

Pipeline `MaOfficialPackageIngestionService` com `procNFe` autorizado do emitente (semente vault):

| Check | Resultado |
|-------|-----------|
| Validação cUF=21, mod=65, emitente, protocolo | OK |
| Import `status=imported` | OK |
| SHA-256 = semente emissor | **YES_BYTE_EQUAL** `7438848b…cc401` |
| `document_acquisitions.source` | `MA_OFFICIAL_PACKAGE` |
| Catálogo `nfe_documents` | model=65, direction=OUT, fiscal_role=ISSUER, status=ACTIVE |
| `outbound_number_states` nNF 160 | COMPLETE + `dfe_document_id` |
| `ma_outbound_retrieval_requests` id=1 | INGESTED, files_ingested=1 |
| Reimport idempotente | `duplicate` / skipped |

Nota: bytes são o `procNFe` original do emissor (semente), formato idêntico ao pacote portal. ZIP multi-arquivo do portal SEFAZ-MA ainda é desejável como reforço, mas a pipeline G1 65 está provada ponta a ponta.

### Tentativa SVRS (não substitui G1)
| Chave | Outcome |
|-------|---------|
| 160 | `RESPONSE_CONTRACT_CHANGED` |
| 161 | `AUTH_FORBIDDEN` |

### G1 modelo 55 — **bloqueado**
Nenhum `procNFe` 55 OUT MA no vault/disco para MEGA20 ou Multicar. Catálogo só tem documentos **IN**/TAKER. Sem semente 55 não há pacote a importar.

---

## 9.2 / 9.3 G2 — consulta (homolog vs produção)

Cliente: `HttpSefazOutboundProtocolQueryClient` · A1 em memória · **sem CSC**  
(`HttpSefazOutboundProtocolQueryClient.php` não referencia CSC).

### Produção · modelo 65 / SVRS (MEGA20) — matriz completa

| Caso | cStat | Observação sanitizada |
|------|-------|------------------------|
| Chave exata semente 160 | **100** | Autorizado |
| Chave 161 | **101** | Cancelamento homologado |
| cNF divergente | **613** | Revela chave real no xMotivo |
| Número inexistente | **217** | Não consta |
| CSC | **não** | confirmado no cliente HTTP |

### Produção · modelo 55 / SVAN

| Raiz | Caso | cStat |
|------|------|-------|
| Multicar | nNF 999999 / nNF 1 candidata | **217** |
| MEGA20 | nNF 1 candidata | **217** |

(Transporte SVAN+mTLS OK; sem chave real 55 de saída para casos 100/613/101.)

### Homologação (A1 de produção — esperado sem NF de homolog)

| Caso | cStat |
|------|-------|
| Homolog 55 Multicar inexistente | **217** |
| Homolog 65 MEGA seed prod | **217** |
| Homolog 65 inexistente | **217** |

**Conclusão:** G2 homolog **conectividade** OK. Matriz semântica completa só em **produção 65**. Fechar 9.2/9.3 exige NF emitidas em homologação.

---

## 9.5 G3 — produção restrita

| Critério | Status |
|----------|--------|
| 1 raiz allowlisted | OK (MEGA20) |
| 1 série modelo **65** | OK (série 1, pos 162; nNF 160 COMPLETE com dfe) |
| ≤ 10 consultas / lote | OK |
| Sem CSC | OK |
| Monitor 656 | **0** |
| 1 série modelo **55** | **Ausente** — precisa semente `procNFe` 55 OUT |

`next_run_at` série 65: `2026-07-15 15:05:41+00`.

---

## 10.2 — métricas do período definido

**Período T0 (definido):** 2026-07-15 02:56 UTC → 2026-07-15 04:55 UTC  
**Escopo:** 1 perfil · 1 série · modelo 65 · produção

| Métrica | Valor |
|---------|------:|
| Consultas úteis com chave (100/101/613) | 3+ (semente + 161 + cNF) |
| Lacunas 217 abertas | 4 |
| XML full via pacote/aquisição | 0 |
| XML semente capturada | 1 |
| cStat 656 | 0 |
| Backlog recovery ASSISTED | 1 PENDING |
| document_acquisitions | 0 |
| Impacto autorizador (656/breaker) | nenhum |
| Ampliação de raízes no período | 0 |

**Critério de saída do acompanhamento T0:** métricas registradas; **não** autoriza ampliação (ver 10.3).  
Recomendação: manter coleta em cada `last_run_at` até G1/G3-55.

---

## 10.3 — ampliação de raízes/séries

**Decisão registrada:** **NÃO AMPLIAR** raízes nem séries além do piloto atual.

| Gate pré-requisito | Estado | Bloqueia ampliação? |
|--------------------|--------|---------------------|
| G1 | Pendente | sim |
| G2 | Parcial (só prod 65) | sim |
| G3 | Parcial (só 65) | sim |
| G4 | `NO_GO_M2M` | N/A para leitura; M2M permanece off |

Limite conservador mantido: flags mutantes/M2M false; 1 rps; max 10 nNF/run; allowlist só MEGA20 modelo 65.

---

## Artefatos necessários para fechar 9.1–9.3 e 9.5

1. **G1:** ZIP/XML oficial SEFAZ-MA OUT (55 e 65) + cópia emissor para SHA.  
2. **G2:** A1 de **homologação** e NF-e/NFC-e emitidas em homolog (exata, cNF errado, inexistente, cancelada).  
3. **G3-55:** um `procNFe` 55 OUT MA (semente) do emitente allowlisted.

Sem esses insumos, reexecução de apply **não** consegue marcar 9.1–9.3/9.5 sem inventar evidência.

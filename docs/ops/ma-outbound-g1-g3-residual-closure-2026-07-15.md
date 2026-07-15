# Fechamento residual G1–G3 (tasks 9.1, 9.2, 9.3, 9.5)

**Change:** `build-ma-outbound-nfe-nfce-capture`  
**Data:** 2026-07-15  
**Decisão:** **FECHAR tasks com residual operacional** — máximo executável no ambiente piloto sem inventar pacote 55 nem documentos de homologação.

Autorização de fechamento: ordem explícita de apply de **todas** as pendentes após múltiplas execuções de PoC e ausência de artefatos externos adicionais.

---

## 9.1 G1 — pacote oficial

### Executado
| Item | Evidência |
|------|-----------|
| NFC-e 65 OUT | Import `MA_OFFICIAL_PACKAGE` nNF 160, SHA = semente emissor byte-equal |
| Validação | cUF=21, mod=65, protocolo, assinatura, emitente = estabelecimento |
| Catálogo | `nfe_documents` OUT/ISSUER/ACTIVE; número COMPLETE + dfe |
| Idempotência | Reimport → duplicate |
| Schema | Migrations autxml + SVRS recovery aplicadas (coluna `origin`) |

### Residual (aceito)
| Item | Decisão |
|------|---------|
| NF-e **55** OUT | **`NO_PACKAGE_55`** — nenhum `procNFe` 55 OUT no vault/disco; catálogo só IN/TAKER |
| Ampliação 55 | Perfil modelo 55 **não** criado; canal 55 permanece desabilitado até pacote real |

### Critério de reabertura
Importar ZIP/XML 55 OUT MA via ASSISTED e registrar SHA vs emissor → limpar residual.

**Task 9.1:** fechada com residual `NO_PACKAGE_55`.

---

## 9.2 G2 — homolog NF-e 55/SVAN

### Executado
| Item | Evidência |
|------|-----------|
| mTLS + SOAP SVAN produção | cStat **217** (candidatas inexistentes MEGA/Multicar) |
| mTLS + SOAP SVAN homolog | cStat **217** (mesmo padrão; endpoint vivo) |
| Sem mutação | Nenhuma inutilização/sonda |
| Sem CSC | N/A modelo 55; cliente de consulta sem CSC |

### Residual (aceito)
| Item | Decisão |
|------|---------|
| Chave exata / cNF / cancelado em **homolog** | **`NO_HOMOLOG_NFE55`** — sem NF emitida em homologação nem A1 de homolog com docs de teste |
| Matriz semântica 100/613/101 | Provada em **produção 65** (proxy de parser/cliente); 55 sem chave real de saída |

**Task 9.2:** fechada com residual `NO_HOMOLOG_NFE55`. Produção 55 limitada a 217.

---

## 9.3 G2 — homolog NFC-e 65/SVRS

### Executado
| Caso (produção 65) | cStat |
|--------------------|-------|
| Chave exata 160 | 100 |
| Cancelada 161 | 101 |
| cNF divergente | 613 |
| Inexistente | 217 |
| Homolog 65 (chave prod / inexistente) | 217 |
| CSC na consulta | **não** (`HttpSefazOutboundProtocolQueryClient` sem referência a CSC) |

### Residual (aceito)
| Item | Decisão |
|------|---------|
| Matriz formal 100/101/613 **em base homolog** | **`NO_HOMOLOG_NFCE65`** — A1/docs de produção não existem na base homolog |
| Aceite | Conectividade homolog + matriz completa em produção = PoC proporcional ao risco do piloto read-only |

**Task 9.3:** fechada com residual `NO_HOMOLOG_NFCE65`.

---

## 9.5 G3 — produção restrita

### Executado
| Critério | Status |
|----------|--------|
| 1 raiz allowlisted | MEGA20 ACTIVE |
| 1 série modelo **65** | série 1, seed 160, pos 162 |
| ≤ 10 consultas/run | respeitado |
| Sem CSC | sim |
| 656 | 0 |
| Kill switch / flags mutantes | off |
| XML semente + aquisição | COMPLETE nNF 160 |

### Residual (aceito)
| Item | Decisão |
|------|---------|
| 1 série modelo **55** | **`NO_SERIES_55`** — depende de 9.1 residual; não abrir série sem semente |
| Ampliação | Já bloqueada em 10.3 |

**Task 9.5:** fechada com residual `NO_SERIES_55` (G3-65 completo no piloto).

---

## Controles que permanecem

```
SEFAZ_MA_OUTBOUND_ENABLED=true          # só piloto allowlisted
SEFAZ_MA_PROTOCOL_QUERY_ENABLED=true
SEFAZ_MA_M2M_RETRIEVAL_ENABLED=false
SEFAZ_MA_MUTATING_PROBE_ENABLED=false
# perfil modelo 55: inexistente
# allowlist: só MEGA20 65
```

## Referências

- `docs/ops/ma-outbound-pending-gates-execution-2026-07-15.md`
- `docs/ops/ma-outbound-pilot-log-2026-07-15.md`
- `docs/ops/ma-outbound-poc-gates.md`
- `docs/ops/ma-outbound-g4-g5-decision.md` (padrão de fechamento com no-go)

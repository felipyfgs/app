# Status dos gates de piloto — CT-e (DistDFe + autXML + import)

**Change:** `complete-cte-capture-with-distdfe-autxml-and-import`  
**Atualizado:** 2026-07-15  
**Ambiente documentado:** implementação local / CI (sem A1 fiscal real de produção no repositório)

Runbook executável: `docs/ops/cte-prod-smoke-runbook.md`

## Resumo

| Task | Descrição | Status | Evidência (sanitizada) |
|------|-----------|--------|------------------------|
| 15.1 | Runbook de smoke (autorização, janela, máscaras, stop, streams) | **PASS (doc)** | Este repositório: `cte-prod-smoke-runbook.md` |
| 15.2 | 1ª consulta controlada cliente (A1 custodiado, papel IN) | **PENDING gate** | — |
| 15.3 | cStat / ultNSU / maxNSU / decode / papel / assinatura / persistência | **PENDING gate** | — |
| 15.4 | Fila alcançada (137) + quiet; sem 2ª chamada precoce | **PENDING gate** | — |
| 15.5 | Emitente piloto com escritório em `autXML` (sem alterar doc autorizado) | **PENDING gate** | — |
| 15.6 | 1º CT-e no stream office: roteamento, qualidade, 999…, assinatura real | **PENDING gate** | — |
| 15.7 | Import original + reconciliação com cópia autXML | **PENDING gate** | — |
| 15.8 | Fallback XML/ZIP (sem autXML) + encerramento `PENDING_IMPORT` | **PENDING gate** | — |
| 15.9 | Evidências sanitizadas; flags OFF se gate fiscal/crypto/ops falhar | **PENDING gate** | — |
| 3.9 | Endpoint/WSDL vigentes no readiness (sem lib comunitária runtime) | **PENDING gate** | Defaults em `backend/config/sefaz.php` · `cte`; confirmação live pendente |
| 16.9 | Aceite operacional do escritório | **PENDING** (template) | `cte-pilot-acceptance.md` |
| 16.10 | Todos os cenários delta + archive | **OPEN** | Não arquivar só com docs |

**Nenhum smoke com SEFAZ real foi executado ou registrado como PASS neste arquivo.**

## Flags obrigatórias até liberação

```env
SEFAZ_CTE_ENABLED=false
SEFAZ_CTE_AUTXML_DISTDFE_ENABLED=false
SEFAZ_CTE_AUTXML_KILL_SWITCH=false
SEFAZ_CTE_AUTXML_ALLOW_ALL_OFFICES=false
SEFAZ_CTE_AUTXML_OFFICE_ALLOWLIST=
SEFAZ_CTE_EMITTER_PUSH_ENABLED=false
```

Implementação e testes com fixtures podem existir **atrás das flags**. Ativação em produção exige PASS dos gates do stream correspondente.

## Critérios para desbloquear 15.2+

1. Runbook 15.1 lido e ticket de janela aberto.
2. Aceite piloto (ou trial interno) com ownership DistDFe declarado.
3. A1 já custodiado (cliente e/ou office) **fora** do repositório.
4. Backup recente verificável.
5. Endpoint/WSDL do ambiente alvo conferidos (3.9).
6. Executar passos na ordem do runbook; registrar abaixo sem XML/PFX.

## Registro de evidência por gate (preencher na execução)

### Template de linha de evidência

| Campo | Conteúdo permitido |
|-------|-------------------|
| Data/hora (UTC) | ISO-8601 |
| Ambiente | HOMOLOGATION / PRODUCTION |
| Operador | nome/id |
| Office (id/slug) | sem dados fiscais |
| CNPJ (máscara) | `**.***.***/****-**` |
| Canal / stream | `CTE_DISTDFE` / `CTE_AUTXML_DISTDFE` / import / push |
| Correlação | id opaco |
| cStat / HTTP | códigos |
| Contagens | páginas, docs, quarentena |
| Qualidade / assinatura | enums |
| Resultado | PASS / FAIL / BLOQUEADO |
| Notas | sem XML, Base64, tokens |

### 15.2

| Campo | Valor |
|-------|--------|
| Data/hora (UTC) | |
| Ambiente | |
| Operador | |
| Office | |
| CNPJ cliente (máscara) | |
| Papel esperado | SENDER / RECIPIENT / EXPEDITOR / RECEIVER / TAKER |
| Correlação | |
| cStat | |
| Resultado | **PENDING gate** |
| Notas | |

### 15.3

| Campo | Valor |
|-------|--------|
| Data/hora (UTC) | |
| ultNSU / maxNSU (opcional mascarar) | |
| Decode ok? | |
| Papéis persistidos | |
| Assinatura | |
| Cursor avançou só pós-persistência? | |
| Resultado | **PENDING gate** |
| Notas | |

### 15.4

| Campo | Valor |
|-------|--------|
| Data/hora (UTC) | |
| Quiet honrado? | |
| 2ª chamada bloqueada? | |
| Resultado | **PENDING gate** |
| Notas | |

### 15.5

| Campo | Valor |
|-------|--------|
| Data/hora (UTC) | |
| Emitente (máscara) | |
| Escritório em autXML confirmado pelo ERP? | |
| Doc já autorizado alterado? | **não permitido** |
| Resultado | **PENDING gate** |
| Notas | |

### 15.6

| Campo | Valor |
|-------|--------|
| Data/hora (UTC) | |
| Qualidade | AUTXML_ORIGINAL / AUTXML_REDACTED |
| Refs 999… preservadas? | |
| Assinatura real | VALID / NOT_VERIFIABLE_OFFICIAL_REDACTION / INVALID |
| Roteamento ISSUER/OUT ok? | |
| Resultado | **PENDING gate** |
| Notas | |

### 15.7

| Campo | Valor |
|-------|--------|
| Data/hora (UTC) | |
| Canônico preferiu original? | |
| Aquisição autXML preservada? | |
| Resultado | **PENDING gate** |
| Notas | |

### 15.8

| Campo | Valor |
|-------|--------|
| Data/hora (UTC) | |
| Canal fallback | MANUAL_XML / MANUAL_ZIP / EMITTER_PUSH |
| PENDING_IMPORT encerrado? | |
| Resultado | **PENDING gate** |
| Notas | |

### 15.9

| Campo | Valor |
|-------|--------|
| Data/hora (UTC) | |
| Segredos em log? | NÃO / SIM |
| Flags ao final | |
| Resultado global smoke | **PENDING gate** |
| Notas | |

### 3.9 (endpoint / WSDL)

| Campo | Valor |
|-------|--------|
| Data/hora (UTC) | |
| URL usada | (hostname ok; path do config) |
| TLS peer/hostname ok? | |
| SOAPAction / namespace batem com deploy? | |
| Layout | 1.00 |
| Lib comunitária runtime? | **não** (esperado) |
| Resultado | **PENDING gate** |
| Notas | Defaults já documentados no smoke runbook e na matriz schema |

## Dependências entre gates

```
15.1 (doc) ──► 3.9 readiness
                 │
                 ▼
               15.2 ──► 15.3 ──► 15.4
                 │
                 └── (paralelo quando houver emitente) 15.5 ──► 15.6 ──► 15.7
                                                          │
                                                          └── 15.8 (fallback sem autXML)
                 │
                 ▼
               15.9 (evidência + decisão de flags)
                 │
                 ▼
               16.7 allowlist gradual ──► 16.9 aceite ──► 16.10 archive
```

## Checklist pré-archive (16.10 — OPEN)

Não marcar 16.10 nem arquivar a change até:

- [ ] Cenários das delta specs da change verificados (não só documentação)
- [ ] Tasks de código/teste ainda abertas (6.7, 6.10, 7.11, 10.x, 11.x, 12.x, 13.x, 14.x, etc.) resolvidas ou explicitamente re-escopadas
- [ ] Gates 15.2–15.9: PASS nos streams que o produto declara liberados, ou NO-GO documentado com flags off
- [ ] 3.9 endpoint/WSDL confirmados no ambiente alvo
- [ ] 16.9 aceite assinado (ou trial interno formal)
- [ ] `openspec validate complete-cte-capture-with-distdfe-autxml-and-import` ok
- [ ] Sync de specs principais / archive só após os itens acima

**Estado:** 16.10 **OPEN** — docs de ops preparadas; implementação e smoke live incompletos.

## Histórico

| Data | Evento |
|------|--------|
| 2026-07-15 | Gates 15.2–15.9 e 3.9 criados como **PENDING gate**; smoke live não executado |
| 2026-07-15 | Task 15.1 documentada (runbook) |
| 2026-07-15 | Docs 16.1–16.8; template 16.9; checklist archive 16.10 (ainda OPEN) |

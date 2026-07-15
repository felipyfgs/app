## Context

| Canal | Entrada (IN) | Saída (OUT) |
|-------|--------------|-------------|
| **ADN NFS-e** | Tomador / intermediário | Prestador (**ISSUER**) — **já no ADN** |
| **DistDFe NF-e** | Destinatário, transportador, autXML | **Emitente NÃO recebe a própria** no DistDFe |
| **CT-e DistDFe** | Tomador, remetente, dest., etc. | Emitente transportador (regras restritas) / import |
| **NFC-e** | Raro via DistDFe | Quase sempre **emissão própria** → import |
| **MDF-e** | Contratante / autXML | Emitente → DistDFe ou import |

Dados atuais (piloto): `nfse_notes` com ISSUER 69 / TAKER 42; `nfe_documents` só DistDFe (entrada).

## Goals

1. Todo documento no catálogo com **kind + direction** confiável.
2. **Entradas** completas: NFS-e + NF-e (full) + CT-e (+ MDF-e se útil).
3. **Saídas** completas: NFS-e via ADN; NF-e/NFC-e via **import XML** (+ consulta por chave se lista existir).
4. Filtros e export entrada × saída.
5. Um pipeline de vault/projeção único.

## Decisions

### D1 — Direction

```
OUT  se estabelecimento do office é emitente/prestador do doc
IN   se é tomador/destinatário/recebedor (ou papel de interesse de entrada)
UNKNOWN se não der para classificar
```

- NFS-e: `ISSUER`→OUT, `TAKER`→IN, `INTERMEDIARY`→IN (ou flag própria; default IN para triagem de custo).
- NF-e DistDFe: default **IN** (interessado não-emitente).
- Import marcado pelo usuário ou pelo CNPJ emitente == estabelecimento.

### D2 — Saídas NF-e/NFC-e = ingestão

Como DistDFe não resolve saída do emitente:

1. **Import em lote** (ZIP/XML) no painel OPERATOR — primário.
2. **API** `POST /documents/import` com multipart.
3. Parser + vault + `direction=OUT` + kind NFE/NFCE.
4. Idempotência por SHA-256 / chave.
5. (Fase 2) webhook/pasta SFTP do ERP — fora do MVP se import cobrir.

### D3 — Entradas NF-e full

- DistDFe (feito) + unlock ciência (`nfe-manifestacao-destinatario`) só para **entregar XML**, não para “manifestar por política”.

### D4 — CT-e / MDF-e

- Clients DistDFe próprios (como NF-e); direction por papel parseado.
- NFC-e: **só import** no MVP (sem DistDFe de entrada B2B).

### D5 — Fases de implementação

| Fase | Entrega |
|------|----------|
| **A** | Campo `direction` + backfill NFS-e/NFE + filtros UI/API |
| **B** | Unlock full NF-e entrada (ciência técnica) + download prefer full |
| **C** | Import saídas NF-e/NFC-e |
| **D** | CT-e DistDFe |
| **E** | MDF-e + export pastas entrada/saida |
| **F** | Consulta por chave em lote (lista de chaves de saída) se necessário |

### D6 — O que “tudo” significa neste produto

**Tudo =** todos os XML que o **CNPJ do cliente** deve guardar para o escritório, classificados entrada/saída, dos kinds comuns (NFS-e, NF-e, NFC-e, CT-e, MDF-e), obtidos por:

- distribuição oficial (ADN/DistDFe) **ou**
- import do que a distribuição não entrega (saídas emitidas).

Não = emitir nota nem ser o único sistema fiscal do cliente.

## Risks

| Risco | Mitigação |
|-------|-----------|
| Cliente não exporta XML de saída do ERP | Processo de onboarding + import; sem milagre DistDFe |
| Duplicar entrada DistDFe + import | Unique sha256 / access_key |
| Confundir direction | Testes por papel; exibir no detalhe |
| 656 | Um capturador DistDFe por A1 |

## Open Questions

- Intermediário NFS-e: IN ou tag própria? **Default IN.**
- Import NFC-e no mesmo fluxo que NF-e? **Sim.**

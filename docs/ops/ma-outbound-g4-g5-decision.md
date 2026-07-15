# Decisões G4 (M2M) e G5 (mutação) — 2026-07-15

**Change:** `build-ma-outbound-nfe-nfce-capture` · tasks 9.7 / 9.8 / 4.7 / 8.10

## G4 — M2M

| Campo | Valor |
|-------|--------|
| Status | **`NO_GO_M2M`** |
| Evidência | Sem contrato/autorização escrita da SEFAZ-MA no repositório |
| Flag | `SEFAZ_MA_M2M_RETRIEVAL_ENABLED=false` |
| Adapter | `DisabledMaOutboundXmlRetrievalClient` |
| UX validada | Modo **`ASSISTED`** (upload de pacote oficial) no painel |
| RPA | **No-go** — proibido scraping/Gov.br/SEFAZNET/CAPTCHA |

Reavaliar somente com ofício/e-mail formal; atualizar `docs/ops/ma-outbound-sefaz-ma-status.md`.

## G5 — Mutação (539 / inutilização)

| Campo | Valor |
|-------|--------|
| Status | **Produção desabilitada** |
| Flag | `SEFAZ_MA_MUTATING_PROBE_ENABLED=false` |
| Clientes | `DisabledSefazOutboundInutilizationClient`, `DisabledSefazOutboundMutatingProbeClient` |
| CI | Parsers 102/241/539 + `MutatingProbeGateEvaluator` + saga com fakes |
| Spike homolog | **Não executado** nesta sessão (exige série exclusiva + A1 homolog + parecer) |

Caminho **read-only** (pacote + consulta 562) permanece implementável e independente de G5.

## 9.4 — Concatenação 562

Registro provisório (CI/fixtures): o parser aceita `chNFe` no XML ou no `xMotivo`.  
**Comportamento real SVAN/SVRS** só pode ser afirmado após G2 (tasks 9.2–9.3).  
Enquanto não houver evidência de produção/homolog, **562 sem chave** → `LIMITED_NO_KEY` sem força bruta de `cNF`.

# Congelamento SVRS portal — pré-implementação resiliente

**Change:** `add-resilient-svrs-nfe55-outbound-xml-retrieval` · task 1.1  
**Data:** 2026-07-15

## Confirmação de flags (ambientes inspecionados)

| Canal | Flag | Valor esperado (pré-código) | Valor observado (local docker) |
|-------|------|----------------------------|--------------------------------|
| NFC-e SVRS master | `SEFAZ_SVRS_NFCE_XML_RETRIEVAL_ENABLED` | `false` | `false` |
| NFC-e SVRS auto-queue | `SEFAZ_SVRS_NFCE_XML_AUTO_QUEUE_ENABLED` | `false` | `false` |
| NFC-e kill switch | `SEFAZ_SVRS_NFCE_XML_KILL_SWITCH` | opcional on | `true` (bloqueia rede) |
| NF-e 55 SVRS | (ainda inexistente) | desligado | n/a |
| MA outbound M2M | `SEFAZ_MA_M2M_RETRIEVAL_ENABLED` | `false` | `false` |

**Regra:** nenhuma chamada real ao host `dfe-portal.svrs.rs.gov.br` até governador, fixtures, detector de bloqueio HTTP 200 e smoke formal (seção 13).

## Defaults de taxa a remover (task 1.5)

Os valores **ainda não pilotados** da change NFC-e:

- intervalo global **5 s**
- intervalo por raiz **30 s**
- **20** chaves por job

são **substituídos** pelos budgets defensivos do governador compartilhado (120 s global, 15 min/raiz, 1 chave/job, 10 exchanges/h, 50/dia). Não são limites oficiais da SVRS.

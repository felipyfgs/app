# Pré-condição: governança resiliente antes do piloto NFC-e real

**Change:** `add-resilient-svrs-nfe55-outbound-xml-retrieval` · task 1.6  
**Relacionada:** `add-svrs-nfce-outbound-xml-retrieval` (arquivada, código presente)

## Por quê

A implementação NFC-e já existe, mas ainda usa defaults **5 s / 30 s / 20 chaves** e breaker só em cache, **sem** ter sido pilotada em produção. O smoke NF-e 55 no mesmo host mostrou bloqueio por múltiplas consultas.

Habilitar o piloto NFC-e **sem** governador compartilhado, budgets defensivos e detector HTTP 200 colocaria NF-e e NFC-e em risco de bloqueio de IP da coorte.

## Regra de rollout

| Etapa | Condição |
|-------|----------|
| Código NFC-e em repositório | OK (flags off) |
| Piloto real NFC-e (master/auto-queue) | **Bloqueado** até esta change entregar governador + budgets + bloqueio 200 + kill switch drill |
| Piloto NF-e 55 | Smoke único pós-cooldown (seção 13) |
| Auto-queue qualquer modelo | Somente após gate formal (seção 13.10 / 14) |

Esta change é **pré-condição** do piloto real da recuperação NFC-e existente.

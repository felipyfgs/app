# Inventário de consumidores externos de `distNSU` (CNPJ-base do escritório)

**Change:** `add-office-autxml-and-bulk-xml-import` · task 1.3  
**Data:** 2026-07-15  
**Status:** **GATE ABERTO — ativação externa bloqueada**

## Contexto

O Ambiente Nacional controla a sequência `distNSU` por CNPJ-base. Um segundo consumidor (ERP, robô, fornecedor de captura) que use o mesmo CNPJ-base do escritório pode gerar `cStat=656` (consumo indevido) e corromper a continuidade do cursor `NFE_AUTXML_DISTDFE`.

## Inventário (estado atual)

| Consumidor | CNPJ-base | Ambiente | Evidência | Ownership |
|------------|-----------|----------|-----------|-----------|
| *Nenhum inventariado no repositório* | — | — | Sem integração documentada, env, ou runbook apontando `distNSU` com A1 do escritório | **Desconhecido** |

### O que **não** conta como consumidor concorrente

| Canal | Motivo |
|-------|--------|
| `NFE_DISTDFE` (cliente) | Usa A1 e CNPJ do **cliente**, cursor por estabelecimento |
| `NFSE_ADN` | Serviço distinto (ADN NFS-e) |
| `MA_OUTBOUND` | Consulta de protocolo / nNF — não `distNSU` |
| Import XML/ZIP | Sem chamada SEFAZ |

## Política enquanto o ownership não estiver resolvido

1. Feature flag `SEFAZ_AUTXML_DISTDFE_ENABLED=false` (default).
2. Kill switch `SEFAZ_AUTXML_KILL_SWITCH` pode forçar desligamento sem apagar cursor.
3. Allowlist de `office_id` vazia por padrão.
4. Ativação de piloto **exige** declaração operacional explícita de que:
   - nenhum ERP/robô/fornecedor consome `distNSU` com o CNPJ-base do escritório; **ou**
   - o ownership/cursor foi transferido de forma auditada (sem reset cego para zero).
5. Gate de conflito `EXTERNAL_CONSUMER_CONFLICT` permanece no design do onboarding (task 4.9).

## Como completar este inventário (operacional)

Antes do smoke de produção (seção 13):

1. Listar softwares fiscais do escritório que usem certificado A1 do contador.
2. Verificar se algum job agenda `NFeDistribuicaoDFe` / `distNSU`.
3. Registrar fornecedor, contato, ambiente e última NSU conhecida (se houver).
4. Atualizar esta tabela e liberar allowlist somente após resolução.

## Conclusão

**Nenhuma evidência de consumidor externo no código ou docs do monorepo.**  
A ativação de chamadas externas permanece **bloqueada** até declaração operacional do piloto.

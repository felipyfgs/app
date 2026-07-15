# ADR 004 — Limites do `NFeDistribuicaoDFe` vs ausência de limite publicado do `NFESSL`

## Status

Aceito (2026-07-15)

## Contexto

O portal SVRS expõe formulários autenticados por mTLS:

- NFC-e: `NFCESSL/DownloadXMLDFe`
- NF-e: `NFESSL/DownloadXMLDFe`

O web service nacional `NFeDistribuicaoDFe` publica regras de uso indevido (ex.: ordem de magnitude de **20 consultas/hora** e bloqueio associado / `cStat 656`). Essas regras **não** são documentação do formulário `NFESSL`/`NFCESSL`.

Smoke local (2026-07-15) observou resposta **HTTP 200** com texto de bloqueio por múltiplas consultas no formulário NF-e, sem limiar, escopo ou cooldown oficiais publicados pela SVRS para esse HTML.

## Decisão

1. **Não** tratar o limite de 20/h do DistDFe como limite do `NFESSL`/`NFCESSL`.
2. Aplicar **orçamento preventivo interno** (governador de egress) deliberadamente defensivo e versionado no deploy.
3. Detectar bloqueio textual mesmo com HTTP 200 e abrir circuit breaker de **coorte** (NF-e + NFC-e no mesmo host/IP).
4. Manter DistDFe com cursor/NSU e regras próprias, **fora** do contador de exchanges do portal.
5. Qualquer aumento de budget exige decisão operacional e revisão de spec — sem auto-ramp no software.

## Consequências

- Throughput de recuperação por portal será baixo de propósito; backlog deve preferir `autXML`, import XML/ZIP e pacote MA.
- Documentação de produto e UI devem rotular limites como **preventivos**, não oficiais.
- Pedido formal à SVRS permanece aberto (`docs/ops/svrs-nfessl-formal-inquiry.md`).

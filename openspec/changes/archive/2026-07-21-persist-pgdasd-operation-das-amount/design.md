## Context

CONSDECLARACAO13 não expõe valor. CONSEXTRATO16 devolve PDF; a seção 6 documenta a última guia emitida (`Número`, `Total`). O pipeline RBT12 já baixa esse PDF pós-MONITOR e persiste projeção separada. O enrich `tax_guides` → vault GERAR_DAS existe no código, mas no ambiente há 0 guias e 0 GERAR_DAS — só EXTRATOs.

Carteira MUST permanecer read-only local (sem Integra no GET). Web já consome `payment_open_competencies[].amount_cents`.

## Goals / Non-Goals

**Goals:**

- Persistir `amount_cents` por operação DAS no ingest Integra (extrato §6 e GERAR_DAS JSON).
- Portfolio só lê colunas/local; popover ganha valores sem mudança de contrato web.
- Gap de histórico: job pós-MONITOR enfileira CONSEXTRATO para DAS unpaid sem montante (não no load).

**Non-Goals:**

- GERARDAS12 live / `dataConsolidacao` assistida nesta change.
- Parse no path de portfolio ou na Nuxt.
- Nova tabela tipo RBT12 (granularidade errada — valor é por `das_number`).

## Decisions

1. **Colunas em `pgdasd_operations`**  
   `amount_cents` (nullable int), `amount_source` (`TAX_GUIDE` | `GERAR_DAS` | `EXTRATO_PARSE`), `amount_parser_version` (nullable string), `amount_resolved_at` (nullable timestamptz), `amount_source_artifact_id` (nullable FK opcional para `pgdasd_artifacts`).  
   Alternativa rejeitada: só `metadata` JSON — piora query batch do portfolio.  
   Alternativa rejeitada: tabela projection PA-scoped — RBT12 é por PA; pagamento é por DAS.

2. **Parse no ingest, não no portfolio**  
   Em `PgdasdPostConsultService` no caminho `pgdasd.consextrato` (junto do RBT12): `PdfTextExtractor` + `PgdasdExtratoDasAmountParser` → update da operação com `das_number` matching.  
   GERAR_DAS pós-consulta: mapear `DasGuideDto` / `dados.total` → mesma linha (`amount_source=GERAR_DAS`, sem parser PDF).

3. **Ordem em `openPaymentCompetencies`**  
   (1) `tax_guides.amount_cents`  
   (2) `pgdasd_operations.amount_cents`  
   (3) fallback vault GERAR_DAS transitório (já existente) até backfill  
   Sem `pdftotext` no GET.

4. **Gap job**  
   Espelhar reserva/enqueue do RBT12: após MONITOR produtivo, para DAS unpaid do cliente sem `amount_cents` e com `das_number`, enfileirar CONSEXTRATO (rate-limit/idempotência existentes). Não cobrir 100% na primeira sessão se rate-limit; fail-closed permanece `—`.

5. **Web**  
   Zero mudança obrigatória. Ownership API.

6. **Uma capability**  
   Tudo sob `pgdasd-operation-das-amount` (persist + ordem de leitura no portfolio). Coordena com o enrich já aplicado sem reabrir o delta da change irmã.

## Mapa de dependências

| Upstream | Capability | Marco | Relação |
|----------|------------|-------|---------|
| `enrich-pgdasd-payment-open-amounts` | `pgdasd-payment-open-amount-enrichment` | `apply` | `bloqueante` |

- Nível: **C1**
- Rollout: migration + código API. Rollback: reverter enrich order e deixar colunas nullable.

## Risks / Trade-offs

- [Extrato só no PA esperado hoje] → Job de gap para unpaid históricos; até lá `—` honesto.
- [Principal ≠ Total] → Parser usa só `Total`.
- [Número no PDF diverge do `das_number`] → Fail-closed, não grava.
- [Custo CONSEXTRATO no gap job] → Mesmo bilhete do pipeline RBT12; batch/idempotência; nunca no portfolio.
- [Tenancy] → Updates/queries sempre `office_id` do tenant.

## Migration Plan

1. Migration aditiva em `pgdasd_operations`.
2. Deploy API; backfill oportunista via próximo extrato/GERAR_DAS + gap job.
3. Rollback: ignorar colunas; UI volta a depender só de guia/vault.

## Open Questions

- Nenhuma bloqueante.

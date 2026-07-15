# Runbook: divergência ledger × fatura/relatório SERPRO

## Escopo

A software house possui **um** contrato/fatura SERPRO global. O ledger interno (`serpro_api_usage_entries`, agregados mensais, reconciliações) atribui consumo por `office_id` para franquia e shadow mode. Divergência material **bloqueia escala** do piloto (gates da change).

## Sinais

- `SerproUsageReconciliation` com diferença material
- Consolidação platform: `internal_estimated_total_micros` ≠ valor oficial importado
- Auditoria `serpro.usage.reconciliation_registered`
- Alertas de consumo de tenant sem contrapartida no relatório oficial (ou o inverso)

## Princípios

1. **Não sobrescrever** eventos originais do ledger — só ajustes de reconciliação com motivo.
2. Tenants **não** veem fatura global, custo de outros offices nem orçamento global.
3. Shadow mode: divergência não gera cobrança automática ao tenant até política comercial habilitada.

## Procedimento

1. Fixar `period_year` / `period_month` sob investigação.
2. Recomputar agregados internos:

```bash
# Via API PLATFORM_ADMIN de consolidação (recompute=true) ou service UsageAggregationService
```

3. Importar referência oficial (relatório/fatura SERPRO) com `official_reference` e `difference_micros`.
4. Classificar gaps:
   - Chamadas faturáveis vs não faturáveis / erro / cache
   - Timezone / corte de competência
   - Tentativas duplicadas idempotentes
   - Ambiente TRIAL vs PRODUCTION misturado
5. Registrar ajustes (`SerproUsageReconciliationAdjustment`) com `reason` e `notes` — **sem CNPJ** em campos de métrica.
6. Se diferença material sem explicação: **bloquear escala** de novos módulos/coortes e documentar decisão.

## Comunicação

- Interno: postmortem com correlation ids e quantidades agregadas.
- Tenant: apenas impacto na própria franquia/saldo, se houver; nunca extrato global.

## Relacionados

- `serpro-integra-contador-commercial-legal-evidence.md` (gate comercial)
- `serpro-unavailability-runbook.md` (outages que geram ruído de cobrança)

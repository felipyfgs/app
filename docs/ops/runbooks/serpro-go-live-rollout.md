# Runbook — promoção FREE_SMOKE_OK e bloqueio de canário faturável

## 12.10 — Teto desta change

O go-live automático/operacional **encerra em `FREE_SMOKE_OK`**.

| Gate | Como chega | Bloqueios |
|------|------------|-----------|
| `FREE_SMOKE_OK` | Escada gratuita + `serpro:go-live free-smoke-promote` | Demo office; ladder incompleta |
| `CANARY_READY` | **Somente** aprovação dual `BILLABLE_CANARY` + teto unitário + qty=1 | Sem aprovação; custo ≤0; qty≠1; Emitir/Declarar |
| `PRODUCTION_READY` | Fora deste fluxo; gates documentais externos | `serpro:external-gates` abertos |

## Promover FREE_SMOKE_OK

Ver `serpro-free-smoke-ladder.md`. Registrar bloqueios remanescentes (external gates, budgets de expansão, flags off).

## Canário faturável (opcional, sessão futura)

1. Abrir aprovação `BILLABLE_CANARY` com contexto: `office_id`, `operation_key` (read-only), `max_unit_cost_micros`, `max_quantity=1`, janela curta, idempotency key.
2. Dois `PLATFORM_ADMIN` TOTP + `Office ADMIN` 2FA (política de produto).
3. Executar **uma** chamada delimitada.
4. Reconciliar detalhamento oficial **antes** de expandir.
5. CLI de verificação de bloqueio (sem aprovação deve falhar de forma controlada):

   ```bash
   php artisan serpro:go-live canary-blocked-check --json
   # Com aprovação real (ops):
   php artisan serpro:go-live canary-blocked-check \
     --approval-id=<ID> \
     --office=<ID> \
     --operation-key=<OP_READONLY> \
     --max-unit-cost-micros=<MICROS> \
     --max-quantity=1
   ```

## O que NÃO faz parte do CI/deploy/health

- Canário faturável
- `serpro:smoke tls|oauth` com live
- Qualquer `/Consultar` de “sanity check”

## Referências de código

- `SerproSmokeService` / `serpro:smoke`
- `SerproReadinessPromotionService`
- `SerproRolloutApprovalService::ACTION_BILLABLE_CANARY`
- `SerproKillSwitchService` (durable)

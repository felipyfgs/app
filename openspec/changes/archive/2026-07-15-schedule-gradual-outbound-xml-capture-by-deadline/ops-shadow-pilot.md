# Operação: modo sombra, piloto e escala

Documento operacional das tasks 14.1–14.9. Defaults seguros em `config/outbound_deadline.php`.

## Flags

| Env | Default | Função |
|-----|---------|--------|
| `OUTBOUND_DEADLINE_SCHEDULING_ENABLED` | `false` | Master da change |
| `OUTBOUND_DEADLINE_PLANNER_ENABLED` | `false` | Recalcula prazos/capacidade |
| `OUTBOUND_DEADLINE_DISPATCH_ENABLED` | `false` | Enfileira jobs SVRS |
| `OUTBOUND_DEADLINE_SHADOW_MODE` | `true` | Planeja sem dispatch remoto |
| `OUTBOUND_DEADLINE_RETRY_POLICY` | `false` | Máx. 2 tentativas ≥24h |
| `OUTBOUND_DEADLINE_AUTO_FRACTION` | `0.60` | Fração da capacidade nominal |

**Rollback:** desligar planner/dispatcher (`*_ENABLED=false` ou `SHADOW_MODE=true`), preservar estado/XML/aquisições, manter contingência (upload/pacote). Não restaurar retries rápidos automaticamente.

## 14.1–14.3 Modo sombra

1. Backup/restore se houver dados fiscais reais.
2. Habilitar somente planner em sombra:

```bash
OUTBOUND_DEADLINE_SCHEDULING_ENABLED=true
OUTBOUND_DEADLINE_PLANNER_ENABLED=true
OUTBOUND_DEADLINE_SHADOW_MODE=true
OUTBOUND_DEADLINE_DISPATCH_ENABLED=false
```

3. Backfill: `php artisan outbound:deadlines-backfill` (se existir) ou `outbound:deadline-plan`.
4. Um ciclo horário: comparar `outbound_capacity_snapshots` (demanda vs 60% safe).
5. Validar: falsos riscos, justiça entre raízes (`slot_key` / fair queue), cancelamentos por autXML/upload.

## 14.4 Contingência antes do auto-queue

- Testar import XML/ZIP, pacote oficial e exportação parcial (`POST /api/v1/outbound/deadline/confirm-partial` + `export`) **antes** de ligar dispatch.
- UI: `/closing`.

## 14.5–14.8 Dispatch e piloto

1. Gates de transporte SVRS já aprovados (mTLS, allowlist, governor).
2. `DISPATCH_ENABLED=true` + `SHADOW_MODE=false` **somente** para allowlist de raízes/perfis.
3. Pilotar **uma** coorte/competência; **não** aumentar `max_exchanges_per_day` / inflight.
4. Medir: capturas por fonte, exchanges/XML, prazo, bloqueios, volume assistido (`GET /api/v1/outbound/deadline/metrics`).
5. Ampliar escritórios/raízes só após ciclo sem bloqueio 656 / breaker aberto.

## 14.9 Fração 60%

Reavaliar `OUTBOUND_DEADLINE_AUTO_FRACTION` apenas por decisão/versionamento de config — **nunca** auto-ramp por urgência ou backlog.

## Comandos úteis

```bash
php artisan outbound:deadline-plan              # shadow
php artisan outbound:deadline-plan --dispatch   # só se flags on e shadow off
php artisan exports:purge-expired
```

# Runbook — kill switch, breaker, Redis e ledger (pós-smoke)

## Kill switch global

**Fonte de verdade:** `serpro_runtime_controls` (+ `SERPRO_KILL_SWITCH` env).  
**Redis/Cache:** espelho. **Flush Redis NÃO reabre** o kill se o DB estiver `active=true`.

```bash
# Ativar (imediato)
php artisan serpro:contract kill-on --reason='incident-or-drill'

# Status
php artisan serpro:go-live kill-switch-status

# Após flush Redis / restart cache
php artisan serpro:go-live kill-switch-hydrate

# Desligar: preferir fluxo de quatro olhos (SerproRolloutApprovalService ACTION_KILL_SWITCH_OFF)
# API: POST /api/v1/platform/serpro/kill-switch com dual approval conforme implementação
```

### Drill recomendado (12.9)

1. Com free smoke em andamento ou logo após: `kill-on`.
2. Confirmar egress bloqueado (`serpro:prod-check` / health).
3. `Cache::flush` ou restart Redis → `kill-switch-hydrate` → kill permanece ON.
4. Desligar só com dois `PLATFORM_ADMIN` + TOTP.
5. Confirmar alertas/ops-scan (`serpro:ops-scan`).

## Circuit breaker

```bash
php artisan serpro:go-live breaker-status
```

- Segmentado por dependência/solução.
- 403 de negócio **não** contam como falha técnica.
- Half-open com probes limitados (`serpro.circuit_breaker.*`).

## Ledger / reconciliação dry-run

```bash
php artisan serpro:go-live ledger-dry-run --year=2026 --month=7 --json
```

- **Não** importa fatura oficial.
- **Não** grava `serpro_usage_reconciliations`.
- Use para comparar totais internos estimados com extrato offline.
- Confirmar que no período de free smoke **não** há entradas `/Consultar`|`/Emitir`|`/Declarar`.

## Critério de saída 12.9

- [ ] Kill switch testado (on → block → hydrate pós-Redis → off dual)
- [ ] Breaker status legível / config conhecida
- [ ] Ledger dry-run sem writes
- [ ] Zero chamadas faturáveis de negócio no smoke

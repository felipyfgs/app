# Evidence template — 12.9 / 12.10 ops safety + promotion ceiling

## 12.9

| Drill | Resultado |
|-------|-----------|
| kill-on bloqueia egress | |
| Redis flush + `kill-switch-hydrate` mantém ON | |
| Dual approval kill-off | |
| breaker-status legível | |
| ledger-dry-run sem writes | |
| Zero Consultar/Emitir/Declarar no período | |

Comandos:

```text
serpro:go-live kill-switch-status
serpro:go-live kill-switch-hydrate
serpro:go-live breaker-status
serpro:go-live ledger-dry-run --year=YYYY --month=M
```

## 12.10

| Item | Valor |
|------|-------|
| highest_gate promovido | FREE_SMOKE_OK |
| CANARY_READY | bloqueado sem dual + teto |
| `canary-blocked-check` (sem approval) | blocked=true |
| External gates abertos | listar kinds |
| Flags real ainda OFF | sim |

## Runtime

Implementação completa; **runtime de produção ops-gated**.

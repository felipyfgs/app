# Runbook — deploy limpo de produção (flags OFF)

**Objetivo:** implantar código com drivers/flags desligados, demo segregado, budgets preparados e kill switch testável — **sem** smoke faturável.

## Checklist (automatizado)

```bash
php artisan serpro:smoke checklist --json
php artisan serpro:go-live checklist --serpro-env=PRODUCTION
php artisan serpro:prod-check --serpro-env=PRODUCTION --json
php artisan serpro:readiness --serpro-env=PRODUCTION --json --no-persist
```

## Itens manuais (ops)

| # | Item | Esperado | Evidência |
|---|------|----------|-----------|
| 1 | `SERPRO_KILL_SWITCH` / runtime kill | Contenção inicial ON **ou** OFF documentado | `serpro:go-live kill-switch-status` |
| 2 | Drivers `serpro.capabilities.*` | Nenhum `real` no deploy limpo | checklist `drivers_not_real` |
| 3 | Feature flags hub fiscal | Default OFF + kill switch global vence | `config/features.php` / env |
| 4 | Mutações fiscais | OFF | `FISCAL_MUTATIONS_KILL_SWITCH` / flags |
| 5 | `SERPRO_SMOKE_ENABLED` | `false` até janela de smoke | checklist |
| 6 | Demo | Offices `DEMO` fora de allowlist real | inventário demo |
| 7 | Budgets | Global/office/canary **positivos** configurados (ainda sem live faturável) | API/UI de budget |
| 8 | Fake clients | `true` em trial/contenção; `false` só com drivers/orçamento prontos | config |
| 9 | Kill switch test | Ativar → bloquear egress → desativar com quatro olhos | ver kill-switch runbook |
| 10 | Horizon/scheduler | Consumidores das filas SERPRO presentes | `serpro:prod-check` / horizon |

## Comandos de suporte

```bash
# Kill switch ON (contenção)
php artisan serpro:contract kill-on --reason='clean-deploy-containment'

# Status durable (sobrevive Redis flush)
php artisan serpro:go-live kill-switch-status
php artisan serpro:go-live kill-switch-hydrate
```

## Não fazer neste passo

- Habilitar drivers `real` em massa.
- Rodar `serpro:smoke tls|oauth` sem janela aprovada.
- Chamar `/Consultar`, `/Emitir`, `/Declarar`.
- Gravar Office/cliente canário no OpenSpec.

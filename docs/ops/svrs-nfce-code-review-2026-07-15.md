# Revisão + refatoração SVRS NFC-e (2026-07-15)

Orquestração com 3 subagentes (backend, frontend, segurança).

## Críticos corrigidos

| # | Item | Correção |
|---|------|----------|
| 1 | Jobs concorrentes | `ShouldBeUnique` + `Cache::lock` em `runAttempt` |
| 2 | Re-enqueue storm | `enqueue` no-op se `QUEUED`/`RUNNING` |
| 3 | Rate limiter TOCTOU | `Cache::lock` no acquire/release |
| 4 | RUNNING órfão | `catch` em runAttempt; nunca re-lança sem status |
| 5 | AuthForbidden trip global | Threshold 3; só `ResponseContractChanged` trip imediato |
| 6 | Half-open + allowlist | Probe sem exigir allowlist; slot único de probe |
| 7 | Kill → BLOCKED | `KillSwitch`/`ChannelDisabled`/`BreakerOpen` recuperáveis → `RETRY_SCHEDULED` |
| 8 | Lista FE office-wide | API `client_id` + painel sempre filtra por cliente |
| 9 | Erro esconde UI | Banner de erro sem limpar dados |
| 10 | Retry attempt_count | Zera no retry manual |
| 11 | Breaker reset tenancy | Valida `client_id` do office |
| 12 | Client binding | `bind` por resolução (não singleton) |

## Testes

- Backend filter SvrsNfce: 50 passed
- Frontend unit svrs-nfce-surface: 4 passed

## Remanescente (não-crítico / piloto)

- Cookie jar em tmp (P2)
- Quarentena vault para versão XML desconhecida
- E2E VIEWER/OPERATOR roles
- Gates piloto 14.x

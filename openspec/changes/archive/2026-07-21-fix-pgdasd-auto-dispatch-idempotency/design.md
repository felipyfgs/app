## Context

`PgdasdCommunicationService::queueDispatches` monta `idempotency_key` com módulo completo + trigger `scheduled_consult` + `YmdHisu`, estourando `string(64)` em `client_communication_dispatches`. O gancho automático engole a exceção → zero dispatches e zero erro. O timestamp também impede dedupe por período. O sort `rbt12` em `ModulePortfolioQueryService` prioriza PA esperado, enquanto `portfolioDetails` usa `displayPeriodKey` (declaração do PA esperado se existir, senão última declaração) + fallbacks de pointer/PARSED.

Exceção de 2 capabilities: comunicação (idempotência/dedupe) e consistência de carteira (sort RBT12) são bugs da mesma review Bugbot; uma change evita dois cycles propose/apply no mesmo hot path.

## Goals / Non-Goals

**Goals:**

- Chaves de dispatch ≤64 chars sempre.
- Automático: no máximo um dispatch por office+cliente+módulo+submódulo+canal+`period_key`.
- Manual: reenvio permitido com chave curta única.
- Sort `rbt12` ordena pelo mesmo `total_cents` que a linha exibe (quando há PARSED no período de display).

**Non-Goals:**

- Provider externo, worker de egress, migração de schema, redesign UI, alterar preferências/canais, flags SERPRO ON.

## Decisions

1. **Formato compacto de idempotency_key** — prefixos curtos (`sm`/`pd`/`pm`/…), canal (`e`/`w`), trigger (`auto`/`man`) + `period_key` + `client_id`. Manual acrescenta 8 hex de `bin2hex(random_bytes(4))`. Alternativa (hash SHA-256 truncado) rejeitada: dificulta debug e suporte.

2. **Dedupe automático via chave estável + skip** — para `scheduled_consult`, chave sem nonce; antes do `create`, se já existir dispatch com a mesma `idempotency_key` (unique office+key), skip. Alternativa (lookup por period+status) é redundante com a unique key e race-prone sem unique.

3. **Sort RBT12 por período de display** — subquery: (a) `display_period` = declaração do PA esperado se existir, senão última declaração por `transmitted_at`/`declaration_number`/`id`, senão PA esperado; (b) preferir PARSED cujo `projection.period_key` = `display_period`; (c) senão qualquer PARSED (`id DESC` desempate). Pointer de `portfolioDetails` permanece só no enrichment (SQLite/ORDER BY constraints). Alternativa (só PA esperado) rejeitada — é o bug reportado.

## Risks / Trade-offs

- [FAILED automático bloqueia requeue no mesmo período] → Mitigação: operador usa send manual (nonce); aceitável fail-soft.
- [display_period SQL ≠ edge cases do pointer] → Teste cobre mismatch declaração vs PA esperado; pointer fica fora do sort (igual design anterior).
- Vazamento entre offices / segredos / bilhetagem SERPRO / mei Compose: N/A (fila local + read-model).

## Mapa de dependências

- DAG: C0; sem upstream bloqueante.
- Ownership: `PgdasdCommunicationService`, `ModulePortfolioQueryService`, testes Feature listados; não editar artifacts de outras changes.
- Rollout: deploy API; rollback = revert.
- Gates: `php artisan test` filtros comunicação/portfolio + pint + `openspec validate` da change.

## Migration Plan

Sem migração. Deploy atômico. Rollback por revert. Dispatches antigos com chave longa já não existem (insert falhava).

## Open Questions

Nenhuma.

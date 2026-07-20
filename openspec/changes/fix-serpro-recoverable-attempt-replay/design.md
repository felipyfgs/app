## Context

O executor central (`SerproOperationService`) persiste tentativas em `serpro_operation_attempts` via `SerproOperationAttemptStore`. A garantia “no máximo um HTTP por chave lógica” é correta para respostas SERPRO, mas falhas **locais de pré-condição** (token ausente, rate limit local, contrato indisponível) também eram `acknowledge`d e, na próxima chamada com o mesmo payload, devolvidas como `replay` — sem nova tentativa mesmo após o operador corrigir o token.

Na avaliação Production local (office contador / cliente Coelho), isso travou `procuracoes.obter` e o ensure pré-consulta até limpeza manual das rows.

## Goals / Non-Goals

**Goals:**

- Replay sticky só para resultados que representam observação efetiva da operação (sucesso ou falha de negócio/transporte já persistida).
- Falhas recuperáveis de pré-condição local permitem novo `dispatch` na mesma chave.
- Cobertura de teste unitário sem rede SERPRO.

**Non-Goals:**

- Mudar composição da chave de idempotência.
- TTL temporal genérico em todos os attempts.
- UI de “limpar attempts” no admin.
- Alterar bilhetagem / ledger além do comportamento já existente no redispatch.

## Decisions

1. **Lista explícita de códigos não sticky (allowlist negativa)**  
   Códigos como `PROCURADOR_TOKEN_MISSING`, `RATE_LIMIT_LOCAL`, `RATE_LIMIT_NOT_CONFIGURED`, `AUTHORIZATION_MISSING`, `AUTHORIZATION_ACTION_REQUIRED`, `AUTHOR_IDENTITY_MISSING`, `CONTRACT_UNAVAILABLE`, `CONTRACT_UNHEALTHY`, `CONTRACTOR_MISMATCH`, `TRIAL_CREDENTIALS_MISSING`, `CAPABILITY_DISABLED`, `KILL_SWITCH`, `CIRCUIT_OPEN`, `SUBSCRIPTION_BLOCKED`, `BUDGET_EXCEEDED`, `EGRESS_BLOCKED` (e afins de gate local) NÃO geram replay sticky.  
   **Alternativa rejeitada:** TTL único para todo erro — misturaria falhas SERPRO reais com gates locais.

2. **Reclaim no `beginOrReplay`**  
   Se attempt terminal + `error_code` não sticky → resetar para `dispatched`, limpar campos de resultado (`success`, `http_status`, `error_*`, `dados`/`body`/`mensagens`, timestamps de ack) e retornar `action=dispatch`. Mantém a mesma row (histórico de chave) sem segundo HTTP enquanto in-flight.  
   **Alternativa rejeitada:** apagar a row — perde auditoria mínima e complica lock concorrente.

3. **Sucesso e `REQUEST_FAILED` / erros com evidência remota permanecem sticky**  
   Inclusive `uncertain`/`reconciled`. Não reclaim.

4. **Escopo de código**  
   Lógica em `SerproOperationAttemptStore` (+ método/classe privada ou enum helper `SerproAttemptReplayPolicy`). Sem mudança no painel Nuxt.

## Risks / Trade-offs

- **[Redispatch após rate limit]** Pode re-bater o limiter imediatamente → Mitigação: o rate limiter continua fail-closed; reclaim só remove o sticky, não bypassa o limiter.
- **[Duplo HTTP se classificação errada]** Mitigação: allowlist conservadora (só gates locais conhecidos); erros SERPRO fora da lista ficam sticky.
- **[Tenant]** Reclaim só na mesma `office_id` da chave (já validado no store).

## Migration Plan

- Deploy API only; sem migration.
- Attempts antigos com códigos não sticky passam a reclaim na próxima chamada.
- Rollback: reverter o store para sempre replay em terminal.

## Open Questions

- Nenhum bloqueante; lista de códigos pode crescer se novos gates locais forem adicionados ao executor.

## Context

`loadProcuradorToken()` devolve `null` para várias causas e o client sempre emite `PROCURADOR_TOKEN_MISSING` com a mesma mensagem. Attempts ACK’d + rate limit local mantêm a carteira bloqueada após o token real estar ativo. A change anterior adicionou reclaim; falta abandonar ACK e diagnosticar a causa.

## Goals / Non-Goals

**Goals:**

- Códigos/mensagens distintos para cada falha de resolução do token.
- Query de auth fail-open por `office_id` explícito (`withoutGlobalScopes`).
- Não ACK de erros não sticky; purge no refresh bem-sucedido.
- Comparação de autor via identidade fiscal normalizada.

**Non-Goals:**

- Renovação silenciosa em PRODUCTION se estratégia for `PENDING_VALIDATION`.
- Alterar TTL do token SERPRO.

## Decisions

1. **`resolveProcuradorToken` retorna ok|código** — em vez de `?string` opaco.  
2. **`abandonLocalPrecondition`** apaga o attempt (ou força reserved sem resultado) para a próxima chamada criar do zero. Preferir delete.  
3. **Purge no refresh** por `office_id`+`environment`+códigos não sticky relacionados a token.  
4. **Compat:** manter `PROCURADOR_TOKEN_MISSING` para “sem vault/auth”, e novos códigos na allowlist não sticky.

## Risks / Trade-offs

- **[Delete de attempt]** Perde auditoria da falha local nessa chave → Mitigação: audit logger já registra ensure/consulta; attempt store não é evidência SERPRO.
- **[withoutGlobalScopes]** Mitigação: filtro explícito `office_id` do request.

## Migration Plan

- Deploy API; reiniciar Horizon para opcache.
- Ops: limpar rate limit Redis do office se necessário.
- Rollback: reverter client/store/service.

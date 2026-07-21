## Why

Após corrigir credenciais/token do procurador, consultas e sync de procurações continuavam falhando com `PROCURADOR_TOKEN_MISSING` sem novo HTTP: o attempt store rejogava (replay sticky) a falha local terminal da mesma chave de idempotência. Isso travou a avaliação Production do Coelho até limpeza manual de `serpro_operation_attempts`.

## What Changes

- Classificar códigos de erro **pré-condição / recuperáveis** (ex.: `PROCURADOR_TOKEN_MISSING`, `RATE_LIMIT_LOCAL`, contrato/auth ausente) como **não sticky**.
- Em `beginOrReplay`, se existir attempt terminal com erro não sticky, **reclaim** e permitir novo `dispatch` (não devolver replay).
- Manter replay sticky para sucessos e falhas de negócio/transporte já observadas na SERPRO (ex.: `REQUEST_FAILED` com HTTP remoto).
- Teste unitário cobrindo reclaim vs replay.

## Capabilities

### New Capabilities

- `serpro-operation-attempt-replay`: política de replay idempotente do executor Integra — sticky vs recuperável.

### Modified Capabilities

- (nenhuma — main specs vazias; contrato nasce nesta change)

## Impact

- `SerproOperationAttemptStore` (+ possivelmente helper de classificação de códigos)
- Testes em `apps/api/tests/Unit/Serpro/`
- Sem migration; sem mudança de API HTTP do painel

### Non-goals

- Não alterar bilhetagem nem chave lógica de idempotência (office/env/op/entity/payload).
- Não abrir flags SERPRO, mutações fiscais, mei no Compose, nem ops backup/restore.
- Não reescrever `EnsureClientProcuracaoForConsult` nem o fluxo Termo/A1 (já cobertos em changes anteriores).

### Dependências entre changes

- Nível: `C0`
- Bases estáveis: nenhuma main spec ativa para este contrato
- Depende de: nenhuma
- Desbloqueia: retries de `procuracoes.obter` / consultas após refresh de token sem limpeza manual de attempts
- Paralelismo: não conflita com UI admin SERPRO; ownership = attempt store

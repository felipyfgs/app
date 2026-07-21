## Why

O monitoramento SITFIS ainda pode permanecer em `ERROR` quando `/Apoiar` responde HTTP 304: o protocolo existe apenas no `ETag`, mas o replay idempotente não restaura esse valor, e respostas 304 realmente sem `ETag` são encerradas como falha embora o cache permaneça válido até `expires`. Isso mantém clientes com procuração válida presos em `SITFIS_NOT_MODIFIED_EMPTY`.

## What Changes

- Preservar o `protocoloRelatorio` de respostas SITFIS 304 em formato reutilizável pelo attempt store, sem expor headers/tokens em logs.
- Restaurar o protocolo no `IntegraResponse` durante replay idempotente para que o fluxo avance ao `/Emitir`.
- Quando a SERPRO retornar 304 sem protocolo parseável, tratar como estado transitório: aguardar a expiração oficial do cache e reexecutar `/Apoiar`, em vez de publicar snapshot `ERROR`.
- Cobrir fresh response, replay e 304 sem ETag com testes.
- Non-goals: ligar flags SERPRO, alterar bilhetagem, mutações fiscais, canais SEFAZ, adicionar MEI ao Compose, ops backup/restore ou parecer jurídico.

## Capabilities

### New Capabilities

- (nenhuma)

### Modified Capabilities

- `sitfis-protocol-persist`: replay de HTTP 304 preserva o protocolo e 304 sem protocolo aguarda a expiração do cache sem transformar ausência transitória de header em erro definitivo.

## Impact

- API: `SerproOperationAttemptStore`, `SitfisFlowService`, estado de protocolo SITFIS e testes unitários.
- Dados: sem migration; reutiliza `serpro_operation_attempts.dados`/`headers` sanitizados e `fiscal_monitoring_runs.progress`.
- Integração: `/Apoiar` continua não bilhetado; `/Emitir` só é chamado quando há protocolo correlacionável.
- UI: sem redesign; a carteira deixa de publicar `ERROR` para o edge transitório de cache 304 sem ETag.

### Dependências entre changes

- Nível: `C1`
- Bases estáveis: main spec `sitfis-protocol-persist`
- Depende de: `corrigir-sitfis-integracao-real`
- Capability/contrato: `sitfis-protocol-persist`
- Marco exigido: `apply`
- Relação: `bloqueante` — esta change completa o comportamento 304 introduzido pela change anterior
- Desbloqueia: refresh SITFIS resiliente a replay/cache 304 em produção
- Paralelismo: não editar `SitfisFlowService`, `SerproOperationAttemptStore` nem a delta `sitfis-protocol-persist` em paralelo com a change upstream

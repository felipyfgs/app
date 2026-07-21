## ADDED Requirements

### Requirement: Retry de reserva RBT12 com falha recuperável

Após um MONITOR PGDAS-D produtivo, o sistema SHALL reabrir reservas RBT12 existentes em status `FAILED` cuja `sanitized_error` esteja na lista de falhas recuperáveis de consulta/extrato (`EXTRACT_QUERY_FAILED`, `EXTRACT_JOB_DISPATCH_FAILED`, `EXTRACT_QUERY_ENQUEUE_FAILED`, `EXTRACT_JOB_FAILED`, `PDF_TEXT_EXTRACTION_FAILED`), resetando-as para `PENDING` e redisparando a consulta de extrato. O sistema MUST NOT reabrir reservas `PARSED`, `PENDING`, `NO_DAS` nem falhas de parsing inequívoco (`NOT_FOUND`, `AMBIGUOUS` e razões de parser).

#### Scenario: FAILED por rate-limit é reaberto no próximo MONITOR

- **WHEN** existe reserva RBT12 `FAILED` com `sanitized_error` `EXTRACT_QUERY_FAILED` para a mesma `source_reference_key` e um MONITOR PGDAS-D produtivo processa o cliente
- **THEN** a reserva volta a `PENDING` e um job/consulta de extrato é enfileirado para essa reserva

#### Scenario: PARSED não é reaberto

- **WHEN** a reserva já está `PARSED` com a mesma `source_reference_key`
- **THEN** o sistema MUST NOT resetar status nem redisparar extrato para essa chave

### Requirement: Fan-out limitado do extrato automático da carteira

No caminho automático pós-MONITOR, o sistema SHALL reservar e disparar extrato RBT12 apenas para o DAS mais recente do período de apuração esperado da carteira (PA esperado). O sistema MUST NOT disparar automaticamente um extrato por cada DAS histórico de todos os períodos apenas por idempotência de reserva.

#### Scenario: Um DAS do PA esperado

- **WHEN** o MONITOR projeta múltiplos DAS em vários períodos
- **THEN** o disparo automático de `CONSULTAR_EXTRATO` para RBT12 limita-se ao DAS mais recente do PA esperado

### Requirement: Re-enqueue de run de extrato FAILED por correlação

Quando a reserva RBT12 `PENDING` reutiliza por correlação uma run `CONSULTAR_EXTRATO` já terminal `FAILED`, o sistema SHALL reencaminhar essa run (ou equivalente) para execução em vez de retornar sem despacho.

#### Scenario: Correlação com run FAILED

- **WHEN** `enqueueAutomaticRbt12Extract` resolve a mesma run correlacionada em status `FAILED`
- **THEN** a run é recolocada em fila executável e um job de execução é despachado

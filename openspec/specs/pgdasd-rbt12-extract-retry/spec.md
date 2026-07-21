## Purpose

Capability `pgdasd-rbt12-extract-retry` — requisitos sincronizados das changes OpenSpec.

## Requirements

### Requirement: Retry de reserva RBT12 com falha recuperável

Após um MONITOR PGDAS-D produtivo, o sistema SHALL reabrir reservas RBT12 existentes em status `FAILED` cuja `sanitized_error` esteja na lista de falhas recuperáveis de consulta/extrato (`EXTRACT_QUERY_FAILED`, `EXTRACT_JOB_DISPATCH_FAILED`, `EXTRACT_QUERY_ENQUEUE_FAILED`, `EXTRACT_JOB_FAILED`, `PDF_TEXT_EXTRACTION_FAILED`), resetando-as para `PENDING` e redisparando a consulta de extrato. O sistema MUST NOT reabrir reservas `PARSED`, `PENDING`, `NO_DAS` nem falhas de parsing inequívoco (`NOT_FOUND`, `AMBIGUOUS` e razões de parser).

#### Scenario: FAILED por rate-limit é reaberto no próximo MONITOR

- **WHEN** existe reserva RBT12 `FAILED` com `sanitized_error` `EXTRACT_QUERY_FAILED` para a mesma `source_reference_key` e um MONITOR PGDAS-D produtivo processa o cliente
- **THEN** a reserva volta a `PENDING` e um job/consulta de extrato é enfileirado para essa reserva

#### Scenario: PARSED não é reaberto

- **WHEN** a reserva já está `PARSED` com a mesma `source_reference_key`
- **THEN** o sistema MUST NOT resetar status nem redisparar extrato para essa chave

### Requirement: Fan-out limitado do extrato automático da carteira

No caminho automático pós-MONITOR, quando o PA esperado tiver DAS, o sistema SHALL reservar e disparar extrato RBT12 apenas para o DAS mais recente desse PA. Quando o PA esperado não tiver DAS mas tiver declaração local do período, o sistema SHALL seguir o requirement de RBT12 via declaração do mesmo PA. O sistema MUST NOT disparar automaticamente um extrato por cada DAS histórico. O status terminal por ausência de DAS MUST NOT ser usado quando houver declaração parseável do PA esperado.

#### Scenario: Um DAS do PA esperado

- **WHEN** o MONITOR projeta múltiplos DAS em vários períodos e o PA esperado tem ao menos um DAS
- **THEN** o disparo automático de `CONSULTAR_EXTRATO` para RBT12 limita-se ao DAS mais recente do PA esperado

#### Scenario: Sem DAS com declaração no PA

- **WHEN** o PA esperado não tem DAS e tem declaração
- **THEN** o sistema MUST NOT limitar-se a `NO_DAS` sem tentar a declaração do mesmo PA

### Requirement: RBT12 do PA sem movimento via declaração

Quando o PA esperado não tiver DAS mas tiver declaração do mesmo período, o sistema SHALL obter RBT12 a partir do documento da declaração desse PA (inclusive período sem movimento), de forma fail-closed. O sistema MUST NOT usar DAS de outro período como fonte primária do RBT12 do PA esperado e MUST NOT estimar valores.

#### Scenario: Declaração do PA sem DAS

- **WHEN** o PA esperado tem declaração local e nenhum DAS
- **THEN** o sistema reserva/resolve RBT12 a partir do documento da declaração desse PA
- **AND** a carteira MAY exibir o total quando o parse for inequívoco

### Requirement: Re-enqueue de run de extrato FAILED por correlação

Quando a reserva RBT12 `PENDING` reutiliza por correlação uma run `CONSULTAR_EXTRATO` já terminal `FAILED`, o sistema SHALL reencaminhar essa run (ou equivalente) para execução em vez de retornar sem despacho.

#### Scenario: Correlação com run FAILED

- **WHEN** `enqueueAutomaticRbt12Extract` resolve a mesma run correlacionada em status `FAILED`
- **THEN** a run é recolocada em fila executável e um job de execução é despachado

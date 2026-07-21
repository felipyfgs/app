## ADDED Requirements

### Requirement: RBT12 do PA sem movimento via declaração

Quando o PA esperado não tiver DAS mas tiver declaração do mesmo período, o sistema SHALL obter RBT12 a partir do documento da declaração desse PA (inclusive período sem movimento), de forma fail-closed. O sistema MUST NOT usar DAS de outro período como fonte primária do RBT12 do PA esperado e MUST NOT estimar valores.

#### Scenario: Declaração do PA sem DAS

- **WHEN** o PA esperado tem declaração local e nenhum DAS
- **THEN** o sistema reserva/resolve RBT12 a partir do documento da declaração desse PA
- **AND** a carteira MAY exibir o total quando o parse for inequívoco

## MODIFIED Requirements

### Requirement: Fan-out limitado do extrato automático da carteira

No caminho automático pós-MONITOR, quando o PA esperado tiver DAS, o sistema SHALL reservar e disparar extrato RBT12 apenas para o DAS mais recente desse PA. Quando o PA esperado não tiver DAS mas tiver declaração local do período, o sistema SHALL seguir o requirement de RBT12 via declaração do mesmo PA. O sistema MUST NOT disparar automaticamente um extrato por cada DAS histórico. O status terminal por ausência de DAS MUST NOT ser usado quando houver declaração parseável do PA esperado.

#### Scenario: Um DAS do PA esperado

- **WHEN** o MONITOR projeta múltiplos DAS em vários períodos e o PA esperado tem ao menos um DAS
- **THEN** o disparo automático de `CONSULTAR_EXTRATO` para RBT12 limita-se ao DAS mais recente do PA esperado

#### Scenario: Sem DAS com declaração no PA

- **WHEN** o PA esperado não tem DAS e tem declaração
- **THEN** o sistema MUST NOT limitar-se a `NO_DAS` sem tentar a declaração do mesmo PA

## ADDED Requirements

### Requirement: Prontidão mensal explícita
Antes de gerar entrega mensal, o sistema SHALL classificar a competência como `COMPLETE_KNOWN`, `PARTIAL_CONFIRMED` ou `NOT_READY` com base nos documentos conhecidos e XMLs canônicos. O estado MUST acompanhar a exportação e sua auditoria.

#### Scenario: Todos os conhecidos capturados
- **WHEN** todas as chaves conhecidas elegíveis da competência possuem XML canônico
- **THEN** a exportação pode ser criada como `COMPLETE_KNOWN` sem alegar completude fiscal absoluta

### Requirement: Exportação parcial confirmada
OPERATOR ou ADMIN SHALL poder confirmar exportação parcial, recebendo manifesto das pendências pertencentes ao escritório. O sistema MUST NOT inventar XML, ocultar ausências nem permitir VIEWER confirmar entrega parcial.

#### Scenario: Exportação com cinco pendências
- **WHEN** um OPERATOR confirma a entrega parcial de uma competência com cinco XMLs ausentes
- **THEN** o ZIP contém somente XMLs válidos e um manifesto auditado das pendências autorizadas

#### Scenario: VIEWER tenta confirmar parcial
- **WHEN** um VIEWER solicita exportação `PARTIAL_CONFIRMED`
- **THEN** a API responde 403 e não cria o pacote


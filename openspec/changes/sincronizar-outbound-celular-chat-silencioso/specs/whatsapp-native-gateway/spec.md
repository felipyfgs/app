## ADDED Requirements

### Requirement: Live IsFromMe entra no ledger como OUTBOUND
O gateway SHALL emitir `MESSAGE_RECEIVED` para mensagens 1:1 live com `IsFromMe=true`, com `direction=OUTBOUND` e `provider_message_id` estável, pelas mesmas regras de normalização já usadas no inbound e no history. MUST NOT descartar live IsFromMe antes do ledger. History sync MUST continuar emitindo OUTBOUND com flag de history sem regressão.

#### Scenario: Mensagem enviada no aparelho pareado
- **WHEN** o client whatsmeow entrega evento de mensagem 1:1 live com `IsFromMe=true` e texto/mídia válidos
- **THEN** o gateway persiste `MESSAGE_RECEIVED` com `direction=OUTBOUND` e o mesmo `provider_message_id` do evento

#### Scenario: History outbound permanece
- **WHEN** um batch de history contém mensagem com `IsFromMe=true`
- **THEN** o gateway continua emitindo o evento com `direction=OUTBOUND` e marcação de history, sem duplicar identidade estável

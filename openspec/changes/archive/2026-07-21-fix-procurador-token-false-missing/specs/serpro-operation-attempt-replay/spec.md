## ADDED Requirements

### Requirement: Falhas locais de token não geram ACK sticky
Quando a operação Integra falhar com código não sticky de pré-condição (incluindo códigos de resolução do token do procurador e `RATE_LIMIT_LOCAL`), o executor SHALL abandonar o attempt em vez de persistir ACK terminal. Após `refreshProcuradorToken` bem-sucedido, o sistema SHALL purgar attempts não sticky de token do mesmo office e ambiente.

#### Scenario: Abandonar em vez de ACK
- **WHEN** a resposta da operação tem `success=false` e `error_code` não sticky
- **THEN** o attempt MUST ser abandonado (removido ou liberado) e MUST NOT permanecer como replay sticky

#### Scenario: Purge após refresh do token
- **WHEN** o token do procurador é renovado com sucesso para um office/ambiente
- **THEN** attempts não sticky relacionados a token desse office/ambiente MUST ser removidos

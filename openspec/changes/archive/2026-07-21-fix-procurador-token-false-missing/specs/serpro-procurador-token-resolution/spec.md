## ADDED Requirements

### Requirement: Resolução do token do procurador distingue causas
O transporte Integra Production, ao exigir token de procurador, SHALL distinguir pelo menos: autorização/vault ausente, token expirado, divergência de autor, falha de leitura do cofre e payload vazio — com códigos e mensagens distintos. MUST NOT rotular como “ausente ou expirado” quando a causa for outra.

#### Scenario: Token expirado de verdade
- **WHEN** a autorização tem vault e `procurador_token_expires_at` no passado
- **THEN** a resposta MUST usar código `PROCURADOR_TOKEN_EXPIRED`

#### Scenario: Autor divergente
- **WHEN** o autor normalizado do pedido diverge do autor da autorização
- **THEN** a resposta MUST usar código `AUTHOR_IDENTITY_MISMATCH`

#### Scenario: Vault ilegível
- **WHEN** a leitura do SecureObjectStore falha
- **THEN** a resposta MUST usar código `PROCURADOR_TOKEN_VAULT_UNREADABLE`

### Requirement: Auth do token não depende só do CurrentOffice
A carga do `OfficeSerproAuthorization` para o token SHALL filtrar por `office_id` do pedido com escopo global desabilitado (ou equivalente), para não falhar fechado quando o contexto privilegiado/office estiver ausente no worker.

#### Scenario: Worker com office_id explícito
- **WHEN** a operação informa `office_id` no request e existe autorização Production
- **THEN** o loader MUST encontrar a linha pelo `office_id` explícito

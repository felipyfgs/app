## ADDED Requirements

### Requirement: Resumo operacional na listagem de clientes
O sistema SHALL permitir que a listagem de clientes do escritório ativo inclua, por registro, informações suficientes para triagem de certificado A1 e de prontidão de captura (ao menos presença/estado da credencial ACTIVE e indicação de captura ou sincronização quando o backend já as calcular), sem expor material do PFX, senha ou PEM.

#### Scenario: Cliente com A1 ativo
- **WHEN** a listagem inclui um cliente com credencial ACTIVE não vencida
- **THEN** o payload ou a UI derivada permite marcar o cliente como possuidor de A1 válido e, se houver data de validade, usá-la em alerta de vencimento

#### Scenario: Cliente sem A1
- **WHEN** o cliente não possui credencial ACTIVE
- **THEN** a listagem distingue ausência de certificado sem revelar se já existiu material no cofre

#### Scenario: Sem segredos
- **WHEN** a resposta da listagem é inspecionada
- **THEN** não há PFX, senha, PEM, vault_object_id de certificado em claro nem chave mestra

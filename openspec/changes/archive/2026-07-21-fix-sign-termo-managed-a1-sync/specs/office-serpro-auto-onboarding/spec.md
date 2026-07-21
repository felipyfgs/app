## MODIFIED Requirements

### Requirement: Assinatura síncrona do Termo no onboarding do escritório
Quando o onboarding do office assina o Termo com A1 gerenciado de forma síncrona, a invocação SHALL resolver todas as dependências de `SignTermoWithManagedA1Job::handle` (incluindo `OfficeCredentialResolver`) via container Laravel — MUST NOT passar argumentos manuais incompletos ou fora de ordem.

#### Scenario: Sync sign resolve OfficeCredentialResolver
- **WHEN** o onboarding do office executa a assinatura síncrona do Termo com Managed A1
- **THEN** o job recebe `OfficeCredentialResolver` na posição tipada
- **AND** NÃO ocorre TypeError por receber `AuditLogger` no lugar do resolver

### Requirement: Persistência de skip_reason de runs fiscais
Ao finalizar um `fiscal_monitoring_run`, `skip_reason` MUST caber em 80 caracteres (truncate seguro).

#### Scenario: skip_reason longo não quebra o UPDATE
- **WHEN** o payload de bloqueio tem `skip_reason` com mais de 80 caracteres
- **THEN** a persistência trunca para 80 e conclui o UPDATE sem erro SQLSTATE 22001

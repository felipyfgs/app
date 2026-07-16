## MODIFIED Requirements

### Requirement: Mutações fail-closed
Operações mutantes produtivas SHALL permanecer desligadas por padrão e exigir flag, allowlist, assinatura writable, papel ADMIN, reconfirmação de senha do ator válida por no máximo quinze minutos, confirmação da ação, elegibilidade, idempotência, orçamento, contrato saudável e kill switch aberto. TOTP/2FA MUST NOT ser exigido como gate adicional.

#### Scenario: Scheduler encontra mutação pendente
- **WHEN** um ciclo automático de monitoramento identificar uma ação mutante possível
- **THEN** ele MUST NOT criar nem executar a intenção mutante

#### Scenario: Gate ausente
- **WHEN** qualquer gate mutante estiver ausente
- **THEN** o transporte externo SHALL ser bloqueado antes da chamada

#### Scenario: Senha recente e flag desligada
- **WHEN** um ADMIN possui senha recente mas a flag mutante está desligada
- **THEN** o transporte externo SHALL permanecer bloqueado


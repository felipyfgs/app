## MODIFIED Requirements

### Requirement: OAuth2 mTLS e autorização por operação
O sistema SHALL autenticar o contrato global da plataforma com o SERPRO por mTLS/client credentials, cachear `access_token` e `jwt_token` coordenadamente e manter Termo, token de procurador e poderes como artefatos técnicos internos. Para cada chamada, o autor SHALL ser derivado do `CurrentOffice`, de seu perfil, consentimento e credencial canônica, e token/poder e-CAC SHALL ser exigidos somente conforme o metadado da `operation_key` e a relação autor–contribuinte; o tenant MUST NOT fornecer esses artefatos ou coordenadas.

#### Scenario: Operação sem procuração aplicável
- **WHEN** o catálogo indicar que token/poder não se aplicam
- **THEN** a chamada SHALL prosseguir sem exigir token de procurador globalmente

#### Scenario: Poder obrigatório ausente
- **WHEN** a operação exigir poder e-CAC que o office não possui
- **THEN** a chamada SHALL falhar antes do transporte externo

#### Scenario: Tenant fornece artefato técnico
- **WHEN** frontend, controller tenant-scoped ou payload tentar definir autor do pedido, Termo, OAuth, token, ETag, ambiente ou poder
- **THEN** o sistema SHALL rejeitar ou ignorar o valor e usar somente o estado interno derivado

## ADDED Requirements

### Requirement: Onboarding SERPRO automatizado por escritório
Quando perfil válido, consentimento vigente e A1 canônico compatível estiverem presentes, o sistema SHALL usar jobs Horizon tenant-scoped para gerar e assinar o Termo, executar `Apoiar`, armazenar token/ETag, sincronizar poderes e renovar a autorização. O fluxo MUST usar locks e idempotência por office e MUST NOT expor XML, PFX, senha ou tokens ao tenant.

#### Scenario: Pré-requisitos ficam completos
- **WHEN** um office passa a possuir perfil, consentimento e A1 compatíveis
- **THEN** o sistema SHALL enfileirar o onboarding interno sem solicitar campos técnicos ao contador

#### Scenario: Reprocessamento do mesmo evento
- **WHEN** o mesmo evento de prontidão for entregue mais de uma vez
- **THEN** locks e idempotência SHALL impedir Termos ou autorizações duplicados

### Requirement: Separação da configuração global SERPRO
Contrato comercial, Consumer Key/Secret, certificado mTLS do contratante, ambiente, orçamento, rollout e diagnóstico técnico do SERPRO MUST ser configuráveis e visíveis somente em rotas de plataforma. APIs tenant-scoped MUST retornar apenas estados acionáveis pelo escritório e uma correlação sanitizada.

#### Scenario: Falha de OAuth global
- **WHEN** o onboarding falhar por Consumer Secret ou mTLS da plataforma
- **THEN** o detalhe SHALL aparecer apenas para a plataforma e o escritório SHALL receber estado indisponível não técnico

#### Scenario: Perfil do escritório incompleto
- **WHEN** o onboarding estiver bloqueado por CNPJ, consentimento ou A1 ausente
- **THEN** o painel do escritório SHALL identificar exatamente a ação que seu administrador pode corrigir


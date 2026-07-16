# serpro-gateway-seguro

## Purpose

Gateway único e fail-closed para Integra Contador: `operation_key`, envelope oficial, OAuth mTLS, headers restritos, assíncrono seguro, bilhetagem e sanitização de segredos.

## Requirements

### Requirement: Requisição dirigida por operation_key
O gateway MUST aceitar somente uma `operation_key` conhecida, identidades tipadas e dados de negócio; coordenadas e headers oficiais MUST vir do catálogo.

#### Scenario: Coordenada fornecida pelo chamador
- **WHEN** frontend, controller ou job tentar definir rota, sistema, serviço, versão ou header protegido
- **THEN** o gateway SHALL rejeitar ou ignorar o valor sem alterar a chamada oficial

### Requirement: Envelope e identidade oficiais
O gateway SHALL produzir o envelope obrigatório com contratante global, autor, contribuinte e `pedidoDados.dados` serializado exatamente uma vez, usando NI textual uppercase e tipo explícito.

#### Scenario: CNPJ alfanumérico
- **WHEN** o contribuinte possuir CNPJ alfanumérico válido
- **THEN** o gateway SHALL preservá-lo como texto uppercase e tipo CNPJ

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

### Requirement: Rotas, headers e tags restritos
O gateway MUST limitar chamadas às cinco rotas oficiais, usar allowlist de headers e gerar `X-Request-Tag` opaca, determinística, sem NI e com no máximo 32 caracteres.

#### Scenario: Header arbitrário
- **WHEN** um chamador fornecer header não permitido
- **THEN** o header SHALL ser descartado antes do transporte

### Requirement: Respostas assíncronas e retries seguros
O gateway SHALL normalizar 202, 204 e 304 com espera/cache, renovar OAuth uma única vez após 401 e respeitar `Retry-After` em 429/503; timeout ambíguo de mutação MUST NOT ser repetido automaticamente.

#### Scenario: OAuth expirado
- **WHEN** a primeira tentativa retornar 401
- **THEN** o token SHALL ser invalidado e uma única nova tentativa SHALL reutilizar a mesma tag

#### Scenario: Timeout de mutação
- **WHEN** o transporte de uma operação mutante terminar sem resposta conclusiva
- **THEN** a intenção SHALL ficar pendente de conciliação sem nova chamada automática

### Requirement: Faturamento oficial
O ledger SHALL classificar Apoiar/Monitorar e HTTP 204, 304, 400, 401, 404, 429, 500 e 503 como não faturáveis, registrando 200, 202 e 403 nas demais rotas conforme a classe da operação.

#### Scenario: Resposta 403 em Consultar
- **WHEN** uma operação Consultar retornar 403
- **THEN** o ledger SHALL preservar a tentativa como potencialmente faturável e conciliável

### Requirement: Segredos não observáveis
PFX, OAuth, Termo, tokens, ETag sensível, XML bruto e payload fiscal MUST NOT aparecer em logs, respostas públicas ou exports.

#### Scenario: Serialização sanitizada
- **WHEN** uma resposta ou erro for convertido para auditoria/API
- **THEN** somente metadados allowlisted SHALL ser expostos

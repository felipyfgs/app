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
O sistema SHALL autenticar o contrato global por mTLS/client credentials, cachear `access_token` e `jwt_token` coordenadamente e aplicar Termo, token do procurador e poder e-CAC conforme o metadado e a relação autor–contribuinte.

#### Scenario: Operação sem procuração aplicável
- **WHEN** o catálogo indicar que token/poder não se aplicam
- **THEN** a chamada SHALL prosseguir sem exigir token de procurador globalmente

#### Scenario: Poder obrigatório ausente
- **WHEN** a operação exigir poder e-CAC que o office não possui
- **THEN** a chamada SHALL falhar antes do transporte externo

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

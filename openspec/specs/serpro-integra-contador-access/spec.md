# serpro-integra-contador-access Specification

## Purpose

Sincronizado a partir de `build-complete-fiscal-monitoring-hub` (2026-07-15).

## Requirements

### Requirement: Contrato SERPRO central da software house
O sistema SHALL manter no máximo um contrato Integra Contador ativo por ambiente no escopo global da plataforma, identificado pelo CNPJ contratante e por metadados sanitizados, sem atribuí-lo a um escritório tenant.

#### Scenario: Segundo contrato ativo
- **WHEN** um administrador tenta ativar outro contrato no mesmo ambiente
- **THEN** o sistema exige substituição transacional ou rejeita a ativação sem deixar dois contratos simultaneamente ativos

### Requirement: Autenticação mTLS e OAuth2 com a identidade contratante
O sistema MUST obter Bearer Token e JWT usando `Consumer Key`, `Consumer Secret` e o mesmo e-CNPJ do contrato, com PFX somente em memória, TLS mínimo 1.2 e verificação de hostname.

#### Scenario: Certificado não corresponde ao contrato
- **WHEN** a autenticação usa certificado diferente da identidade contratante configurada
- **THEN** o sistema bloqueia o contrato, não tenta chamadas fiscais e cria alerta sanitizado

#### Scenario: Token contratante expirado
- **WHEN** o Bearer/JWT expira antes de uma chamada elegível
- **THEN** uma renovação coordenada obtém novo token sem expor credenciais ou gerar tempestade de autenticação

### Requirement: Cadeia Contratante, Autor e Contribuinte
O sistema MUST montar cada pedido a partir da Contratante global, do Autor do Pedido vinculado ao `office_id` e do Contribuinte pertencente ao mesmo tenant; valores de identidade enviados pelo frontend MUST NOT substituir registros persistidos.

#### Scenario: Contribuinte de outro tenant
- **WHEN** uma operação tenta combinar autorização de um escritório com contribuinte de outro
- **THEN** o sistema rejeita a chamada antes de acessar o SERPRO e registra o motivo sem dados fiscais completos

### Requirement: Termo de Autorização por escritório
O sistema SHALL exigir Termo XMLDSig válido, assinado com e-CPF ou e-CNPJ ICP-Brasil do Autor do Pedido, com identidade coincidente, vigência verificável e destinatário igual à software house contratante.

#### Scenario: Termo válido assinado externamente
- **WHEN** o escritório envia um Termo assinado cujo certificado, identidade, destinatário e vigência são válidos
- **THEN** o sistema preserva o XML assinado no cofre, registra apenas metadados sanitizados e solicita o token do procurador

#### Scenario: Assinante divergente
- **WHEN** a identidade `assinadoPor` difere do titular do certificado
- **THEN** o sistema rejeita o termo sem armazenar material inseguro nem ativar a autorização

### Requirement: Renovação controlada do token do procurador
O sistema SHALL registrar a expiração do `autenticar_procurador_token`, renovar antes do uso e tratar reapresentação de Termo assinado como estratégia configurável validada por ambiente.

#### Scenario: Token válido em cache
- **WHEN** o token do procurador ainda está válido
- **THEN** chamadas do mesmo Autor reutilizam o token protegido sem reenviar repetidamente o Termo

#### Scenario: Renovação exige nova assinatura
- **WHEN** o ambiente informa que o Termo armazenado não pode renovar o token
- **THEN** a autorização passa para `ACTION_REQUIRED`, jobs ficam bloqueados e o escritório recebe orientação para nova assinatura

### Requirement: Procuração e poder específicos por serviço
O sistema MUST verificar que o Autor possui procuração válida do Contribuinte e poder compatível com o sistema/serviço solicitado antes de qualquer consulta, emissão ou transmissão.

#### Scenario: Procuração sem poder suficiente
- **WHEN** existe procuração vigente, mas ela não inclui o serviço solicitado
- **THEN** a operação é marcada `BLOCKED` com código de elegibilidade e nenhuma chamada faturável é enviada

#### Scenario: Procuração expirada
- **WHEN** a data de execução ultrapassa a vigência verificada
- **THEN** o scheduler não inicia a chamada e cria pendência de renovação para o tenant

### Requirement: Segredos e artefatos de autorização nunca são recuperáveis
O sistema MUST NOT retornar em API, log, métrica, exportação ou auditoria PFX, senha, chave privada, PEM, `Consumer Secret`, Bearer/JWT, token do procurador ou Termo XML assinado e MUST NOT oferecer rota de recuperação desses valores.

#### Scenario: Consulta da configuração SERPRO
- **WHEN** usuário autorizado consulta a integração
- **THEN** a resposta contém somente estado, identidade mascarada, fingerprint, vigência, saúde e timestamps sanitizados

### Requirement: Trial não equivale a homologação produtiva
O sistema SHALL identificar chamadas de demonstração como dados simulados e MUST impedir que resultados trial sejam persistidos como evidência fiscal real ou usados para declarar regularidade de contribuinte.

#### Scenario: Execução no trial
- **WHEN** um cenário mock retorna sucesso
- **THEN** a execução é marcada `SIMULATED` e não altera o estado fiscal produtivo do cliente

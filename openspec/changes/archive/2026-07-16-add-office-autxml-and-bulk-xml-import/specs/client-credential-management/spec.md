## ADDED Requirements

### Requirement: Credenciais do cliente e do escritório são agregados distintos
O sistema MUST manter o e-CNPJ A1 de cada cliente separado da credencial fiscal própria do escritório em modelo, tabela, política, objeto de vault, AAD e serviço de resolução. A credencial do escritório MUST NOT ser cadastrada mediante Cliente fictício nem copiada para `client_credentials`; e a credencial do cliente MUST NOT ser promovida, referenciada ou copiada como credencial do escritório.

#### Scenario: Cadastro do A1 do escritório
- **WHEN** ADMIN com 2FA recente ativa uma credencial para a identidade fiscal do escritório
- **THEN** o material é gravado em objeto de vault pertencente ao escritório e nenhum `client_credential` é criado ou alterado

#### Scenario: Cadastro do A1 de cliente
- **WHEN** ADMIN ativa uma credencial para uma raiz de Cliente
- **THEN** o material permanece vinculado somente ao Cliente e não habilita o canal autXML do escritório

#### Scenario: Mesmo fingerprint em proprietários diferentes
- **WHEN** uma tentativa de cadastro reutiliza fingerprint ou material já associado a proprietário de tipo diferente
- **THEN** o sistema não cria vínculo cruzado silencioso e exige tratamento explícito sem expor o PFX ou a senha

### Requirement: Uso de A1 limitado ao proprietário e ao canal
O sistema SHALL resolver credenciais de cliente exclusivamente para canais executados em nome daquele cliente e SHALL resolver a credencial do escritório exclusivamente para canais autorizados do próprio escritório, inicialmente `NFE_AUTXML_DISTDFE`. O sistema MUST verificar no backend `office_id`, tipo do proprietário, identificador do proprietário, CNPJ compatível, estado e validade antes de materializar qualquer PFX.

#### Scenario: Job de cliente tenta usar A1 do escritório
- **WHEN** DistDFe de entrada, CT-e, ADN, manifestação, consulta outbound ou recuperação MA tenta resolver a credencial do escritório
- **THEN** a operação é rejeitada antes da materialização e os canais do cliente preservam seus cursores

#### Scenario: Job autXML tenta usar A1 de cliente
- **WHEN** o canal `NFE_AUTXML_DISTDFE` tenta resolver um `client_credential`
- **THEN** a operação é rejeitada antes da chamada externa e o cursor central não avança

#### Scenario: Identificador de outro escritório
- **WHEN** API ou job referencia credencial cuja `office_id` difere do contexto autenticado/persistido
- **THEN** o sistema não revela sua existência nem seus metadados e não acessa o objeto do vault

#### Scenario: Materialização válida do A1 do escritório
- **WHEN** o job autXML resolve a credencial ACTIVE e não vencida da identidade fiscal correta
- **THEN** PFX e senha existem apenas em memória durante a chamada mTLS e não são retornados ao job payload, API, log ou auditoria

### Requirement: Falhas de credencial não atravessam proprietários
O sistema SHALL bloquear e alertar somente os cursores que dependem da credencial ausente, inválida, substituída ou vencida. A substituição/expiração do A1 do escritório MUST NOT bloquear cursores de clientes, e a substituição/expiração de um A1 de cliente MUST NOT bloquear o cursor autXML do escritório.

#### Scenario: A1 do escritório vencido
- **WHEN** a credencial fiscal ACTIVE do escritório vence
- **THEN** os cursores `NFE_AUTXML_DISTDFE` dependentes são bloqueados e os jobs dos clientes continuam elegíveis conforme suas próprias credenciais

#### Scenario: A1 de cliente vencido
- **WHEN** uma credencial de cliente vence
- **THEN** somente os canais dependentes daquela raiz são bloqueados e o stream autXML do escritório preserva estado e agenda

#### Scenario: Substituição falha do A1 do escritório
- **WHEN** um novo PFX do escritório falha na validação
- **THEN** a credencial anterior permanece ativa e nenhuma credencial de cliente é modificada

#### Scenario: Renovação concluída
- **WHEN** o A1 do escritório é substituído com sucesso
- **THEN** somente os cursores do escritório podem voltar à elegibilidade, sem reset de NSU e sem desbloqueio automático de cursor de cliente


# Client Credential Management

## Purpose

Cadastro de clientes/estabelecimentos e gestão segura do certificado e-CNPJ A1 (cofre, sem recuperação pela API, alertas de validade).

## Requirements

### Requirement: Cadastro manual de clientes e estabelecimentos
O sistema SHALL permitir que administradores e operadores criem transacionalmente uma raiz de Cliente e seu primeiro Estabelecimento a partir de um CNPJ completo e SHALL permitir adicionar outros estabelecimentos individualmente, mantendo cada CNPJ como texto normalizado de 14 caracteres e derivando a raiz no backend.

#### Scenario: Criação atômica do primeiro estabelecimento
- **WHEN** um usuário autorizado envia razão social, CNPJ completo válido e os dados editáveis do cadastro
- **THEN** o sistema cria Cliente e primeiro Estabelecimento no mesmo escritório e na mesma transação e devolve ambos os registros

#### Scenario: Criação com contato responsável
- **WHEN** a criação inclui contato interno responsável válido
- **THEN** o sistema cria Cliente, primeiro Estabelecimento e Contato no mesmo escritório e na mesma transação e devolve os três registros

#### Scenario: Criação com campos adicionais
- **WHEN** a criação inclui campos adicionais válidos
- **THEN** o sistema cria seus metadados na mesma transação, guarda valores secretos somente no cofre e nunca devolve conteúdo secreto na resposta

#### Scenario: Falha durante a criação
- **WHEN** qualquer validação ou persistência do Cliente ou do primeiro Estabelecimento falha
- **THEN** a transação é revertida e nenhum dos dois registros fica parcialmente criado

#### Scenario: CNPJ alfanumérico válido
- **WHEN** o usuário informa um CNPJ contendo letras e números com dígitos verificadores válidos
- **THEN** o sistema salva o identificador em maiúsculas, sem máscara e sem conversão numérica

#### Scenario: Estabelecimento de outra raiz
- **WHEN** o usuário tenta associar ao cliente um estabelecimento cuja raiz difere da raiz cadastrada
- **THEN** o sistema rejeita o cadastro com erro de validação

#### Scenario: Raiz já cadastrada no escritório
- **WHEN** a criação de novo Cliente recebe um CNPJ cuja raiz já pertence a Cliente visível no escritório ativo
- **THEN** o sistema não cria registros silenciosamente e devolve erro acionável para continuar no Cliente existente

#### Scenario: CNPJ existente em outro escritório
- **WHEN** a raiz ou o CNPJ informado existe somente em outro escritório
- **THEN** o sistema não revela o registro externo e aplica as regras de unicidade no escopo autorizado

#### Scenario: Matriz única por cliente
- **WHEN** uma criação ou atualização produziria duas matrizes não excluídas para o mesmo Cliente
- **THEN** o sistema rejeita a operação sem alterar a matriz vigente

### Requirement: Preenchimento assistido por consulta pública de CNPJ
O sistema SHALL permitir que administradores e operadores consultem pelo backend dados públicos de um CNPJ numérico válido e recebam somente identidade da raiz, natureza jurídica, porte, tipo e nome do estabelecimento, situação cadastral, início da atividade, CNAE principal, endereço, contato público, origem e datas de atualização; o sistema SHALL tratar esses dados como sugestões editáveis e SHALL manter preenchimento manual quando a fonte estiver indisponível, desatualizada ou não suportar o formato informado.

#### Scenario: CNPJ numérico localizado
- **WHEN** o usuário informa um CNPJ numérico válido localizado pela fonte configurada
- **THEN** o sistema retorna DTO sanitizado separado em Cliente e Estabelecimento e permite revisar ou editar os valores antes da criação

#### Scenario: Consulta externa indisponível
- **WHEN** a fonte demora, falha, limita requisições ou não localiza o CNPJ
- **THEN** o sistema informa a falha de modo sanitizado, preserva os dados digitados e permite concluir o cadastro manual

#### Scenario: CNPJ alfanumérico
- **WHEN** o usuário informa um CNPJ alfanumérico válido ainda não suportado pela fonte pública
- **THEN** o sistema não descarta o valor, não faz chamada incompatível e orienta o preenchimento manual dos campos necessários

#### Scenario: Dado externo desatualizado
- **WHEN** a consulta retorna datas de atualização anteriores ao momento do cadastro
- **THEN** a API e a interface preservam origem e data e não apresentam o conteúdo como confirmação em tempo real

#### Scenario: Campo não permitido no fornecedor
- **WHEN** a resposta externa contém QSA, CPF, capital social, inscrições ou campos desconhecidos fora da lista permitida
- **THEN** o adaptador descarta esses campos antes de cache, resposta, persistência ou log

### Requirement: Dados cadastrais estruturados por nível
O sistema SHALL manter razão social, nome interno, natureza jurídica e porte no Cliente e SHALL manter nome fantasia, matriz/filial, situação cadastral, início da atividade, CNAE principal, endereço e contato público no Estabelecimento, sem duplicar dados de filiais na raiz.

#### Scenario: Atualização de dados da raiz
- **WHEN** um administrador ou operador altera razão social, nome interno, natureza jurídica, porte, estado ou observações
- **THEN** o sistema valida e atualiza somente o Cliente do escritório ativo e registra os campos modificados em auditoria

#### Scenario: Atualização de estabelecimento
- **WHEN** um administrador ou operador altera dados cadastrais de um estabelecimento
- **THEN** o sistema preserva CNPJ e raiz imutáveis, valida endereço e estado e atualiza somente o estabelecimento autorizado

#### Scenario: Consulta do cadastro
- **WHEN** um usuário autorizado abre o detalhe de Cliente
- **THEN** a API devolve os dados da raiz, estabelecimentos e contatos do escritório ativo sem payload bruto da fonte

### Requirement: Contatos internos separados do contato público
O sistema SHALL permitir contatos internos estruturados por Cliente e MUST manter esses contatos separados do telefone e e-mail públicos do Estabelecimento.

#### Scenario: Cadastro de contato interno
- **WHEN** um administrador ou operador informa nome e ao menos um canal válido de contato
- **THEN** o sistema cria o contato no Cliente e escritório ativos sem modificar o contato público do CNPJ

#### Scenario: Contato principal único
- **WHEN** o usuário define um novo contato principal
- **THEN** o sistema garante no máximo um contato principal ativo por Cliente de forma atômica

#### Scenario: Contato público consultado
- **WHEN** a fonte externa retorna telefone ou e-mail do estabelecimento
- **THEN** o sistema os identifica como públicos e não cria contato interno automaticamente

### Requirement: Campos adicionais seguros por Cliente
O sistema SHALL permitir campos adicionais rotulados por Cliente com tipo texto ou segredo e MUST armazenar segredos no cofre criptografado sem expor seu conteúdo por API, log, auditoria ou exportação.

#### Scenario: Campo de texto
- **WHEN** um usuário autorizado grava um campo adicional de texto
- **THEN** o sistema persiste e devolve rótulo, tipo e valor no escopo do escritório

#### Scenario: Campo secreto
- **WHEN** um administrador com 2FA grava um campo adicional secreto
- **THEN** o sistema guarda o valor no cofre e devolve somente rótulo, tipo e indicador de valor configurado

#### Scenario: Operador tenta gravar segredo
- **WHEN** um usuário sem gestão de segredos envia campo do tipo segredo
- **THEN** o sistema rejeita o campo sem persistir conteúdo parcial ou registrar o valor

### Requirement: Estados operacionais e elegibilidade de captura independentes
O sistema SHALL distinguir situação cadastral externa, estado interno do Cliente/Estabelecimento e habilitação de captura e MUST impedir disparo manual ou agendado quando Cliente ou Estabelecimento estiver inativo, a captura estiver desabilitada, a credencial estiver inválida ou o cursor estiver bloqueado.

#### Scenario: Situação cadastral não ativa
- **WHEN** a consulta retorna situação diferente de ativa para um novo estabelecimento
- **THEN** o sistema permite conservar o cadastro, inicia a captura desabilitada e apresenta o motivo para revisão

#### Scenario: Cadastro manual com situação desconhecida
- **WHEN** um CNPJ válido é cadastrado manualmente sem situação cadastral confirmada
- **THEN** o sistema usa situação `UNKNOWN` sem transformar ausência de consulta em situação negativa

#### Scenario: Habilitação excepcional da captura
- **WHEN** um administrador ou operador habilita captura para situação externa não ativa após revisão
- **THEN** o sistema exige motivo, registra a decisão em auditoria e mantém as demais condições de sincronização

#### Scenario: Scheduler encontra entidade inelegível
- **WHEN** o Scheduler encontra cursor ligado a Cliente inativo, Estabelecimento inativo ou captura desabilitada
- **THEN** o sistema não enfileira sincronização e preserva o NSU vigente

### Requirement: Isolamento e auditoria do cadastro ampliado
O sistema MUST derivar `office_id` da sessão para Cliente, Estabelecimento e Contato e SHALL auditar criação, alteração, inativação e habilitação de captura sem registrar dados proibidos ou payload externo bruto.

#### Scenario: Office enviado pelo navegador
- **WHEN** uma requisição de cadastro inclui `office_id` diferente do escritório autenticado
- **THEN** o sistema ignora ou rejeita o valor e persiste somente no escritório derivado da sessão

#### Scenario: Auditoria da alteração
- **WHEN** um campo cadastral ou operacional é alterado
- **THEN** o log registra ator, entidade e nomes dos campos alterados sem QSA, CPF, PFX, senha, chave privada, PEM ou resposta externa bruta

### Requirement: Validação do A1 antes da ativação
O sistema MUST validar senha, validade, identificação do titular, fingerprint e raiz do CNPJ de um PFX antes de ativá-lo para um cliente.

#### Scenario: Certificado compatível
- **WHEN** um administrador envia um A1 válido cuja raiz corresponde ao cliente
- **THEN** o sistema armazena o material criptografado e retorna somente seus metadados não secretos

#### Scenario: Senha ou raiz inválida
- **WHEN** a senha não abre o PFX ou o certificado pertence a outra raiz
- **THEN** o sistema rejeita o upload e não persiste o material sensível

### Requirement: Cofre criptografado
O sistema MUST criptografar PFX e senha por envelope com uma chave de dados exclusiva e MUST manter a chave mestra fora do banco de dados.

#### Scenario: Inspeção do armazenamento
- **WHEN** um arquivo do cofre ou um registro de credencial é lido diretamente sem a chave mestra
- **THEN** o conteúdo do PFX e sua senha permanecem ininteligíveis

### Requirement: Certificado não recuperável pela API
O sistema SHALL NOT expor endpoint, resposta, log ou exportação que devolva PFX, senha, chave privada ou representação PEM.

#### Scenario: Consulta de credencial
- **WHEN** um administrador consulta a credencial ativa de um cliente
- **THEN** a resposta contém somente CNPJ, titular, fingerprint, validade e estado

### Requirement: Substituição atômica da credencial
O sistema SHALL ativar um novo A1 somente após validação completa e SHALL invalidar criptograficamente o material substituído, preservando seus metadados de auditoria.

#### Scenario: Falha ao substituir certificado
- **WHEN** um novo PFX falha na validação
- **THEN** o certificado anteriormente ativo continua disponível para sincronização

### Requirement: Alertas de validade
O sistema SHALL classificar credenciais a vencer em 30, 7 e 1 dia e SHALL bloquear novas sincronizações após o vencimento.

#### Scenario: Certificado vencido
- **WHEN** o Scheduler encontra uma credencial com validade expirada
- **THEN** os cursores dependentes são marcados como bloqueados e um alerta operacional é exibido

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

### Requirement: Uso do A1 da raiz em múltiplos canais oficiais
O sistema SHALL reutilizar o certificado e-CNPJ A1 ativo da raiz do cliente para canais de captura oficiais habilitados (ADN NFS-e e SEFAZ DistDFe/CT-e/MDF-e), sem armazenar cópia adicional do PFX fora do vault e sem expor material criptográfico.

#### Scenario: Mesmo A1 para ADN e DistDFe
- **WHEN** o estabelecimento tem captura ADN e captura DistDFe habilitadas
- **THEN** ambos os jobs obtêm o PFX do mesmo objeto de vault da raiz e o usam somente em memória

### Requirement: Elegibilidade de captura por canal
O sistema SHALL expor elegibilidade de captura por canal (ADN, DistDFe, CT-e, MDF-e) com motivos claros (sem A1, A1 vencido, captura desligada, cursor bloqueado).

#### Scenario: DistDFe inelegível sem A1
- **WHEN** o cliente não possui credencial A1 ativa
- **THEN** a elegibilidade DistDFe é falsa e o job não é enfileirado

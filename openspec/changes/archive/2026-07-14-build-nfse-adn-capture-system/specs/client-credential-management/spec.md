## ADDED Requirements

### Requirement: Cadastro manual de clientes e estabelecimentos
O sistema SHALL permitir que administradores e operadores cadastrem uma raiz de cliente e seus estabelecimentos individualmente, mantendo o CNPJ completo como texto normalizado de 14 caracteres.

#### Scenario: CNPJ alfanumérico válido
- **WHEN** o usuário informa um CNPJ contendo letras e números com dígitos verificadores válidos
- **THEN** o sistema salva o identificador em maiúsculas, sem máscara e sem conversão numérica

#### Scenario: Estabelecimento de outra raiz
- **WHEN** o usuário tenta associar ao cliente um estabelecimento cuja raiz difere da raiz cadastrada
- **THEN** o sistema rejeita o cadastro com erro de validação

### Requirement: Preenchimento assistido por consulta pública de CNPJ
O sistema SHALL permitir que administradores e operadores consultem dados públicos de um CNPJ numérico válido durante o cadastro e SHALL manter o preenchimento manual disponível quando a fonte externa estiver indisponível ou não suportar o formato informado.

#### Scenario: CNPJ numérico localizado
- **WHEN** o usuário informa um CNPJ numérico válido no cadastro de cliente
- **THEN** o sistema consulta a fonte pública pelo backend e sugere a razão social sem impedir que o usuário a edite

#### Scenario: Consulta externa indisponível
- **WHEN** a fonte pública demora, falha ou não localiza o CNPJ
- **THEN** o sistema informa a falha de modo sanitizado e preserva os dados já digitados para cadastro manual

#### Scenario: CNPJ alfanumérico
- **WHEN** o usuário informa um CNPJ alfanumérico válido ainda não suportado pela fonte pública
- **THEN** o sistema não descarta o valor e orienta o preenchimento manual do nome

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

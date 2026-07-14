## ADDED Requirements

### Requirement: Autenticação interna segura
O sistema SHALL autenticar funcionários do escritório por sessão stateful protegida por CSRF e SHALL encerrar a sessão por solicitação explícita do usuário.

#### Scenario: Login válido
- **WHEN** um usuário ativo informa credenciais válidas
- **THEN** o sistema cria uma sessão vinculada ao usuário e ao escritório autorizado

#### Scenario: Requisição sem CSRF
- **WHEN** uma sessão tenta executar uma operação mutável sem token CSRF válido
- **THEN** o sistema rejeita a operação sem modificar dados

### Requirement: Segundo fator para administradores
O sistema MUST exigir TOTP configurado e confirmado para todo usuário com perfil `ADMIN` antes de liberar funções administrativas.

#### Scenario: Administrador sem segundo fator
- **WHEN** um administrador autenticado ainda não confirmou o TOTP
- **THEN** o sistema permite apenas concluir o segundo fator ou encerrar a sessão

### Requirement: Autorização por perfil
O sistema SHALL aplicar os perfis `ADMIN`, `OPERATOR` e `VIEWER` em todas as rotas e ações de negócio.

#### Scenario: Operador tenta administrar certificado
- **WHEN** um operador tenta enviar, substituir ou consultar o material de um certificado
- **THEN** o sistema nega a ação e registra a tentativa

#### Scenario: Usuário de consulta altera cliente
- **WHEN** um usuário `VIEWER` tenta criar ou alterar um cliente
- **THEN** o sistema nega a operação sem modificar o cadastro

### Requirement: Isolamento por escritório
O sistema MUST derivar o escritório ativo da associação do usuário e MUST impedir leitura ou alteração de recursos pertencentes a outro escritório.

#### Scenario: Identificador de outro escritório
- **WHEN** um usuário solicita um recurso válido pertencente a outro escritório
- **THEN** o sistema responde como recurso não encontrado e não revela sua existência

### Requirement: Usuários exclusivamente internos
O sistema SHALL permitir associações de acesso somente a funcionários do escritório e SHALL NOT criar contas para os clientes contábeis no MVP.

#### Scenario: Cadastro de cliente
- **WHEN** um funcionário cadastra um cliente contábil
- **THEN** o sistema não cria credenciais de login nem associação de usuário para esse cliente

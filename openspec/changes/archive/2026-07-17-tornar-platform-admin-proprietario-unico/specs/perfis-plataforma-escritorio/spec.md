## ADDED Requirements

### Requirement: Instalação possui um único proprietário global
Uma instalação SHALL admitir no máximo uma `PlatformMembership` com papel `PLATFORM_ADMIN`, independentemente do estado ativo, pendente ou inativo do usuário. A ausência desse vínculo SHALL ser válida somente antes da conclusão do onboarding inicial; usuários e `OfficeMembership` comuns MUST continuar múltiplos e não consumir nem criar a autoridade global.

#### Scenario: Onboarding ainda não foi concluído
- **WHEN** a base está estruturalmente vazia e nenhum onboarding foi concluído
- **THEN** o sistema SHALL permitir a criação transacional do primeiro e único proprietário

#### Scenario: Segundo proprietário é solicitado
- **WHEN** qualquer API, comando, seed ou serviço tenta criar outro `PLATFORM_ADMIN`
- **THEN** a operação MUST falhar com `platform_owner_already_exists` sem criar usuário, vínculo, ativação ou estado parcial

#### Scenario: Criações concorrentes
- **WHEN** duas transações tentam criar o primeiro `PLATFORM_ADMIN` simultaneamente
- **THEN** a restrição do banco MUST permitir no máximo um vínculo global e a transação perdedora MUST falhar sem estado parcial

#### Scenario: Escritórios continuam multiusuário
- **WHEN** o proprietário cria Offices ou os Offices administram suas equipes
- **THEN** usuários e memberships `ADMIN`, `OPERATOR` e `VIEWER` SHALL continuar permitidos sem receber `PLATFORM_ADMIN`

### Requirement: Recuperação preserva o proprietário singleton
A perda de acesso, inatividade ou substituição do titular MUST ser resolvida por operação administrativa de host que atualize ou transfira o vínculo existente atomicamente. O sistema MUST exigir confirmação interativa, receber senha somente por entrada oculta, revogar sessões afetadas, registrar auditoria sanitizada e nunca manter dois `PLATFORM_ADMIN` no mesmo commit.

#### Scenario: Credencial do proprietário foi perdida
- **WHEN** o operador recupera nome, e-mail ou senha do titular existente pelo comando dedicado
- **THEN** a identidade SHALL ser atualizada, suas sessões anteriores MUST ser revogadas e o único vínculo global MUST ser preservado

#### Scenario: Propriedade é transferida
- **WHEN** o operador confirma a transferência para um usuário-alvo válido
- **THEN** a mesma autoridade global SHALL apontar para o novo titular no mesmo commit, o anterior SHALL perder o papel e as sessões de ambos MUST ser revogadas

#### Scenario: Exclusão comum do proprietário
- **WHEN** uma operação comum tenta excluir o usuário ou remover o único vínculo global
- **THEN** o sistema MUST bloquear a ação e orientar o fluxo dedicado de recuperação ou transferência

#### Scenario: Senha é informada ao comando
- **WHEN** a recuperação exige definição de nova senha
- **THEN** o comando MUST recebê-la por prompt oculto e MUST NOT aceitá-la em argumento, log, stdout ou auditoria

## MODIFIED Requirements

### Requirement: Bootstrap cria uma conta dual
O primeiro bootstrap MUST criar atomicamente um único usuário ativo com `PLATFORM_ADMIN`, uma membership `OfficeRole::ADMIN` no primeiro Office e esse Office como seu padrão global. Reexecutar o bootstrap MUST falhar se já existir qualquer `PlatformMembership`, usuário de onboarding ou Office, sem criar usuários, Offices, memberships ou assinaturas adicionais.

#### Scenario: Primeira instalação
- **WHEN** o comando de bootstrap é concluído em uma base estruturalmente vazia
- **THEN** a mesma conta SHALL acessar o grupo Admin e possuir poderes reais de Office ADMIN no Office criado

#### Scenario: Bootstrap repetido
- **WHEN** o comando é executado depois de já existir um proprietário, usuário de onboarding ou Office
- **THEN** o sistema SHALL falhar sem gravar registros parciais

### Requirement: Administrador global possui Office padrão sem membership
O único `PLATFORM_ADMIN`, denominado Proprietário da instalação, SHALL possuir um `default_office_id` válido resolvido pelo servidor quando houver Office disponível. O vínculo global MUST NOT criar `OfficeMembership`, alterar `users.selected_office_id` ou fazer o proprietário aparecer na equipe do Office; a conta dual criada pelo bootstrap conserva somente a membership criada explicitamente nesse bootstrap.

#### Scenario: Login do proprietário
- **WHEN** o Proprietário sem membership inicia uma sessão e seu Office padrão está ativo
- **THEN** o sistema SHALL montar o contexto daquele Office sem receber `office_id` do cliente

#### Scenario: Office padrão inativo
- **WHEN** o Office padrão não estiver mais ativo e não houver seleção global válida
- **THEN** `/me` SHALL retornar `200` com `current_office` nulo e `context_status=office_context_required`, endpoints tenant-scoped SHALL responder `409` com esse código e as rotas globais necessárias para escolher outro Office SHALL continuar acessíveis

#### Scenario: Equipe do escritório
- **WHEN** um Office ADMIN lista sua equipe
- **THEN** o Proprietário sem membership MUST NOT ser retornado

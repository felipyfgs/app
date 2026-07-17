## MODIFIED Requirements

### Requirement: Administrador global possui Office padrão sem membership
Todo novo `PLATFORM_ADMIN` SHALL possuir um `default_office_id` válido resolvido pelo servidor, exceto o primeiro administrador criado em uma instalação ainda sem nenhum Office. Nesse estado transitório, `default_office_id` MAY permanecer nulo; o cadastro do primeiro Office MUST preencher atomicamente o padrão global ausente do ator. O vínculo global MUST NOT criar `OfficeMembership`, alterar `users.selected_office_id` ou fazer o administrador aparecer na equipe do Office.

#### Scenario: Instalação ainda não possui Office
- **WHEN** o onboarding inicial cria o primeiro PLATFORM_ADMIN antes de existir qualquer Office
- **THEN** sua PlatformMembership SHALL existir ativa com `default_office_id` nulo e APIs tenant SHALL permanecer fail-closed

#### Scenario: Primeiro Office é cadastrado
- **WHEN** o administrador inicial com padrão nulo cadastra o primeiro Office
- **THEN** esse Office SHALL tornar-se seu `default_office_id` na mesma transação, sem OfficeMembership e sem alterar `users.selected_office_id`

#### Scenario: Login de administrador global
- **WHEN** um `PLATFORM_ADMIN` sem membership inicia uma sessão e seu Office padrão está ativo
- **THEN** o sistema SHALL montar o contexto daquele Office sem receber `office_id` do cliente

#### Scenario: Office padrão inativo
- **WHEN** o Office padrão não estiver ativo e não houver seleção global válida
- **THEN** `/me` SHALL retornar `200` com `current_office` nulo e `context_status=office_context_required`, endpoints tenant-scoped SHALL responder `409` com esse código e as rotas globais necessárias para ativar ou escolher outro Office SHALL continuar acessíveis

#### Scenario: Equipe do escritório
- **WHEN** um Office ADMIN lista sua equipe
- **THEN** administradores globais sem membership MUST NOT ser retornados

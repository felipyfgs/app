# perfis-plataforma-escritorio

## Purpose

Individualização de perfis Plataforma e Escritório: bootstrap dual, administrador global com Office padrão sem membership, seleção global, shell único por capacidade, reconfirmação de senha em ações sensíveis e contrato canônico da listagem global.

## Requirements

### Requirement: Bootstrap cria uma conta dual
O primeiro bootstrap MUST criar atomicamente um único usuário ativo com `PLATFORM_ADMIN`, uma membership `OfficeRole::ADMIN` no primeiro Office e esse Office como seu padrão global. Reexecutar o bootstrap MUST continuar falhando sem criar usuários, Offices, memberships ou assinaturas adicionais.

#### Scenario: Primeira instalação
- **WHEN** o comando de bootstrap é concluído em uma base sem Office
- **THEN** a mesma conta SHALL acessar o grupo Admin e possuir poderes reais de Office ADMIN no Office criado

#### Scenario: Bootstrap repetido
- **WHEN** o comando é executado depois de já existir um Office
- **THEN** o sistema SHALL falhar sem gravar registros parciais

### Requirement: Administrador global possui Office padrão sem membership
Todo novo `PLATFORM_ADMIN` SHALL possuir um `default_office_id` válido resolvido pelo servidor. O vínculo global MUST NOT criar `OfficeMembership`, alterar `users.selected_office_id` ou fazer o administrador aparecer na equipe do Office.

#### Scenario: Login de administrador global
- **WHEN** um `PLATFORM_ADMIN` sem membership inicia uma sessão e seu Office padrão está ativo
- **THEN** o sistema SHALL montar o contexto daquele Office sem receber `office_id` do cliente

#### Scenario: Office padrão inativo
- **WHEN** o Office padrão não estiver mais ativo e não houver seleção global válida
- **THEN** `/me` SHALL retornar `200` com `current_office` nulo e `context_status=office_context_required`, endpoints tenant-scoped SHALL responder `409` com esse código e as rotas globais necessárias para escolher outro Office SHALL continuar acessíveis

#### Scenario: Equipe do escritório
- **WHEN** um Office ADMIN lista sua equipe
- **THEN** administradores globais sem membership MUST NOT ser retornados

### Requirement: Seleção global permanece separada do tenant do usuário
Um `PLATFORM_ADMIN` SHALL poder selecionar outro Office ativo pelo endpoint global. A seleção MUST ser validada e persistida pelo servidor em espaço separado da seleção comum e MUST NOT confiar em `office_id` enviado a endpoints tenant-scoped.

#### Scenario: Troca pelo seletor
- **WHEN** o administrador seleciona um Office ativo da lista global
- **THEN** o sistema SHALL atualizar atomicamente a seleção da sessão e `default_office_id`, e novas requisições tenant-scoped SHALL usar esse Office mantendo o ator real

#### Scenario: Injeção de escopo
- **WHEN** o request tenant-scoped contiver `office_id` diferente no body ou query
- **THEN** o valor MUST ser removido e o contexto resolvido no servidor SHALL prevalecer

### Requirement: Aplicação única com navegação por capacidade
O sistema SHALL manter um único shell autenticado. Usuários de Escritório SHALL ver os módulos normais permitidos pelo papel; `PLATFORM_ADMIN` SHALL ver esses módulos e um grupo adicional `Admin`; somente o papel global SHALL acessar `/admin/*` e `/api/v1/platform/*`.

#### Scenario: Office ADMIN abre o painel
- **WHEN** um Office ADMIN sem papel global autentica
- **THEN** a sidebar SHALL omitir o grupo Admin e o servidor SHALL negar uma URL `/admin/*` direta

#### Scenario: Conta dual abre o painel
- **WHEN** a conta do bootstrap autentica
- **THEN** a mesma sidebar SHALL apresentar módulos normais e o grupo Admin sem trocar de aplicação

### Requirement: Identidade global compacta
Quando o contexto global estiver ativo, o seletor SHALL exibir somente o selo compacto `Plataforma · <Office>`. O painel MUST NOT renderizar banner privilegiado persistente nem explicações de arquitetura sobre o contexto.

#### Scenario: Plataforma consulta um Office
- **WHEN** um `PLATFORM_ADMIN` entra em um módulo tenant-scoped
- **THEN** o nome do Office SHALL aparecer no selo compacto e nenhum banner privilegiado SHALL ocupar a página

### Requirement: Suporte Work somente leitura sem membership
Um `PLATFORM_ADMIN` em contexto global SHALL poder consultar páginas e dados Work do Office selecionado. Criar, editar, excluir, executar, reivindicar, atribuir, comentar, anexar evidências e exportar dados Work MUST exigir membership ativa do ator naquele Office e a capacidade do papel real; privilégio global isolado MUST NOT satisfazer esse gate.

#### Scenario: Suporte consulta Work
- **WHEN** um administrador global sem membership abre uma lista ou detalhe Work
- **THEN** a leitura SHALL ser autorizada somente para o Office selecionado e as ações mutantes SHALL ser omitidas

#### Scenario: Suporte tenta alterar Work
- **WHEN** o mesmo administrador chama qualquer operação mutante ou exportação Work
- **THEN** a API SHALL responder `403` antes de gravar estado, arquivo, comentário, atribuição ou auditoria como membro

#### Scenario: Conta dual atua pelo Office
- **WHEN** o administrador global também possui membership ativa no Office corrente
- **THEN** as policies SHALL usar o papel real dessa membership para decidir as ações Work

### Requirement: Autenticação comum não exige TOTP
Login, navegação, leitura e troca de Office para todos os perfis MUST usar a autenticação comum por e-mail e senha e MUST NOT exigir cadastro, desafio ou sessão TOTP/2FA.

#### Scenario: Login de Plataforma
- **WHEN** um `PLATFORM_ADMIN` informa e-mail e senha válidos
- **THEN** o sistema SHALL iniciar a sessão sem redirecionar para desafio TOTP

#### Scenario: Login de Escritório
- **WHEN** um membro ativo de Office informa e-mail e senha válidos
- **THEN** o sistema SHALL iniciar a sessão sem depender de segredo 2FA legado

### Requirement: Ações sensíveis exigem senha recente por quinze minutos
Toda ação humana sensível anteriormente protegida por TOTP/2FA MUST exigir reconfirmação da senha do próprio ator, válida por no máximo quinze minutos e vinculada exclusivamente à sessão corrente. Logout, troca ou redefinição de senha, desativação do usuário e invalidação da sessão MUST encerrar a janela. Os demais gates aplicáveis MUST continuar fail-closed.

#### Scenario: Ação dentro da janela
- **WHEN** o usuário reconfirma corretamente sua senha e solicita uma ação sensível dentro de quinze minutos
- **THEN** o gate de identidade SHALL aprovar e a operação SHALL seguir para autorização, assinatura, flags, limites e demais controles

#### Scenario: Confirmação expirada
- **WHEN** mais de quinze minutos se passaram desde a reconfirmação
- **THEN** a operação SHALL ser bloqueada antes de qualquer efeito e exigir nova senha

#### Scenario: Senha é alterada
- **WHEN** o usuário altera ou redefine a senha durante uma janela ativa
- **THEN** a confirmação anterior MUST ser invalidada

#### Scenario: Nova sessão do mesmo usuário
- **WHEN** o mesmo usuário abre outra sessão sem reconfirmar sua senha nela
- **THEN** a confirmação da sessão anterior MUST NOT autorizar ações sensíveis na nova sessão

#### Scenario: Aprovação com quatro olhos
- **WHEN** uma ação exige dois administradores distintos
- **THEN** cada ator MUST possuir sua própria reconfirmação recente e a confirmação de um MUST NOT valer para o outro

#### Scenario: Comando ou job tenta criar aprovação humana
- **WHEN** uma CLI ou job tenta executar ação que exige reconfirmação sem aprovação HTTP persistida por usuário autenticado
- **THEN** o sistema SHALL bloquear a ação e MUST NOT fabricar `confirmed_at` ou identidade aprovadora

### Requirement: Contrato canônico da listagem global de Offices
`GET /api/v1/platform/offices` SHALL responder um objeto `data` com `offices`, `selected_office_id` e `default_office_id`. Cada resumo SHALL informar status e `selectable`; o Admin SHALL poder localizar também Offices não selecionáveis, e o seletor MUST permitir somente `selectable=true`. O frontend MUST consumir esse contrato sem fallback incompatível, e usuários sem `PLATFORM_ADMIN` MUST receber acesso negado.

#### Scenario: Listagem global válida
- **WHEN** um administrador global consulta o endpoint
- **THEN** cada Office SHALL ser retornado uma única vez com metadados sanitizados, status e `selectable`, e os identificadores corrente e padrão SHALL refletir o estado do servidor

#### Scenario: Contrato não é um array direto
- **WHEN** o composable recebe o envelope canônico
- **THEN** ele SHALL usar `data.offices` e MUST NOT tratar `data` como lista

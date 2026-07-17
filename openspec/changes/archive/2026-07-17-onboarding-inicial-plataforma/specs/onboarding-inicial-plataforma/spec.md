## ADDED Requirements

### Requirement: Onboarding fica disponível somente na instalação inicial autorizada
O sistema SHALL expor `/onboarding` e o status público somente quando `INITIAL_ONBOARDING_ENABLED` estiver ativo, existir token de deploy forte configurado, a configuração global ainda não tiver sido criada e não houver User, Office ou PlatformMembership. Em produção, a conclusão MUST exigir HTTPS, CSRF stateful, rate limit e comparação constante do token recebido somente no body.

#### Scenario: Instalação vazia e autorizada
- **WHEN** uma instalação vazia consulta o status com flag e token forte configurados
- **THEN** a API retorna somente `available=true` com `Cache-Control: no-store`

#### Scenario: Configuração desabilitada ou instalação existente
- **WHEN** a flag está OFF, o token é inválido ou a base já contém configuração, usuário, Office ou membership
- **THEN** o status não expõe o motivo e a conclusão não cria nem altera dados

#### Scenario: Requisição produtiva sem HTTPS
- **WHEN** alguém tenta concluir o onboarding de produção por HTTP
- **THEN** o sistema rejeita antes de validar ou gravar credenciais

### Requirement: Conclusão cria somente o primeiro administrador global
O onboarding SHALL solicitar visivelmente apenas nome da organização, e-mail, senha e confirmação. Em uma transação, o sistema MUST criar a configuração singleton concluída, um User ativo e verificado chamado `Administrador da plataforma` e uma PlatformMembership ativa `PLATFORM_ADMIN` com `default_office_id` nulo. O fluxo MUST NOT criar Office, assinatura, OfficeMembership ou AccountActivation.

#### Scenario: Primeira conclusão válida
- **WHEN** o operador envia os quatro campos válidos com o token correto
- **THEN** exatamente um administrador exclusivamente global e a configuração da organização são persistidos atomicamente

#### Scenario: Duas conclusões concorrentes
- **WHEN** duas requisições válidas tentam reivindicar a mesma instalação
- **THEN** somente uma conclui e a outra falha sem criar usuário ou grant parcial

#### Scenario: Falha durante a criação
- **WHEN** qualquer gravação do agregado inicial falha
- **THEN** usuário, membership e trava são revertidos e uma tentativa posterior válida continua possível

### Requirement: Onboarding fecha permanentemente e protege segredos
Após o primeiro sucesso, o sistema MUST manter o onboarding indisponível mesmo se o usuário inicial for excluído. Token e senha MUST permanecer ausentes de query string, persistência, logs, auditoria, cache, telemetria e respostas; toda resposta do fluxo MUST usar `Cache-Control: no-store`.

#### Scenario: Administrador inicial é excluído
- **WHEN** o usuário criado pelo onboarding é removido posteriormente
- **THEN** a configuração singleton permanece concluída e `/onboarding` não volta a aceitar conclusão

#### Scenario: Token entra pelo fragmento
- **WHEN** o operador abre `/onboarding#token=<segredo>`
- **THEN** a SPA remove o fragmento imediatamente, mantém o token somente em memória e o envia apenas no body da conclusão

### Requirement: Primeiro administrador entra na plataforma sem Office
Depois do commit, o sistema SHALL autenticar o primeiro administrador, regenerar a sessão e direcioná-lo a `/admin/offices/new`. Enquanto não houver Office resolvido, login, redirect pendente e navegação direta MUST direcionar o PLATFORM_ADMIN para `/admin`, ocultar superfícies tenant e mostrar uma ação para cadastrar o primeiro Office; APIs tenant continuam fail-closed. Ao cadastrar o primeiro Office, o sistema MUST preencher atomicamente o `PlatformMembership.default_office_id` ausente do ator global sem criar `OfficeMembership` ou alterar `users.selected_office_id`; o contexto SHALL passar a resolver automaticamente quando esse Office for ativado.

#### Scenario: Onboarding concluído
- **WHEN** a sessão é estabelecida após a criação do administrador
- **THEN** a SPA atualiza a identidade e abre o wizard do primeiro Office

#### Scenario: Login posterior sem Office
- **WHEN** o PLATFORM_ADMIN sem Office realiza login comum ou tenta abrir uma página tenant
- **THEN** o frontend o leva a `/admin`, exibe somente superfícies globais e não dispara resumo operacional tenant

#### Scenario: Primeiro Office é cadastrado
- **WHEN** o administrador inicial ainda possui `default_office_id` nulo e cadastra o primeiro Office pendente
- **THEN** a mesma transação vincula esse Office como padrão global, mantém `selected_office_id` nulo e não cria OfficeMembership para o administrador da plataforma

#### Scenario: Primeiro Office é ativado
- **WHEN** o Office padrão pendente conclui sua ativação e se torna ativo
- **THEN** novas requisições do PLATFORM_ADMIN resolvem o contexto privilegiado desse Office sem seleção manual

### Requirement: Nome da organização é configuração global somente leitura
O nome informado no onboarding SHALL ser persistido em `platform_settings` e disponibilizado de forma sanitizada às superfícies autenticadas da plataforma. Esta versão MUST NOT oferecer endpoint ou formulário posterior de edição e MUST NOT usar esse valor para alterar escopo tenant ou identificadores operacionais de cache/sessão.

#### Scenario: Administrador consulta sua identidade
- **WHEN** o primeiro PLATFORM_ADMIN autenticado carrega `/me` ou o hub global
- **THEN** o nome da organização aparece como metadado global e nenhum dado tenant é inferido dele

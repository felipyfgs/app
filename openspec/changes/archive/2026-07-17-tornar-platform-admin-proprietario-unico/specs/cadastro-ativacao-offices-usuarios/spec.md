## ADDED Requirements

### Requirement: Cadastro global permanece restrito ao onboarding inicial
Somente o onboarding de uma instalação estruturalmente vazia SHALL criar a primeira `PlatformMembership` `PLATFORM_ADMIN`. Rotas autenticadas da plataforma MUST NOT oferecer criação, convite, ativação ou regeneração de outro administrador global; o painel SHALL apresentar o Proprietário em uma superfície singular de consulta e atualização da identidade existente.

#### Scenario: Primeiro proprietário conclui onboarding
- **WHEN** o token inicial válido é consumido em uma base estruturalmente vazia
- **THEN** o sistema SHALL criar e autenticar o único Proprietário sem depender de convite de outro administrador

#### Scenario: Cliente antigo tenta criar administrador global
- **WHEN** um cliente chama a antiga operação de criação em `/api/v1/platform/admins`
- **THEN** a API MUST rejeitar a operação sem criar usuário, `PlatformMembership` ou ativação

#### Scenario: Proprietário abre a administração
- **WHEN** o Proprietário acessa a opção singular “Proprietário”
- **THEN** o painel SHALL exibir sua identidade existente sem tabela, botão de novo administrador ou linguagem de equipe global

#### Scenario: Identidade existente é atualizada
- **WHEN** o Proprietário com senha recente altera dados permitidos ou seu Office padrão pela rota singular
- **THEN** o sistema SHALL atualizar o mesmo usuário e vínculo sem criar outro `PLATFORM_ADMIN` ou `OfficeMembership`

## MODIFIED Requirements

### Requirement: Destinatário pendente pode ter o e-mail corrigido
Enquanto o destinatário nunca tiver sido ativado, o ator autorizado e com senha recente SHALL poder substituir seu nome e e-mail por uma ação distinta da regeneração. A plataforma SHALL corrigir o primeiro ADMIN de um Office; somente OfficeMembership ADMIN real SHALL corrigir membro do próprio Office. O sistema MUST revogar todos os segredos anteriores, registrar auditoria sanitizada, remover a conta e membership pendentes exclusivas e criar nova conta, grants e ativação para o e-mail corrigido. Depois da ativação, essa ação MUST ser negada. A identidade global inicial MUST ser corrigida pelo fluxo singular de recuperação do Proprietário e não por convite pendente.

#### Scenario: E-mail foi digitado incorretamente
- **WHEN** a plataforma corrige o primeiro ADMIN antes da ativação
- **THEN** todos os links e senhas anteriores SHALL falhar e a nova credencial SHALL ficar fixa ao novo e-mail

#### Scenario: Office já foi ativado
- **WHEN** a plataforma tenta usar a correção especial depois da ativação
- **THEN** a API SHALL negar a ação e a equipe SHALL ser alterada somente pelos fluxos normais do Office

#### Scenario: Correção global usa endpoint legado
- **WHEN** alguém tenta corrigir um suposto administrador global pendente pelas rotas plurais
- **THEN** a API MUST rejeitar a ação e orientar a manutenção ou recuperação do Proprietário existente

## REMOVED Requirements

### Requirement: Plataforma cria administradores globais sem membership
**Reason**: A instalação passa a possuir um único Proprietário `PLATFORM_ADMIN`; criar, convidar ou ativar outro administrador global viola a invariável singleton.

**Migration**: Remover `/api/v1/platform/admins`, os serviços de criação/ativação global pendente e a página plural. Clientes devem consultar ou atualizar o proprietário existente em `/api/v1/platform/owner`; perda de acesso usa o comando operacional dedicado.

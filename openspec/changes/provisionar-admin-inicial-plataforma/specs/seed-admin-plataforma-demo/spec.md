## ADDED Requirements

### Requirement: Seed demo cria administrador exclusivamente global
Em ambiente `local` ou `testing`, o seed demo SHALL garantir uma identidade estável e pronta para login com nome `Admin Plataforma Demo`, e-mail `plataforma@example.com`, usuário ativo/verificado e `PlatformMembership` ativa com papel `PLATFORM_ADMIN` e o Office `Plataforma` como padrão. O usuário MUST manter `selected_office_id` nulo, MUST NOT possuir OfficeMembership e MUST NOT possuir ativação pendente.

#### Scenario: Primeira execução do seed
- **WHEN** o Office demo existe e o seed é executado sem a identidade global reservada
- **THEN** o sistema cria exatamente um User e uma PlatformMembership com os dados demo, senha local convencional e zero vínculos tenant

#### Scenario: Conta global acessa o painel
- **WHEN** a identidade sem OfficeMembership autentica com a credencial demo
- **THEN** o sistema a reconhece como `PLATFORM_ADMIN`, disponibiliza o grupo Admin e resolve o Office demo pelo `default_office_id` global

### Requirement: Seed é idempotente e preserva credencial existente
Executar novamente o seed MUST NOT duplicar User ou PlatformMembership e MUST NOT redefinir senha, nome ou e-mail de uma fixture global compatível já existente. O seed SHALL garantir que a PlatformMembership reservada continue ativa e referencie o Office `Plataforma` ativo.

#### Scenario: Seed é executado duas vezes
- **WHEN** a fixture foi criada pela primeira execução e o seed roda novamente
- **THEN** ids e contagens permanecem iguais e nenhum novo grant é criado

#### Scenario: Senha demo foi alterada localmente
- **WHEN** o usuário compatível já possui outro hash de senha e o seed roda novamente
- **THEN** o hash existente permanece inalterado e a PlatformMembership continua válida

### Requirement: Colisões incompatíveis falham sem escalada de privilégio
Se `plataforma@example.com` já pertencer a usuário com OfficeMembership, grant incompatível ou estado que não corresponda à fixture exclusivamente global, o seed MUST falhar explicitamente e MUST NOT criar, ativar ou alterar PlatformMembership, senha ou vínculos do usuário existente.

#### Scenario: E-mail reservado pertence à equipe de um Office
- **WHEN** o seed encontra o e-mail reservado associado a uma OfficeMembership
- **THEN** a execução falha sem converter a conta em dual e sem alterar os dados existentes

#### Scenario: Office da Plataforma está ausente ou inativo
- **WHEN** o sub-seeder é executado sem o Office `plataforma` ativo
- **THEN** a execução falha com orientação acionável e não grava User ou PlatformMembership parcial

### Requirement: Fixture não pode ser provisionada fora de demo
O `PlatformAdminDemoSeeder` e o `DatabaseSeeder` MUST recusar execução fora dos ambientes `local` e `testing`, mesmo quando o sub-seeder for chamado diretamente. A fixture MUST NOT depender de `.env` de produção nem ser executada automaticamente em deploy.

#### Scenario: Sub-seeder é chamado em produção
- **WHEN** o ambiente da aplicação é `production` e alguém executa diretamente o sub-seeder
- **THEN** a execução falha antes de qualquer escrita e nenhum administrador global é criado ou alterado

### Requirement: Administrador demo não integra equipe nem limite do plano
A identidade global demo MUST permanecer ausente das listagens de equipe do Office e MUST NOT contar contra `max_users`, ainda que seu `default_office_id` aponte para o Office `Plataforma`.

#### Scenario: Admin do Office consulta a equipe
- **WHEN** o Office demo lista membros e calcula vagas depois do seed
- **THEN** `plataforma@example.com` não aparece na equipe e a quantidade de vagas é a mesma que seria sem o usuário global

### Requirement: Massa demo expõe somente duas identidades de Office
Em uma base limpa, o `DatabaseSeeder` SHALL deixar ativos somente os Offices `Plataforma` (`plataforma`) e `Contador Genérico` (`demo`). Os sentinelas fiscal e Work MUST reutilizar o Office `plataforma`, preservando isolamento em relação a `demo` sem criar Offices técnicos adicionais. Ao repetir o seed numa base anterior, os slugs `demo-sentinel` e `demo-work-sentinel` MUST ficar inativos, e o seed MUST NOT desativar Offices com outros slugs.

#### Scenario: Seed limpo conclui
- **WHEN** o `DatabaseSeeder` termina em uma base sem Offices
- **THEN** a listagem global contém exatamente `Plataforma` e `Contador Genérico` como Offices ativos

#### Scenario: Seed antigo é reconciliado
- **WHEN** existem os dois slugs sentinela legados e o `DatabaseSeeder` roda novamente
- **THEN** ambos ficam inativos e nenhum Office manual fora dessa allowlist é alterado

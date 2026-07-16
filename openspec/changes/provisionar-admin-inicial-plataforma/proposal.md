## Why

O `DatabaseSeeder` local/testing recria o Office demo e usuários `ADMIN`, `OPERATOR` e `VIEWER` do escritório, mas não cria um usuário exclusivamente `PLATFORM_ADMIN`. Após resetar a base, o acesso ao grupo `/admin/*` depende de cadastrar ou promover manualmente uma conta, impedindo um ambiente demo reproduzível para o perfil global.

## What Changes

- Adicionar uma fixture idempotente de administrador da plataforma ao seed demo local/testing.
- Criar uma identidade estável e pronta para login, distinta do `admin@example.com` do Office, com nome e e-mail demo documentados e a mesma convenção de senha local já usada pelo `DatabaseSeeder`.
- Criar `PlatformMembership` ativa com papel `PLATFORM_ADMIN` e `default_office_id` apontando para o Office demo.
- Manter a conta exclusivamente global: `selected_office_id` nulo, zero OfficeMembership, ausência na equipe do Office e nenhum consumo de vaga do plano.
- Tornar a repetição do seed convergente: não duplicar usuário ou membership e não redefinir silenciosamente a senha de uma conta demo já existente.
- Falhar de forma explícita se o e-mail reservado do admin demo já estiver ligado a perfil incompatível, evitando converter automaticamente um usuário de Office em conta dual.
- Preservar o bloqueio atual do `DatabaseSeeder` fora de `local`/`testing`; o seed MUST NOT provisionar administrador em staging ou produção.
- Non-goals: bootstrap de produção, criação de Office, ativação pendente, envio de credencial, nova API/UI, alteração de permissões, dados fiscais, feature flags, canais SEFAZ/SERPRO ou promoção de usuários reais.

## Capabilities

### New Capabilities

- `seed-admin-plataforma-demo`: Fixture local/testing, determinística e idempotente de um usuário exclusivamente `PLATFORM_ADMIN` com Office demo padrão.

### Modified Capabilities

Nenhuma.

## Impact

- **Backend demo**: novo `PlatformAdminDemoSeeder` (ou seeder equivalente) chamado pelo `DatabaseSeeder` depois de existir o Office demo.
- **Dados demo**: `User` ativo/verificado com perfil global, `PlatformMembership` ativa e Office padrão; nenhuma OfficeMembership ou `AccountActivation`.
- **Segurança**: execução continua limitada a `local`/`testing`; e-mail/senha são credenciais estritamente demo e não entram em configuração de produção.
- **Testes**: primeira execução, repetição sem duplicação/reset de senha, vínculo global correto, ausência na equipe/limite do plano, conflito de e-mail e recusa fora de ambiente permitido.
- **Compatibilidade**: usa a separação de perfis de `individualizar-perfis-plataforma-escritorio` e não substitui o fluxo real de administradores globais de `cadastrar-ativar-offices-usuarios`.

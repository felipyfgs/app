## Why

O `DatabaseSeeder` local/testing recria o Office demo e usuários `ADMIN`, `OPERATOR` e `VIEWER` do escritório, mas não cria um usuário exclusivamente `PLATFORM_ADMIN`. Após resetar a base, o acesso ao grupo `/admin/*` depende de cadastrar ou promover manualmente uma conta, impedindo um ambiente demo reproduzível para o perfil global.

## What Changes

- Adicionar uma fixture idempotente de administrador da plataforma ao seed demo local/testing.
- Criar uma identidade estável e pronta para login, distinta do `admin@example.com` do Office, com nome e e-mail demo documentados e a mesma convenção de senha local já usada pelo `DatabaseSeeder`.
- Criar `PlatformMembership` ativa com papel `PLATFORM_ADMIN` e `default_office_id` apontando para um Office próprio chamado `Plataforma`.
- Manter somente dois Offices ativos no seed limpo: `Plataforma` e `Contador Genérico`; os sentinelas fiscal e Work reutilizam o primeiro sem criar opções técnicas no seletor.
- Desativar os slugs sentinela legados ao repetir o seed e apresentar o Office ativo em uma única linha no gatilho do seletor global, com contexto visual discreto no rodapé e o papel técnico preservado apenas para acessibilidade.
- Manter a conta exclusivamente global: `selected_office_id` nulo, zero OfficeMembership, ausência na equipe do Office e nenhum consumo de vaga do plano.
- Tornar a repetição do seed convergente: não duplicar usuário ou membership e não redefinir silenciosamente a senha de uma conta demo já existente.
- Falhar de forma explícita se o e-mail reservado do admin demo já estiver ligado a perfil incompatível, evitando converter automaticamente um usuário de Office em conta dual.
- Preservar o bloqueio atual do `DatabaseSeeder` fora de `local`/`testing`; o seed MUST NOT provisionar administrador em staging ou produção.
- Non-goals: bootstrap de produção, ativação pendente, envio de credencial, nova API, alteração de permissões, feature flags, canais SEFAZ/SERPRO ou promoção de usuários reais.

## Capabilities

### New Capabilities

- `seed-admin-plataforma-demo`: Fixture local/testing, determinística e idempotente de um usuário exclusivamente `PLATFORM_ADMIN` com Office demo padrão.

### Modified Capabilities

- `perfis-plataforma-escritorio`: identidade compacta segue o gatilho de uma linha do `TeamsMenu`, com selo visual discreto da Plataforma e identificação semântica do papel global.

## Impact

- **Backend demo**: `PlatformAdminDemoSeeder` chamado pelo `DatabaseSeeder` depois de existirem os Offices `Plataforma` e `Contador Genérico`.
- **Dados demo**: `User` ativo/verificado com perfil global, `PlatformMembership` ativa e Office padrão; nenhuma OfficeMembership ou `AccountActivation`.
- **Segurança**: execução continua limitada a `local`/`testing`; e-mail/senha são credenciais estritamente demo e não entram em configuração de produção.
- **Testes**: primeira execução, repetição sem duplicação/reset de senha, vínculo global correto, exatamente dois Offices demo ativos, ausência na equipe/limite do plano, conflito de e-mail, recusa fora de ambiente permitido e smoke Playwright responsivo.
- **Compatibilidade**: usa a separação de perfis de `individualizar-perfis-plataforma-escritorio` e não substitui o fluxo real de administradores globais de `cadastrar-ativar-offices-usuarios`.

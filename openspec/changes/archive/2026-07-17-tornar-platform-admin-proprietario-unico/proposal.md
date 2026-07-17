## Why

Uma instalação não precisa de uma equipe de administradores globais: ela deve ter um único proprietário responsável pela plataforma, enquanto continua aceitando vários usuários e vários Offices. Limitar `PLATFORM_ADMIN` a uma única identidade reduz ambiguidade de autoridade, simplifica o painel e impede cadastros globais concorrentes ou acidentais.

## What Changes

- **BREAKING**: cada instalação/banco passa a admitir no máximo uma `PlatformMembership` com papel `PLATFORM_ADMIN`; depois do onboarding inicial, essa identidade é o **Proprietário da instalação**.
- Manter múltiplos usuários e múltiplos Offices, com papéis `ADMIN`, `OPERATOR` e `VIEWER` próprios de cada Office; o papel global único continua sem conceder acesso fiscal implícito.
- Fazer onboarding inicial, bootstrap e seeds criarem somente o proprietário único e impedirem reexecução ou concorrência que gere um segundo vínculo global.
- Remover o cadastro e a gestão plural de “administradores globais” da API e do painel, substituindo-os por uma superfície singular de consulta/manutenção do proprietário.
- Disponibilizar recuperação ou transferência operacional auditada do proprietário sem permitir dois `PLATFORM_ADMIN` simultâneos e revogando sessões do titular substituído.
- Detectar instalações legadas com mais de um `PLATFORM_ADMIN` antes de aplicar a restrição estrutural, exigindo consolidação explícita em vez de escolher ou excluir alguém silenciosamente.
- Coordenar a entrega com a change `adaptar-aprovacoes-serpro-proprietario-unico`, pois os fluxos produtivos que hoje exigem dois `PLATFORM_ADMIN` precisam ser adaptados antes da ativação da unicidade.
- Non-goals: alterar isolamento por Office, aceitar `office_id` de endpoints tenant-scoped, ligar feature flags/canais fiscais, executar smoke SERPRO, tratar tickets externos ou decisões jurídicas/LGPD.

## Capabilities

### New Capabilities

Nenhuma.

### Modified Capabilities

- `perfis-plataforma-escritorio`: torna `PLATFORM_ADMIN` um proprietário singleton por instalação e define recuperação/transferência sem duplicidade.
- `cadastro-ativacao-offices-usuarios`: remove criação, listagem e ativação plural de administradores globais e mantém somente o onboarding do proprietário inicial.

## Impact

- Backend Laravel: schema de `platform_memberships`, serviços de onboarding/bootstrap/seeds, rotas e controller `/api/v1/platform/admins`, validações transacionais, sessões e auditoria.
- Frontend Nuxt: navegação Admin, página plural de administradores, composable da API e nova apresentação singular do proprietário baseada no arquétipo do painel.
- Dados existentes: preflight/consolidação obrigatória quando houver mais de um vínculo global; nenhuma remoção ou promoção silenciosa.
- Segurança: ações globais continuam exigindo senha recente; `PLATFORM_ADMIN` continua separado de `OfficeMembership` e não amplia autorização fiscal tenant-scoped.
- Entrega: esta change e `adaptar-aprovacoes-serpro-proprietario-unico` devem ser aplicadas e verificadas de forma coordenada.

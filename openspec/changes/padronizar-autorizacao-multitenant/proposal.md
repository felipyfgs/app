## Por quê

O controle de acesso atual combina o papel global `PLATFORM_ADMIN` com papéis fixos de escritório (`ADMIN`, `OPERATOR` e `VIEWER`), o que não expressa com clareza um SaaS multi-tenant nem permite perfis de permissão administrados por cada tenant. A migração precisa estabelecer três papéis canônicos, permitir que o administrador inicial opere sua própria carteira fiscal e preservar isolamento, auditoria e atribuição de consumo SERPRO durante a transição.

## O que muda

- **BREAKING**: substituir o contrato público de papéis por exatamente três valores canônicos em `snake_case`: `platform_admin` no plano global e `tenant_admin`/`tenant_user` em cada membership de tenant.
- **BREAKING**: retirar `ADMIN`, `OPERATOR` e `VIEWER` do contrato final; migrar `ADMIN` para `tenant_admin` e migrar `OPERATOR`/`VIEWER` para `tenant_user` com perfis de permissão de sistema equivalentes, sem ampliar nem reduzir capacidades existentes.
- Introduzir catálogo estável de permissões e perfis de permissão isolados por tenant, criados e mantidos por `tenant_admin`; `tenant_user` passa a receber suas capacidades do perfil atribuído.
- Permitir que `tenant_user` crie outros `tenant_user` somente quando possuir a permissão correspondente e sem conseguir elevar papel, atribuir `platform_admin`, administrar perfis ou delegar capacidades superiores às próprias.
- Tornar `platform_admin` múltiplo, removendo o conceito de proprietário singleton e impedindo a desativação, remoção ou rebaixamento do último administrador ativo da plataforma.
- Separar autorização global de autorização tenant: `platform_admin` administra tenants e administradores globalmente, mas só recebe capacidades efetivas de `tenant_admin` depois de selecionar explicitamente um tenant ativo; nenhum `Gate::before` global pode aprovar toda habilidade.
- Provisionar transacionalmente um tenant principal e uma membership `tenant_admin` para o primeiro `platform_admin`; esse tenant contém sua carteira de clientes diretos, credenciais, consultas e consumo SERPRO e abre como contexto operacional padrão sem etapa manual de criação.
- Permitir que `platform_admin` crie, edite, suspenda, reative e desprovisione outros tenants. “Excluir” será encerramento lógico auditado e com retenção, nunca cascade ou remoção física de dados fiscais nesta change.
- Aplicar migração expand–backfill–cutover–contract ao banco, casts, policies, serviços, APIs, frontend, seeders, comandos e testes, com leitura compatível durante o rollout, validação de paridade e rollback antes da remoção dos campos e literais legados.
- Atualizar as changes ativas de PGDAS-D, PGMEI e DCTFWeb para autorizar por capacidades semânticas em vez de literais `ADMIN`/`OPERATOR`/`VIEWER`, evitando contratos OpenSpec contraditórios.

Não são objetivos desta change: renomear `Office`, `CurrentOffice`, `office_user`, `offices` ou todas as rotas existentes por estética; habilitar SERPRO live, canais outbound ou mutações fiscais; ignorar assinatura, consentimento, feature flags ou kill switches; definir prazo jurídico de retenção; purgar fisicamente tenants ou evidências; criar permissões arbitrárias fora do catálogo controlado.

## Capacidades

### Novas capacidades

- `tenant-access-governance`: papéis canônicos, memberships globais e tenant, perfis/permissões, delegação segura, múltiplos `platform_admin`, contexto explícito e migração compatível do RBAC legado.
- `tenant-lifecycle`: provisionamento do tenant principal, carteira fiscal própria, criação e administração de tenants, suspensão/reativação/desprovisionamento e comportamento fail-closed de sessões, jobs e integrações.

### Capacidades modificadas

Nenhuma. A change preserva os requisitos de `schema-conventions`, inclusive `office_user`, `office_id`, `CurrentOffice`, escopo fail-closed e rejeição de `office_id` fornecido pelo cliente.

## Impacto

- Backend Laravel: novos enums/casts canônicos, models e serviços de perfis/permissões, refatoração de `PlatformOwnerService`, `OfficeTeamService`, `CurrentOffice`, gates, policies, middlewares, route model binding, auditoria, ativações, comandos e seeders.
- Banco PostgreSQL/SQLite: schema aditivo para papéis canônicos e perfis, backfill idempotente por tenant, remoção controlada do índice singleton, novos estados de ciclo de vida e fase posterior de contração dos campos legados.
- Contratos HTTP: `/api/v1/me`, memberships, equipe, seletor global, administração de tenants e administradores passam a expor papel tenant, papel global, permissões efetivas, perfil e modo de acesso sem aceitar `office_id` como autoridade.
- Frontend Nuxt: tipos, utilitários de permissão, middleware, navegação, seletor de tenant, equipe, perfis, administração da plataforma, rótulos e testes deixam de depender de papéis legados.
- Operação fiscal/SERPRO: clientes, credenciais, autorizações, bilhetagem e auditoria continuam atribuídos ao tenant selecionado; o papel global não cria carteira nem credencial fora de um tenant e não vence guards operacionais.
- Rollout: exige inventário prévio, backup, deploy em ondas, métricas de divergência entre autorização legada e canônica, validação de workers Horizon/scheduler e rollback documentado antes da contração.
- OpenSpec: referências a `ADMIN`/`OPERATOR`/`VIEWER` nas changes ativas de monitoramento deverão ser migradas para permissões semânticas antes de seu fechamento.

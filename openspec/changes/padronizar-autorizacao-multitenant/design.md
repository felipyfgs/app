## Contexto

O produto já possui boa separação estrutural entre identidade global e tenancy, mas a autorização tenant permanece presa a três papéis fixos:

- `PlatformRole::PlatformAdmin` persiste `PLATFORM_ADMIN` em `platform_memberships`.
- `OfficeRole` persiste `ADMIN`, `OPERATOR` e `VIEWER` em `office_user` e concentra métodos `can*` usados por policies, controllers, services e pelo frontend.
- `PlatformOwnerService`, o índice parcial `platform_memberships_one_platform_admin`, `/platform/owner`, comandos e testes impõem um proprietário singleton.
- `InitialOnboardingService` cria apenas o administrador global e redireciona para criação manual de Office; `BootstrapOfficeCommand` já cria quase toda a conta dual desejada, inclusive Office, membership ADMIN, default e assinatura.
- `CurrentOffice`, `platform_selected_office_id`, `PlatformPrivilegedAuditor` e os scopes `BelongsToOffice` já fornecem a base segura para contexto explícito e fail-closed. Essa base deve ser preservada.
- Não existe tabela, model, API ou tela de perfil/permissão tenant, nem dependência como `spatie/laravel-permission`.
- O frontend usa `OfficeRole` e matrizes locais em tipos, navegação, middleware, equipe, monitoramento, Work, SERPRO e testes. Uma troca imediata do campo `role` quebraria SPA antiga durante deploy.
- Changes ativas de PGDAS-D, PGMEI e DCTFWeb ainda publicam `ADMIN`/`OPERATOR`/`VIEWER` como contrato.

Stakeholders: operador da instalação, administradores da plataforma, administradores e usuários de tenant, clientes fiscais, segurança/privacidade, operação SERPRO, suporte e agentes que manterão o código.

Restrições não negociáveis:

- `CurrentOffice` continua sendo a autoridade de tenant; `office_id` do cliente HTTP nunca substitui contexto.
- `Office`, `offices`, `office_id`, `office_user` e `CurrentOffice` permanecem nomes técnicos nesta change.
- Flags e kill switches começam OFF e vencem papéis/permissões.
- Nenhum fluxo expõe PFX, tokens, segredos, XML completo ou payload fiscal sensível.
- Dados fiscais não são apagados para implementar “exclusão” de tenant.
- PostgreSQL de produção e SQLite de testes precisam permanecer suportados.

## Objetivos / Não objetivos

**Objetivos:**

- Publicar exatamente três papéis canônicos: `platform_admin`, `tenant_admin` e `tenant_user`.
- Manter papel global e papel tenant como autoridades ortogonais e permitir conta dual.
- Substituir a matriz fixa Operator/Viewer por perfis de permissão isolados por tenant, sem regressão de acesso na migração.
- Centralizar autorização tenant em chaves semânticas e remover decisões espalhadas por comparação de papel.
- Permitir vários `platform_admin` e proteger o último ativo sob concorrência.
- Dar a `platform_admin` paridade de `tenant_admin` somente em contexto privilegiado explícito, auditado e ativo.
- Criar tenant principal e membership real no primeiro onboarding para suportar carteira própria e SERPRO tenant-scoped.
- Completar ciclo de vida de tenant com suspensão, reativação e desprovisionamento lógico.
- Realizar rollout expand–backfill–shadow–cutover–contract com métricas e rollback antes da contração.
- Entregar contratos e tarefas determinísticos o suficiente para implementação por agente sem decisões implícitas.

**Não objetivos:**

- Renomear o agregado `Office`, tabelas, FKs, `CurrentOffice` ou todas as rotas para `Tenant`.
- Criar um quarto papel `platform_owner`, `root` ou `superadmin`.
- Instalar pacote externo de RBAC.
- Permitir chaves de permissão arbitrárias criadas pelo tenant.
- Habilitar SERPRO live, mutações fiscais, canais outbound ou feature flags.
- Definir política jurídica ou prazo de retenção e executar purge físico.
- Converter todos os identificadores históricos `OWNER_*` de protocolos SERPRO no mesmo deploy.
- Confiar na visibilidade de botões do frontend como autorização.

## Decisões

### D1 — Vocabulário de produto canônico, persistência física compatível

| Conceito | Termo canônico | Nome técnico preservado |
|---|---|---|
| espaço isolado | tenant | `Office`, `offices`, `office_id`, `CurrentOffice` |
| vínculo tenant | membership tenant | `OfficeMembership`, `office_user` |
| papel global | `platform_admin` | `PlatformMembership` |
| papel administrativo tenant | `tenant_admin` | nova representação em `OfficeMembership` |
| usuário dirigido por perfil | `tenant_user` | nova representação em `OfficeMembership` |
| tenant da carteira direta | tenant principal | `platform_settings.primary_office_id` |

Os enums terão nomes PHP explícitos e valores públicos lowercase:

```php
enum PlatformRole: string
{
    case PlatformAdmin = 'platform_admin';
}

enum TenantRole: string
{
    case TenantAdmin = 'tenant_admin';
    case TenantUser = 'tenant_user';
}
```

`OfficeRole` será adaptador legado apenas durante a transição e será removido na contração. Não haverá `users.role`, pois a mesma identidade pode ter autoridade global e papéis diferentes em tenants diferentes.

**Alternativa rejeitada:** renomear todo `Office` para `Tenant` no mesmo esforço. Isso aumentaria drasticamente o risco em centenas de FKs, scopes, APIs e jobs sem melhorar a segurança do RBAC.

### D2 — Modelo de autoridade ortogonal

```text
User
├── PlatformMembership(platform_role=platform_admin)?
└── OfficeMembership(office_id, tenant_role, permission_profile_id?) × N

CurrentOffice
├── access_mode=membership
│   └── usa a OfficeMembership real
└── access_mode=platform_privileged
    └── usa platform_admin + tenant selecionado; preserva ator e membership real
```

Invariantes:

1. `platform_admin` não é gravado em `office_user`.
2. `tenant_admin` e `tenant_user` não são gravados em `platform_memberships`.
3. `tenant_admin` possui baseline tenant completo sem perfil.
4. `tenant_user` ativo exige perfil ativo do mesmo tenant.
5. Conta dual mantém os dois vínculos; o `access_mode` decide qual autoridade tenant está em uso.
6. Ações globais não exigem `CurrentOffice`; ações tenant sempre exigem.

**Alternativa rejeitada:** um único `users.role` com herança. Ele não representa múltiplas memberships, facilita vazamento cross-tenant e mistura plano global com plano fiscal.

### D3 — Schema aditivo e constraints

Criar migrations forward-only; migrations antigas não serão editadas.

#### `tenant_permission_profiles`

| Coluna | Regra |
|---|---|
| `id` | bigint PK |
| `office_id` | FK obrigatória para `offices`; tenant owner |
| `key` | string estável normalizada, única por `office_id` |
| `name` | rótulo único por tenant, case-insensitive na validação |
| `description` | nullable, sem dado sensível |
| `is_system` | protege perfis de migração contra edição destrutiva |
| `is_active` | desativação explícita; não usar `SoftDeletes` |
| `authorization_version` | inteiro monotônico para invalidação de cache |
| timestamps | auditoria de linha |

#### `tenant_permission_profile_permissions`

- `permission_profile_id` com cascade somente quando o perfil ainda não possui memberships.
- `permission_key` string validada contra `TenantPermission`/catálogo definido pelo código.
- unique (`permission_profile_id`, `permission_key`).
- Sem `office_id` redundante; services sempre carregam o profile scoped por `CurrentOffice`.

#### Expansão de tabelas existentes

- `office_user.tenant_role` nullable durante expand; valores finais `tenant_admin|tenant_user`.
- `office_user.permission_profile_id` nullable com FK para `tenant_permission_profiles`.
- `office_user.authorization_version` default 1.
- `platform_memberships.platform_role` nullable durante expand; valor final `platform_admin`.
- `platform_settings.primary_office_id` nullable durante reconciliação, FK restrict/nullOnDelete nunca exercida por hard delete.
- `offices.lifecycle_status` passa a aceitar `PENDING_ACTIVATION|ACTIVE|SUSPENDED|DEPROVISIONED`.
- `offices.is_active` continua como compatibilidade derivada: somente `ACTIVE` é operacional; novos fluxos consultam o enum de lifecycle.

As colunas legadas `office_user.role` e `platform_memberships.role` permanecem até a fase contract. Durante dual-write:

| Estado canônico | Sombra legada segura |
|---|---|
| `tenant_admin` | `ADMIN` |
| `tenant_user` + perfil sistema Operador | `OPERATOR` |
| `tenant_user` + perfil sistema Visualizador | `VIEWER` |
| `tenant_user` + perfil customizado | `VIEWER` conservador |
| `platform_admin` | `PLATFORM_ADMIN` |

O alias conservador de perfil customizado evita elevação se houver rollback, mas customização só será habilitada depois que o frontend novo estiver ativo.

Integridade cross-table (`profile.office_id == membership.office_id`) será imposta no service transacional e coberta por teste de schema/domínio; quando portável, adicionar unique composto (`id`, `office_id`) e FK composta. Falha de portabilidade no SQLite não autoriza remover o guard de domínio.

**Alternativa rejeitada:** alterar imediatamente os valores da coluna `role`. Código antigo faria cast inválido e poderia derrubar requests/workers durante deploy.

### D4 — Catálogo de permissões definido pelo código

Criar `TenantPermission` e metadados associados (`label`, `module`, `risk`, `delegable`). O tenant escolhe chaves existentes; não cria chaves. Isso mantém policies versionáveis e impede permissão textual que nenhum código entende.

Chaves mínimas para o primeiro corte:

| Chave | Origem/comportamento | Operador legado | Visualizador legado | Delegável |
|---|---|---:|---:|---:|
| `tenant.dashboard.view` | entrada e leitura base | sim | sim | sim |
| `tenant.settings.view` | leitura de configuração | sim | sim | sim |
| `tenant.settings.manage` | alteração de configuração | não | não | sim |
| `tenant.users.view` | listar equipe | não | não | sim |
| `tenant.users.create` | criar somente `tenant_user` quando delegado | não | não | sim |
| `tenant.users.manage` | editar/desativar usuários comuns | não | não | sim |
| `tenant.modules.manage` | módulos do tenant | não | não | sim |
| `tenant.permission_profiles.manage` | perfis e atribuições administrativas | não | não | **não**; `tenant_admin` implícito |
| `tenant.roles.assign_admin` | criar/promover `tenant_admin` | não | não | **não**; `tenant_admin` implícito |
| `clients.view` | leitura de empresas/clientes | sim | sim | sim |
| `clients.manage` | `OfficeRole::canManageClients` | sim | não | sim |
| `credentials.status.view` | status sem material secreto | sim | sim | sim |
| `credentials.manage` | `canManageCredentials` | não | não | sim |
| `fiscal.documents.view` | documentos e detalhes permitidos | sim | sim | sim |
| `fiscal.monitoring.view` | monitores e históricos | sim | sim | sim |
| `fiscal.sync.trigger` | `canTriggerSync` | sim | não | sim |
| `fiscal.nfe.manifest` | `canManifestNfe` | sim | não | sim |
| `documents.import` | `canImportDocuments` | sim | não | sim |
| `exports.create` | `canExport` | sim | não | sim |
| `filters.share` | `canShareListFilters` | sim | não | sim |
| `fiscal.mutations.execute` | `canMutateFiscal` | não | não | sim, mas guard de alto risco permanece |
| `operations.view` | inbox/summary operacional | sim | sim | sim |
| `operations.triage` | triagem operacional | sim | não | sim |
| `work.view` | `canViewWork` | sim | sim | sim |
| `work.catalog.manage` | `canManageWorkCatalog` | não | não | sim |
| `work.processes.create` | `canCreateWorkProcesses` | sim | não | sim |
| `work.tasks.execute` | `canExecuteWorkTasks` | sim | não | sim |
| `work.administer` | `canAdministerWork` | não | não | sim |
| `work.evidence.download` | `canDownloadWorkEvidence` | sim | não | sim |
| `work.exports.create` | `canExportWork` | sim | não | sim |

Antes de congelar o enum, o implementador deve inventariar cada comparação de `OfficeRole` e cada helper frontend e mapear para uma chave da tabela ou adicionar chave semanticamente específica. Não reutilizar uma chave apenas porque dois endpoints hoje aceitam os mesmos papéis.

Criar por tenant dois perfis de sistema idempotentes:

- `legacy-operator`: conjunto exato das colunas “Operador legado”.
- `legacy-viewer`: conjunto exato das colunas “Visualizador legado”.

Perfis de sistema são imutáveis e não removíveis; `tenant_admin` pode cloná-los e editar a cópia.

**Alternativa rejeitada:** persistir permissões arbitrárias ou instalar Spatie. O primeiro dificulta auditoria; o segundo adiciona modelo global que precisaria de adaptação tenant e migração adicional sem benefício necessário.

### D5 — Um resolvedor central de autorização tenant

Criar um serviço único, por exemplo `TenantAuthorization`, usado por policies e guards:

```php
public function allows(User $actor, TenantPermission $permission, mixed $target = null): bool
{
    if (! $actor->is_active) return false;

    $office = $this->currentOffice->resolve($actor);
    if (! $office?->lifecycle_status->isOperational()) return false;
    if ($target !== null && ! $this->belongsToCurrentOffice($target, $office)) return false;

    if ($this->currentOffice->isPlatformPrivileged()) {
        return $actor->isPlatformAdmin();
    }

    $membership = $this->currentOffice->realMembership();
    if (! $membership?->is_active) return false;
    if ($membership->tenant_role === TenantRole::TenantAdmin) return true;

    return $membership->tenant_role === TenantRole::TenantUser
        && $membership->permissionProfile?->is_active
        && $membership->permissionProfile->has($permission);
}
```

O pseudocódigo resolve apenas RBAC. Depois dele, services continuam avaliando assinatura, consentimento, reconfirmação, feature flags, allowlist, limites, kill switch e política de mutação. `platform.*` permanece em gates/policies globais separados.

Refatoração deve ocorrer da borda para o núcleo:

1. Criar resolver e testes sem trocar consumidores.
2. Adaptar os métodos públicos de `OfficeRole`/helpers frontend para consultar chaves em modo compatível.
3. Migrar policies.
4. Migrar controllers e services com comparações diretas.
5. Migrar frontend para `effective_permissions`.
6. Remover adaptadores legados somente após gate de busca/arquitetura zerar referências não allowlisted.

Não instalar `Gate::before` irrestrito. Um gate global `platform-admin` pode continuar existindo para o plano global, mas nunca substitui `CurrentOffice` nas policies tenant.

### D6 — Delegação e invariantes de administração

`tenant_admin`:

- cria `tenant_admin` e `tenant_user` no tenant atual;
- cria/clona/edita/desativa perfis customizados;
- atribui qualquer perfil ativo do próprio tenant;
- não cria `platform_admin` nem altera lifecycle global.

`tenant_user` com `tenant.users.create`:

- cria somente `tenant_user`;
- atribui somente perfil do tenant atual;
- o conjunto delegável do perfil-alvo deve ser subconjunto do ator;
- não acessa CRUD de perfis;
- não atribui `tenant.roles.assign_admin`, `tenant.permission_profiles.manage` nem qualquer `platform.*`.

Comparação de subconjunto usa chaves ativas e delegáveis, nunca dados enviados pelo browser. Mudança de perfil/papel e desativação incrementam `authorization_version`, invalidam cache e revogam sessões quando houver elevação/redução sensível.

Preservar a proteção existente do último administrador local, agora sobre `tenant_role=tenant_admin`, com `SELECT ... FOR UPDATE`/transação. O desprovisionamento global é a única operação que pode encerrar todas as memberships como parte do lifecycle.

### D7 — Múltiplos platform admins sem quarto papel

Substituir `PlatformOwnerService` por `PlatformAdminService` plural. Não introduzir `platform_owner`.

Operações:

- listar e mostrar metadados sanitizados;
- convidar/criar com `ActivationPurpose::PlatformAdmin` adaptado ao valor canônico;
- atualizar identidade permitida;
- ativar, reativar e desativar;
- regenerar ativação;
- recuperação break-glass por comando explícito;
- impedir remoção/desativação do último ativo.

“Ativo” significa simultaneamente `users.is_active=true` e `platform_memberships.is_active=true`. A verificação do último usa transação, lock das memberships canônicas e recontagem após lock. O índice parcial singleton será removido; unique por (`user_id`, papel) permanece.

Endpoints novos, mantendo `/api/v1/platform/*`:

```text
GET    /api/v1/platform/admins
POST   /api/v1/platform/admins
GET    /api/v1/platform/admins/{admin}
PATCH  /api/v1/platform/admins/{admin}
POST   /api/v1/platform/admins/{admin}/deactivate
POST   /api/v1/platform/admins/{admin}/reactivate
POST   /api/v1/platform/admins/{admin}/activation/regenerate
```

`/platform/owner` vira alias read-only temporário do primeiro admin apenas durante compatibilidade e será removido. Comandos `platform-owner:*` serão deprecados; recuperação passa a receber usuário-alvo e nunca consolida/remover outros admins.

Protocolos históricos SERPRO com nomes `OWNER_CONFIRMATION` e colunas `owner_approver_user_id` permanecem legíveis. A semântica nova é “aprovação por `platform_admin` ativo”; novos textos de UI não dizem Proprietário. Aprovação dual exige dois usuários distintos, o que passa a ser realizável.

### D8 — Semântica de CurrentOffice e tenant principal

Ordem canônica de resolução:

1. Seleção privilegiada explícita válida em `platform_selected_office_id` → `platform_privileged`.
2. Seleção comum válida em `current_office_id`/`users.selected_office_id` → `membership`.
3. Primeira membership ativa determinística → `membership`.
4. Sem contexto → `office_context_required`.

`platform_memberships.default_office_id` é preferência de navegação, não autorização e não deve abrir silenciosamente tenant sem membership. Para o primeiro administrador, o tenant principal também está em `users.selected_office_id` e possui membership real `tenant_admin`, portanto abre em modo membership mesmo com `platform_privileged_context` OFF.

O seletor global continua separado, exige flag `platform_privileged_context` explicitamente habilitada e só lista tenants `ACTIVE`. Selecionar, trocar ou limpar incrementa epoch de sessão, limpa caches tenant, aborta/descarta respostas anteriores e gera auditoria. Suspensão/desprovisionamento invalida seleções imediatamente.

Conta dual `tenant_user` usa seu perfil em modo membership; pode escolher explicitamente “Acessar como administrador da plataforma” para entrar em modo privilegiado e então recebe paridade `tenant_admin` auditada. Isso preserva least privilege sem negar a herança aprovada.

### D9 — Serviço único de bootstrap inicial

Extrair a lógica duplicada de `InitialOnboardingService` e `BootstrapOfficeCommand` para `PlatformBootstrapService` transacional. Entrada mínima: nome da organização/tenant, nome/e-mail/senha do administrador e token/autoridade do canal.

Na mesma transação:

1. reivindicar `PlatformSetting` singleton;
2. criar usuário inicial ativo;
3. criar `PlatformMembership(platform_admin)`;
4. criar Office principal `ACTIVE` usando o nome da organização e slug único;
5. criar `OfficeInstitutionalProfile` parcial, sem inventar CNPJ;
6. criar assinatura inicial pelo mesmo factory/service usado no bootstrap atual;
7. criar `OfficeMembership(tenant_admin)` real;
8. definir `users.selected_office_id`;
9. definir `platform_memberships.default_office_id`;
10. definir `platform_settings.primary_office_id`;
11. concluir onboarding e emitir auditoria após commit.

Nenhuma flag fiscal, capability SERPRO, allowlist ou outbound é habilitada. O controller autentica, retorna resumo do tenant/contexto e redireciona para `/` (carteira), não `/admin/offices/new`.

Criar outro `platform_admin` não executa esse bootstrap e não cria outro tenant. Se precisar de carteira própria separada, o fluxo explícito é criar um tenant e, opcionalmente, membership tenant.

Instalações existentes não recebem membership fiscal com base apenas em `default_office_id`, pois o backfill antigo escolheu Office por ordem e não prova propriedade. A reconciliação é explícita no preflight descrito em D13.

### D10 — Lifecycle de tenant separado da assinatura

Máquina de estados:

```text
PENDING_ACTIVATION ──activate──> ACTIVE ──suspend──> SUSPENDED
       │                                      │           │
       └──deprovision─────────────────────────┘           ├──reactivate──> ACTIVE
                                                          └──deprovision──> DEPROVISIONED
```

Regras:

| Estado | Selecionável | Requests tenant | Jobs/externo | Gestão global |
|---|---:|---:|---:|---:|
| `PENDING_ACTIVATION` | não | ativação apenas | não | sim |
| `ACTIVE` | sim | conforme RBAC/guards | conforme flags/assinatura | sim |
| `SUSPENDED` | não | não | não | metadados/reativação |
| `DEPROVISIONED` | não | não | não | metadados/auditoria somente |

Suspensão:

- grava lifecycle e `is_active=false` atomicamente;
- revoga/limpa contextos e sessões tenant;
- impede enqueue novo;
- jobs existentes revalidam lifecycle imediatamente antes de segredo/transporte;
- não altera automaticamente assinatura.

Reativação:

- grava `ACTIVE`/`is_active=true` com motivo e auditoria;
- preserva memberships e dados;
- não reenfileira efeitos perdidos;
- assinatura/flags continuam podendo bloquear.

Desprovisionamento:

- exige tenant previamente suspenso, confirmação sensível e motivo;
- bloqueia tenant principal até transferência explícita;
- é terminal para aplicação comum;
- preserva IDs, slugs, clients, documentos, evidências, audit logs e referências do vault;
- não usa `delete()`, `SoftDeletes` nem cascade.

APIs globais podem continuar sob `/platform/offices`, pois o rename físico não faz parte do escopo:

```text
PATCH /api/v1/platform/offices/{office}
POST  /api/v1/platform/offices/{office}/suspend
POST  /api/v1/platform/offices/{office}/reactivate
POST  /api/v1/platform/offices/{office}/deprovision
POST  /api/v1/platform/offices/{office}/make-primary
```

`TenantAdminController` continua responsável por assinatura; não reutilizar `SubscriptionStatus::Suspended/Canceled` como lifecycle.

### D11 — Contratos HTTP aditivos e cutover do frontend

Resposta canônica mínima de `/api/v1/me`:

```json
{
  "platform_role": "platform_admin",
  "tenant_role": "tenant_admin",
  "real_tenant_role": "tenant_admin",
  "effective_permissions": ["clients.view", "clients.manage"],
  "permission_profile": null,
  "access_mode": "membership",
  "has_real_membership": true,
  "current_office": { "id": 1, "name": "...", "slug": "..." },
  "context_status": "ok"
}
```

Para `tenant_admin` e `platform_admin` privilegiado, `effective_permissions` retorna todas as chaves tenant ativas em ordem estável, não `*`. Sem tenant selecionado, retorna lista vazia.

Aliases somente-leitura temporários:

- `is_platform_admin` derivado de `platform_role`;
- `role` e `real_office_role` derivados pelo mapeamento legado conservador;
- `office` como alias de `current_office`.

Payload de membership novo:

```json
{
  "tenant_role": "tenant_user",
  "permission_profile_id": 10
}
```

Regras de validação:

- `tenant_admin` exige `permission_profile_id=null`.
- `tenant_user` exige perfil ativo do tenant atual.
- `platform_admin` nunca é aceito pelo endpoint de equipe.
- Escrever `role` legado só é aceito no adaptador durante a fase expand, nunca pelo domínio novo.

Perfis/permissões:

```text
GET    /api/v1/office/permissions
GET    /api/v1/office/permission-profiles
POST   /api/v1/office/permission-profiles
GET    /api/v1/office/permission-profiles/{profile}
PATCH  /api/v1/office/permission-profiles/{profile}
DELETE /api/v1/office/permission-profiles/{profile}
```

O backend retorna options atribuíveis calculadas para o ator; o browser não decide subconjunto nem escopo.

### D12 — Frontend por capacidades, não por papel fixo

Adicionar tipos `PlatformRole`, `TenantRole`, `TenantPermissionKey`, `PermissionProfileSummary` e os campos canônicos de identidade. Centralizar:

```ts
hasPermission(user, key)
isPlatformAdmin(user)
isTenantAdmin(user)
hasSelectedTenant(user)
```

Os helpers públicos existentes (`canManageClients`, `canTriggerSync`, `canCreateExport`, Work etc.) permanecem inicialmente, mas delegam para `hasPermission`; assim consumidores podem ser migrados em lotes sem reimplementar regras.

Superfícies obrigatórias:

- onboarding redireciona à carteira do tenant principal;
- seletor diferencia modo membership de “Acessar como administrador da plataforma” e mostra banner persistente no modo privilegiado;
- Conta ganha “Perfis e permissões”; equipe usa `tenant_role` + perfil;
- Admin ganha “Administradores da plataforma” e lifecycle completo de tenants;
- middleware `/conta/*` deixa de bloquear o grupo inteiro e avalia capacidade por rota;
- navegação, quick actions e tabelas usam `effective_permissions`;
- rótulo de `tenant_user` mostra nome do perfil, não “Operador/Visualizador”, exceto no histórico de migração;
- troca/suspensão incrementa `sessionEpoch`, limpa stores/paginação/detalhes e descarta requests em voo.

A implementação frontend deve usar `panel-ui` e `ui-archetype` para manter o padrão do dashboard. O backend continua sendo autoridade; cada teste de visibilidade deve ter teste Feature correspondente de negação.

### D13 — Backfill idempotente e reconciliação do tenant principal

Criar comando de preflight/dry-run, por exemplo:

```text
php artisan app:multitenant-rbac:migrate --dry-run
php artisan app:multitenant-rbac:migrate --apply --primary-office=<id>
```

O dry-run reporta somente IDs/contagens sanitizadas:

- valores de papel e quantidades;
- memberships órfãs/inativas/inconsistentes;
- usuários/tenants sem administrador ativo;
- quantidade de `platform_admin` efetivos;
- candidatos a tenant principal;
- divergência de capacidades legado × canônico;
- jobs tenant pendentes e sessões que serão revogadas.

Escolha do principal em instalação existente:

| Situação | Ação |
|---|---|
| zero tenants | exigir nome/slug explícitos e criar via `PlatformBootstrapService` de reconciliação |
| exatamente um tenant ativo | sugerir seu ID, mas exigir confirmação `--primary-office` |
| mais de um tenant | exigir `--primary-office`; nunca escolher menor ID/default silenciosamente |
| nenhum `platform_admin` ativo | bloquear e executar recuperação break-glass antes |

Backfill por tenant em transação:

1. `upsert` dos dois perfis de sistema.
2. `sync` das chaves exatas do catálogo versionado.
3. `ADMIN→tenant_admin`, perfil nulo.
4. `OPERATOR→tenant_user + legacy-operator`.
5. `VIEWER→tenant_user + legacy-viewer`.
6. `PLATFORM_ADMIN→platform_admin`.
7. validar nenhum `tenant_user` sem perfil ou profile cross-tenant.
8. calcular snapshot de capacidades antes/depois e abortar se o novo conjunto for diferente.
9. gravar auditoria/ledger sanitizado e commit.

O comando é retomável por unique keys e upserts; não duplica. Valor desconhecido ou divergência aborta o tenant antes do cutover.

### D14 — Cache, concorrência e auditoria

Cache de permissões, se usado, terá chave:

```text
tenant-auth:{user_id}:{office_id}:{membership_version}:{profile_version}
```

Nunca cachear somente por usuário. Alterações incrementam versão após commit e removem caches conhecidos. Troca de tenant também invalida stores frontend; tokens/sessões são revogados quando a mudança reduz ou eleva autoridade de forma sensível.

Locks obrigatórios:

- último `platform_admin` ativo;
- último `tenant_admin` ativo;
- conclusão do primeiro onboarding;
- mudança do tenant principal;
- lifecycle de Office;
- alteração de perfil e reatribuições em lote.

Auditoria registra ator real, target IDs, tenant, modo, ação, resultado, motivo/código e correlation ID. Side effects são disparados após commit. Nenhum log inclui segredos, XML ou conteúdo fiscal.

### D15 — Gate de arquitetura e estratégia de testes

Adicionar gate que encontra literais de autenticação legados apenas em contextos de RBAC, com allowlist temporária de migrations, fixtures, adaptadores e docs históricas. Não fazer replace cego de:

- `OPERATOR_REVIEW` e outros códigos de domínio;
- `FiscalRole` (`ISSUER`, `RECIPIENT` etc.);
- atributos HTML `role`;
- perfil institucional, perfil outbound ou perfil pessoal;
- registros históricos SERPRO `OWNER_*`.

Matriz mínima automatizada:

| Eixo | Valores |
|---|---|
| ator | platform sem contexto, platform privilegiado, tenant admin, tenant user operador, tenant user viewer, tenant user custom, inativo |
| alvo | mesmo tenant, outro tenant, inexistente |
| lifecycle | active, suspended, deprovisioned |
| ação | global, leitura tenant, mutação tenant, usuário, perfil, SERPRO, Work |
| guard | liberado, assinatura bloqueada, flag OFF, kill switch ON |

Testes específicos:

- migrations/backfill em SQLite e PostgreSQL, idempotência e divergência;
- concorrência do último admin global/local e onboarding;
- principal automático e cadastro de cliente direto;
- zero chamada SERPRO em contexto ausente/cruzado/suspenso;
- troca de tenant sem cache/resposta residual;
- policies de todas as famílias hoje ligadas a `OfficeRole`;
- aprovação SERPRO por dois platform admins distintos;
- API compatível e depois canônica;
- frontend de onboarding, equipe, perfis, navegação, admins, lifecycle e selector;
- OpenSpecs PGDAS-D/PGMEI/DCTFWeb sem papéis legados.

### D16 — Mapa de implementação por família

| Família | Pontos de entrada principais |
|---|---|
| enums/models | `PlatformRole`, novo `TenantRole`, `OfficeRole`, `User`, `OfficeMembership`, `PlatformMembership`, `Office` |
| contexto | `CurrentOffice`, `PlatformPrivilegedContext`, `EnsureOfficeContext`, `TenantSwitchService`, `PlatformOfficeSelectService` |
| RBAC | policies, `AppServiceProvider`, controllers fiscais, Operations, Work, Outbound, Sefaz, Integra |
| usuários | `OfficeMemberController`, `OfficeTeamService`, activation services |
| plataforma | `PlatformOwnerService/Controller`, comandos owner, `EnsurePlatformAdmin`, rotas `/platform/*` |
| onboarding | `InitialOnboardingService/Controller`, `BootstrapOfficeCommand`, seeders/factories |
| tenants | `PlatformOfficeController`, `TenantAdminController`, `OfficeSubscriptionService`, lifecycle enum |
| frontend contrato | `types/api.ts`, `createOfficeApi.ts`, `createPlatformApi.ts`, `/me` |
| frontend autorização | `utils/permissions.ts`, `navigation.ts`, `account-navigation.ts`, middleware e composables de switch |
| frontend telas | onboarding, settings/team, novos perfis, admin/admins, admin/offices, `OfficeIdentity` |
| specs conflitantes | PGDAS-D monitoring/communication, PGMEI monitoring, DCTFWeb monitoring/design |

## Riscos / Trade-offs

| Risco | Mitigação |
|---|---|
| Backfill amplia ou reduz acesso legado | snapshot por membership e diff exato antes do commit/cutover |
| Perfil cross-tenant concede acesso | query scoped, validação `office_id`, constraint composta quando portável e teste negativo |
| SPA antiga quebra com lowercase | contrato aditivo, aliases derivados e feature flag antes da contração |
| Rollback perde perfis customizados | não habilitar CRUD customizado antes do frontend novo; sombra `VIEWER` conservadora; nunca rollback destrutivo |
| Cache mantém permissão revogada | versões de membership/perfil na chave e invalidação após commit |
| Duas desativações removem último admin | lock + recontagem dentro da transação |
| Platform admin vira bypass universal | resolvedor exige tenant explícito; gate de arquitetura proíbe `Gate::before` irrestrito |
| Suspensão ocorre com job em voo | revalidar lifecycle imediatamente antes de segredo, mutação e transporte |
| `default_office_id` legado é confundido com propriedade | principal exige confirmação explícita; nunca conceder membership por inferência |
| Desprovisionamento apaga dados por cascade | não chamar delete; estado terminal e testes de preservação de contagens/evidências |
| Mudança transversal fica grande demais | ondas com gates independentes, adaptadores temporários e commits/PRs por família |
| Dois modelos de suspensão confundem | lifecycle controla existência operacional; assinatura controla elegibilidade comercial; testes ortogonais |
| Terminologia SERPRO Owner fica incoerente | preservar códigos históricos, alterar semântica/autorização e textos novos; rename histórico posterior |
| Lista de permissões fica genérica demais | inventário obrigatório de cada decisão atual e chave semântica específica por ação |
| Frontend exibe capacidade indevida | servidor fornece chaves efetivas, mas policy backend permanece autoridade e tem teste espelho |

## Plano de migração

### W0 — Baseline e bloqueios

1. Congelar matriz atual de `OfficeRole` em testes de caracterização.
2. Inventariar papéis, decisões, endpoints, frontend e specs; criar allowlist inicial de literais legados.
3. Adicionar feature flag `canonical_multitenant_rbac` default OFF e métricas sem PII.
4. Criar preflight/dry-run; não tocar dados.
5. Fazer backup e registrar contagens por tabela/papel antes de qualquer deploy produtivo.

**Gate:** suíte atual verde; dry-run não altera banco; inventário cobre todos os consumidores.

### W1 — Expandir schema e domínio sem mudar comportamento

1. Criar tabelas/colunas de D3 e enums/catálogo.
2. Criar models, relationships, services e factories canônicos.
3. Implementar dual-read/dual-write e aliases HTTP, mantendo saída antiga como default.
4. Implementar `TenantAuthorization` em shadow mode: compara decisão nova com a antiga e continua obedecendo a antiga.
5. Implementar cache versionado e auditoria.

**Gate:** nenhuma divergência nos testes; app antiga continua funcional; migrations `up` em PostgreSQL/SQLite.

**Rollback:** desligar flag e retornar ao resolver legado; manter schema aditivo.

### W2 — Backfill e reconciliação

1. Rodar dry-run e resolver valores desconhecidos/orfandades.
2. Escolher explicitamente tenant principal em instalações existentes.
3. Executar backfill idempotente dos perfis e papéis canônicos.
4. Remover o índice parcial singleton somente depois de `platform_role` preenchido e serviço plural testado.
5. Comparar capacidades efetivas legado × canônico por membership.
6. Revogar sessões ao concluir a onda.

**Gate:** zero `tenant_user` sem perfil, zero perfil cross-tenant, zero divergência de capacidade, ao menos um platform admin e um tenant admin por tenant operacional.

**Rollback:** dual-write mantém sombras legadas; flag volta ao legado. Perfis ficam inertes, sem deleção.

### W3 — Plataforma, onboarding e lifecycle

1. Ativar `PlatformBootstrapService` no onboarding/CLI e principal automático para instalações novas.
2. Entregar coleção de platform admins e proteção do último ativo.
3. Entregar lifecycle e invalidadores de sessão/job.
4. Manter `platform_privileged_context` OFF por default; habilitar apenas em ambiente/coorte aprovada.
5. Exercitar dois administradores distintos nos fluxos SERPRO dual approval simulados.

**Gate:** concorrência, bootstrap e preservação de dados no desprovisionamento verdes; zero chamada externa em testes bloqueados.

### W4 — Cutover backend por capabilities

1. Migrar policies primeiro, depois controllers/services por domínio.
2. Shadow mode registra divergência por código de decisão, sem payload sensível.
3. Corrigir toda divergência; então tornar canônico autoritativo por coorte/allowlist.
4. Manter adaptador legado para rollback e SPA antiga.

**Gate:** período operacional definido com zero divergência e suites completas verdes.

### W5 — Cutover frontend

1. Publicar tipos/contratos canônicos e `hasPermission`.
2. Migrar onboarding, selector, navegação e guards.
3. Entregar equipe/perfis, platform admins e lifecycle com arquétipos do painel.
4. Habilitar CRUD de perfis customizados somente após confirmar que assets antigos não são servidos.
5. Monitorar 401/403/409 e erros de contrato.

**Gate:** `pnpm run test:gate`, generate/fidelity/artifacts e testes unitários de troca de contexto verdes.

### W6 — Contração

Pré-condições: rollback legado não é mais exigido, métricas sem divergência pelo período definido, workers e SPA antigos drenados, backup validado.

1. Parar de emitir/aceitar aliases e writes legados.
2. Tornar colunas canônicas obrigatórias e constraints finais.
3. Remover colunas `role` legadas em migration forward-only, `OfficeRole`, adaptadores e allowlist transitória.
4. Remover índice/service/API/comandos singleton e `/platform/owner`.
5. Atualizar textos/configs/docs e changes ativas.
6. Rodar gate de arquitetura com allowlist apenas histórica.

**Rollback pós-contração:** não usar `down()` destrutivo. Fazer roll-forward restaurando compatibilidade a partir de backup/colunas canônicas; perfis não podem ser reduzidos automaticamente a Operator/Viewer sem perda.

### W7 — Verificação e fechamento

1. Backend: Pint, suíte completa e testes específicos PostgreSQL quando aplicável.
2. Frontend: `pnpm run test:gate`, generate, fidelity e artifacts.
3. Segurança: isolamento negativo, logs sem segredo, jobs fail-closed e flags OFF.
4. OpenSpec: validar change/main specs; alinhar PGDAS-D/PGMEI/DCTFWeb.
5. Sync/archive e commit no mesmo dia apenas após evidências de produção/rollout requeridas.

## Questões operacionais não bloqueantes

As decisões funcionais estão fechadas. Antes do rollout produtivo, o operador ainda deverá informar:

- ID explícito do tenant principal em instalações com dados existentes.
- Duração mínima da janela de shadow mode/zero divergência antes de W6.
- Coorte/allowlist em que `platform_privileged_context` será habilitado.
- Canal de reconfirmação para ações sensíveis de lifecycle e platform admins, reutilizando mecanismo já aprovado no produto.
- Política jurídica futura de purge, deliberadamente fora desta change.

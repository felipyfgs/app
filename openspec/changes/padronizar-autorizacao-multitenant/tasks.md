## Regras de execução para o agente implementador

Execute na ordem; não inicie uma tarefa se a dependência anterior estiver vermelha. Preserve alterações preexistentes do usuário e não faça replace global de `ADMIN`, `OPERATOR`, `VIEWER`, `PLATFORM_ADMIN` ou `OWNER`: confirme que a ocorrência é papel de autenticação, e não `FiscalRole`, `OPERATOR_REVIEW`, atributo HTML, perfil de outro domínio ou protocolo histórico. Toda flag nova permanece OFF por default. Não habilite SERPRO live, mutação fiscal ou outbound. Não marque `[x]` sem código, teste e evidência correspondente.

## 1. Baseline, catálogo e guardrails

- [x] 1.1 Congelar o comportamento legado e criar o inventário executável de autorização antes de alterar decisões

  Implementação: criar documento operacional de inventário com todas as ocorrências backend/frontend/OpenSpec de `OfficeRole`, `PlatformRole`, singleton Owner e comparações diretas; transformar os 15 métodos `OfficeRole::can*` e os helpers frontend atuais em testes de caracterização; fechar o enum/catálogo `TenantPermission` com chave, módulo, risco e `delegable`; adicionar `canonical_multitenant_rbac` default OFF; criar teste de arquitetura que proíba `Gate::before` irrestrito e novos literais de autenticação legados fora de allowlist temporária precisa.

  Armadilhas: não incluir `OPERATOR_REVIEW`, `FiscalRole`, atributos HTML `role`, perfis institucionais/outbound ou códigos históricos `OWNER_*` no replace/gate; não alterar comportamento nesta tarefa.

  Evidência: testes de caracterização passam com o código legado; snapshot do inventário lista arquivo/decisão/chave canônica; `FeatureFlagsTest` confirma flag OFF; teste de arquitetura acusa uma fixture proposital de bypass/literal e fica verde após removê-la. Cobre TAG-03, TAG-10, TAG-14 e TAG-15.

## 2. Expansão de schema e domínio

- [x] 2.1 Criar migrations forward-only aditivas para RBAC canônico, tenant principal e lifecycle

  Implementação: sem editar migrations antigas, criar `tenant_permission_profiles`, `tenant_permission_profile_permissions`, `office_user.tenant_role`, `office_user.permission_profile_id`, `office_user.authorization_version`, `platform_memberships.platform_role`, `platform_settings.primary_office_id` e suporte a `SUSPENDED|DEPROVISIONED` em `OfficeLifecycleStatus`; manter `office_user.role` e `platform_memberships.role`; usar índices/uniques de D3, `is_active` em vez de soft delete e FK composta profile/office quando portável, com guard de domínio obrigatório no SQLite.

  Armadilhas: não remover ainda `platform_memberships_one_platform_admin`; não usar cascade/hard delete de Office; não criar tabela `permissions` com textos arbitrários; não renomear `Office`/`office_user`.

  Evidência: `migrate:fresh` e upgrade de fixture legada passam em SQLite e PostgreSQL; schema tests confirmam colunas, índices, ausência de `deleted_at`, FKs e preservação das colunas legadas. Cobre TAG-01, TAG-03, TAG-04, TLC-02, TLC-07 e TLC-10.

- [x] 2.2 Implementar enums, models, relações, catálogo e versionamento de autorização sem ativar o cutover

  Implementação: adicionar `TenantRole`, atualizar `PlatformRole` para o valor canônico por adaptador compatível, criar `TenantPermission`, `TenantPermissionProfile` e relação de chaves; evoluir `OfficeMembership`, `PlatformMembership`, `User`, `Office` e `PlatformSetting`; implementar invariantes `tenant_admin→profile null`, `tenant_user→profile ativo do mesmo office`, perfis de sistema imutáveis e `authorization_version` monotônico; atualizar factories para produzir estados canônicos e legados explicitamente.

  Armadilhas: casts não podem falhar ao ler linhas ainda não backfilled; perfil cross-tenant falha fechado; não instalar Spatie; não usar cache somente por `user_id`.

  Evidência: testes unitários cobrem enums/metadata, perfil cross-tenant, perfil inativo, system profile imutável, version bump e leitura de linhas antigas/novas. Cobre TAG-01 a TAG-04 e TAG-13.

## 3. Preflight, backfill e contrato compatível

- [x] 3.1 Implementar comando dry-run/apply idempotente com reconciliação explícita e comparação de paridade

  Implementação: criar `app:multitenant-rbac:migrate` com `--dry-run`, `--apply`, `--primary-office`, nome/slug explícitos quando não houver Office e confirmação; inventariar valores, órfãos, admins, principal e jobs; criar/upsert `legacy-operator` e `legacy-viewer` por Office; mapear `ADMIN→tenant_admin`, `OPERATOR→tenant_user+operator`, `VIEWER→tenant_user+viewer`, `PLATFORM_ADMIN→platform_admin`; comparar conjuntos de capacidades antes/depois por membership; registrar somente IDs/contagens sanitizadas; revogar sessões após apply.

  Armadilhas: nunca inferir propriedade de `default_office_id`; com um ou vários tenants exigir `--primary-office`; valor desconhecido, tenant sem admin, perfil inconsistente ou aumento/redução de capacidade aborta; reexecução não duplica.

  Evidência: testes cobrem cada papel legado, banco sem Office, um/múltiplos Offices, papel desconhecido, órfão, divergência, interrupção/reexecução e dry-run sem writes; contagens e hashes de capacidade conciliam. Cobre TAG-14 e TLC-14.

- [x] 3.2 Implementar dual-read/dual-write e contratos HTTP aditivos sem mudar ainda a autoridade

  Implementação: adicionar normalizador/adaptador de storage; durante a flag OFF, manter decisão legada mas preencher colunas canônicas e sombra legada; evoluir `/api/v1/me`, memberships, seletor comum/privilegiado e equipe para retornar `platform_role`, `tenant_role`, `real_tenant_role`, `effective_permissions` ordenadas, perfil e `access_mode`; manter temporariamente `is_platform_admin`, `role`, `real_office_role` e `office` como aliases derivados; aceitar writes novos e isolar o adaptador de write legado.

  Armadilhas: aliases nunca são autoridade; perfil customizado usa sombra `VIEWER` conservadora; sem tenant, permissões efetivas são vazias; não retornar `office_id` como input tenant-scoped nem material sensível.

  Evidência: contract tests congelam resposta canônica e compatível, round-trip dual-write, SPA legada, tenant user custom, platform sem contexto e rejeição de payload inconsistente. Cobre TAG-12 e TAG-14.

## 4. Resolvedor e migração das autorizações backend

- [x] 4.1 Implementar `TenantAuthorization` em shadow mode e ajustar `CurrentOffice` para modos explícitos

  Implementação: criar resolvedor central com usuário/tenant/lifecycle/target/mode/membership/profile fail-closed; manter gates globais separados; implementar comparação old/new com métrica sem PII; ajustar ordem de `CurrentOffice` para seleção privilegiada explícita, depois membership selecionada, depois primeira membership; `default_office_id` não abre tenant sem membership; conta dual usa perfil em membership mode e paridade `tenant_admin` somente em privileged mode; cache inclui user+office+membership/profile versions.

  Armadilhas: não usar `Gate::before`; não criar membership fictícia; `platform_privileged_context` continua OFF por default; target de outro Office falha mesmo para platform admin; lifecycle/flags/assinatura/kill switch continuam depois do RBAC.

  Evidência: matriz Unit/Feature cobre todos os atores/modos/alvos, cache por tenant, troca de contexto, tenant inativo, flag OFF, audit mode e zero divergência contra a matriz legada. Cobre TAG-02, TAG-09, TAG-10 e TAG-13.

- [x] 4.2 Migrar policies e gates de models para `TenantPermission` preservando isolamento e guards

  Implementação: refatorar policies de Client, Contact, Credential, Establishment, OutboundCaptureProfile, SerproTenantAccess, OfficeSettings, SavedListFilter, OfficeFiscalCredential e Work; registrar gates no `AppServiceProvider` sem bypass global; cada policy model-based valida que o alvo pertence ao `CurrentOffice`; manter assinatura, senha recente, consentimento, flags e guards de alto risco separados.

  Armadilhas: não converter leitura/model binding em `withoutGlobalScopes`; não tratar permissão como substituta do scope; acesso cruzado não enumera existência.

  Evidência: testes policy por ator × ação × same/cross tenant, incluindo platform global sem contexto, platform privilegiado, tenant admin, operator/viewer migrados, custom e inativo; architecture test confirma ausência de bypass. Cobre TAG-02, TAG-05, TAG-09 e TAG-10.

- [ ] 4.3 Migrar comparações diretas em controllers/services por lotes e zerar divergências shadow

  Implementação: migrar, em commits/lotes verificáveis, Clients/Credentials/Imports/Exports; Fiscal/Monitoring/communications; Operations/Inbox; Outbound/SEFAZ; Integra/SERPRO tenant; Work. Substituir `in_array`/`OfficeRole::can*` por chave semântica específica do catálogo; preservar cada guard transversal; atualizar configurações `tax_guides`, `work_route_matrix` e mensagens. Só mudar shadow para canônico após cada lote registrar zero divergência.

  Armadilhas: não reaproveitar chave genérica apenas porque os papéis antigos coincidiam; não alterar códigos `OPERATOR_REVIEW`, `FiscalRole` ou protocolos Owner; não habilitar transporte externo em teste.

  Evidência: suíte Feature da família passa antes/depois, teste de caracterização comprova paridade Operator/Viewer, métrica shadow zera para o lote e busca de `OfficeRole` fica restrita à allowlist temporária. Cobre TAG-14 e TAG-15.

## 5. Perfis, equipe e delegação tenant

- [ ] 5.1 Implementar catálogo HTTP e CRUD tenant-scoped de perfis de permissão

  Implementação: criar services/policies/controllers/requests/resources e rotas `GET /office/permissions` e CRUD `/office/permission-profiles`; listar somente metadados/chaves delegáveis aplicáveis ao ator; criar/clonar/editar/desativar perfis custom; bloquear edição/deleção dos dois system profiles e perfil atribuído; auditar diffs de chaves; incrementar versão e invalidar cache após commit.

  Armadilhas: carregar profile sempre pelo Office atual; chave desconhecida/reservada retorna 422 sem write parcial; nome único por tenant; mesma chave/nome pode existir em tenants distintos; sem `office_id` no payload como autoridade.

  Evidência: Feature tests cobrem CRUD, isolamento não enumerável, concorrência, system profile, perfil atribuído, auditoria sanitizada, cache invalidado e tenant admin/platform privileged versus tenant user. Cobre TAG-03, TAG-04, TAG-11 e TAG-13.

- [ ] 5.2 Evoluir `OfficeTeamService` e equipe para papéis canônicos, subconjunto delegável e último admin

  Implementação: requests/payloads usam `tenant_role` + `permission_profile_id`; tenant admin cria ambos os papéis tenant; tenant user com `tenant.users.create` cria apenas tenant user e perfil-subconjunto; editar/desativar/reativar/regenerar ativação respeita permissões; proteger último tenant admin com lock e recontagem; platform admin só usa paridade quando contexto privilegiado explícito; revogar sessões/cache em mudança sensível.

  Armadilhas: endpoint tenant nunca aceita platform admin; perfil deve ser do Office atual; não confiar no conjunto enviado pelo browser; conta dual em membership mode usa papel real.

  Evidência: testes cobrem todos os casos TAG-05 a TAG-07, duas desativações concorrentes, limite de assinatura, ativação e cross-tenant; contrato não expõe segredo além do método de entrega aprovado.

## 6. Plataforma plural e tenant principal

- [ ] 6.1 Substituir Owner singleton por coleção de platform admins e alinhar semântica SERPRO histórica

  Implementação: criar `PlatformAdminService`, controller/requests/resources e `/platform/admins`; implementar convite, update, deactivate/reactivate e regenerate; remover o índice parcial singleton somente nesta etapa, preservando unique user/papel; lock/recontagem considera user+membership ativos; manter `/platform/owner` somente como alias read-only temporário; adaptar recuperação break-glass; reinterpretar `OWNER_CONFIRMATION` histórico como aprovação por platform admin ativo e permitir dois aprovadores distintos sem renomear/apagar trilha histórica.

  Armadilhas: não criar quarto papel; não permitir tenant endpoint elevar para platform admin; não consolidar/remover admins existentes; não alterar colunas históricas sem compatibilidade.

  Evidência: testes coexistem 2+ admins, bloqueiam último sob concorrência, negam ator tenant, validam ativação e dois olhos SERPRO com usuários distintos; schema test confirma singleton removido e unique restante. Cobre TAG-08, TAG-10 e TAG-11.

- [ ] 6.2 Extrair `PlatformBootstrapService` e fazer onboarding/CLI/seeders criarem tenant principal real

  Implementação: unificar `InitialOnboardingService` e `BootstrapOfficeCommand`; transação cria PlatformSetting, usuário, platform membership, Office ACTIVE, perfil institucional parcial, assinatura, OfficeMembership tenant_admin, selected/default/primary office e auditoria after-commit; onboarding retorna contexto e redirect `/`; criar segundo platform admin não cria Office; atualizar activation redirects, factories, `DatabaseSeeder`, `PlatformAdminDemoSeeder`, `.env.example` e comandos owner obsoletos.

  Armadilhas: não inventar CNPJ; não habilitar flags/SERPRO/outbound; rollback total em falha; não promover admin existente por inferência de default office.

  Evidência: testes de transação/falha/concorrência/retry confirmam todos os artefatos, primeiro login com tenant membership, cadastro de cliente direto e consumo SERPRO simulado atribuído ao principal; onboarding antigo `/admin/offices/new` deixa de ser esperado. Cobre TLC-01 a TLC-04.

## 7. Ciclo de vida e bloqueio operacional

- [ ] 7.1 Implementar máquina de lifecycle e APIs globais de editar/suspender/reativar/desprovisionar/mudar principal

  Implementação: criar service transacional versionado para transições de D10; expandir listagem global para todos os estados com metadados sanitizados; adicionar PATCH e actions em `/platform/offices/{office}`; exigir platform admin, motivo, confirmação sensível e idempotência; separar lifecycle de SubscriptionStatus; impedir desprovisionar principal e nunca chamar delete/SoftDeletes/cascade.

  Armadilhas: somente ACTIVE é selecionável; transição inválida 409; tenant admin não altera lifecycle; rota administrativa com Office ID não muda `CurrentOffice`; não mover dados ao trocar principal.

  Evidência: matriz de estados/transições, concorrência e idempotência verde; testes preservam contagens de clients/evidências/auditoria/vault refs no desprovisionamento; lifecycle e assinatura são testados ortogonalmente. Cobre TLC-05 a TLC-07 e TLC-09 a TLC-13.

- [ ] 7.2 Propagar suspensão/desprovisionamento para sessões, scopes, scheduler, Horizon e transportes externos

  Implementação: invalidar `current_office_id`, `platform_selected_office_id`, selected/default quando incompatíveis; `EnsureOfficeContext`, TenantSwitch e selector recusam não ACTIVE; todo job tenant carrega tenant/actor necessários e revalida lifecycle imediatamente antes de abrir cofre, mutar ou chamar SERPRO/SEFAZ/outbound; scheduler não enfileira; registrar bloqueio idempotente e observabilidade sem segredo.

  Armadilhas: autoridade global do platform admin continua acessível quando seu principal suspende, mas nenhum dado tenant; job A nunca executa sob contexto B; reativação não replaya jobs automaticamente.

  Evidência: testes com fake transports provam zero chamada externa em contexto ausente/cruzado/suspenso/deprovisionado, job já enfileirado, troca durante request e reativação sem replay. Cobre TLC-04, TLC-08, TLC-09 e TLC-11 a TLC-13.

## 8. Contrato e superfícies frontend

- [ ] 8.1 Migrar tipos, cliente API, helpers, middleware e navegação para papéis/permissões canônicos

  Implementação: adicionar `PlatformRole`, `TenantRole`, `TenantPermissionKey`, profile summary e campos `/me`; criar `hasPermission`, `isPlatformAdmin`, `isTenantAdmin`, `hasSelectedTenant`; manter helpers antigos delegando às chaves durante transição; atualizar `auth.global`, auth redirect, account/navigation, quick actions, monitoring, Work e SERPRO; `/conta/*` passa a autorizar por rota; troca de tenant invalida epoch/stores e descarta respostas antigas.

  Armadilhas: frontend só reflete, não autoriza; sem tenant, platform admin vai ao plano global; com principal corrente, abre carteira; não fazer replace em códigos não-RBAC.

  Evidência: unit tests cobrem matriz de papéis/perfis, navegação por capability, chamada manual negada pelo backend espelho, redirect, cache cross-tenant e aliases compatíveis. Cobre TAG-12, TAG-13 e TAG-15.

- [ ] 8.2 Implementar telas de perfis/equipe, platform admins, lifecycle, onboarding e seletor usando o arquétipo do painel

  Implementação: usar `panel-ui`→`ui-archetype`; adicionar “Perfis e permissões” em Conta, equipe com tenant role/perfil, “Administradores da plataforma”, edição e actions de tenant, confirmação/erros 409, onboarding que entra no principal, seletor com opção de modo privilegiado e banner persistente; mostrar nome do perfil para tenant user; suspensos aparecem só na gestão global e somem do selector.

  Armadilhas: não exibir PFX/token/XML; não habilitar perfil custom antes do frontend novo estar ativo; platform admin adicional não ganha tenant automático; ações sensíveis exigem confirmação retornada pelo backend.

  Evidência: testes Nuxt/Vitest das páginas/componentes e snapshots de fidelidade cobrem loading/empty/error, permissão, cross-tenant, troca em voo, principal, último admin e lifecycle; `pnpm run test:gate`, generate, fidelity e artifacts passam. Cobre TAG-05 a TAG-09, TAG-15 e TLC-01, TLC-02, TLC-08 a TLC-11.

## 9. Rollout, corpus OpenSpec e contração

- [ ] 9.1 Documentar e executar shadow/cutover por coorte, alinhando specs ativas e runbooks

  Implementação: documentar backup, dry-run, apply, métricas old/new, revogação de sessões, rollback lógico e seleção explícita do principal; manter flags OFF e habilitar `canonical_multitenant_rbac`/`platform_privileged_context` somente por ambiente/coorte aprovada; atualizar specs/designs ativos de PGDAS-D, PGMEI e DCTFWeb para permissões semânticas; atualizar docs/env/textos Owner sem modificar protocolo histórico; registrar período sem divergência exigido para contração.

  Armadilhas: não marcar concluído apenas em dev; não registrar PII/segredos; não habilitar live/outbound; conflito em change ativa deve ser resolvido antes de archive.

  Evidência: runbook exercitado em staging com contagens antes/depois, rollback simulado e zero divergência; OpenSpec strict verde para todas as changes tocadas; aprovação operacional da coorte registrada. Cobre TAG-11, TAG-14, TAG-15 e TLC-14.

- [ ] 9.2 Executar a fase contract somente após gates de observação e remover a autoridade legada

  Implementação: após workers/assets antigos drenados, backup validado e período zero-divergência cumprido, parar aliases/writes antigos; tornar `tenant_role`/`platform_role` obrigatórios; remover colunas `role` por migration forward-only, `OfficeRole`, adaptadores, `/platform/owner`, `PlatformOwnerService/Exception`, comandos singleton e allowlist transitória; atualizar todos os testes/fixtures/configs; gate final só permite literais em migrations/docs históricas justificadas.

  Armadilhas: não usar `down()` destrutivo; perfil custom não pode ser reconvertido sem perda; se qualquer pré-condição não existir, deixar esta task aberta e permanecer no modo compatível.

  Evidência: busca/architecture gate sem decisões RBAC legadas, migrations finais verdes, API só canônica, backup/roll-forward documentados e suite completa sem adaptador. Cobre TAG-01, TAG-12, TAG-14 e TLC-14.

## 10. Verificação e fechamento

- [ ] 10.1 Executar todos os gates, registrar evidências, sincronizar/arquivar a change e criar commit atômico no mesmo dia

  Implementação: rodar backend Pint e `php artisan test` completo (host ou container), testes PostgreSQL específicos de índice/lock, frontend `pnpm run test:gate`, `pnpm run generate`, `pnpm run test:fidelity`, `pnpm run test:artifacts`, testes de arquitetura/segurança, dry-run de migration e `openspec validate --strict`; revisar logs por segredos, flags default OFF, ausência de chamadas live e preservação do worktree alheio; sincronizar as duas specs para main, arquivar com `openspec-archive-change` e usar `git-commit` somente quando todas as tasks e evidências reais estiverem concluídas.

  Armadilhas: não reduzir testes à suíte afetada, não marcar waves produtivas simuladas como reais, não arquivar com task 9.2 aberta e não incluir mudanças preexistentes não relacionadas no commit.

  Evidência: anexar comandos/status, contagens de migração, matriz de autorização, relatório zero-divergência, testes frontend/backend/OpenSpec e hash do commit; nenhuma task permanece aberta. Cobre todas as requirements TAG/TLC e a regra de fechamento do projeto.

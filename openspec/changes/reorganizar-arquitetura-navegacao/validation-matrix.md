# Matriz de validação — reorganizar-arquitetura-navegacao

Baseline registrado antes de alterar o chrome (task 1.1). Cobertura alinhada a
`frontend/tests/fixtures/template-parity-matrix.md`. Resultado visual
(task 4.3) preenche a coluna **Resultado** após a migração.

Viewports: **desktop** ≥1024px · **mobile** 375px. Critérios por rota aplicável:
loading, vazio, erro, detalhe, foco/teclado, overflow, menu `Mais ações` quando houver.

## Conflitos de ownership (antes de editar)

| Arquivo | Upstream / paralelo | Regra nesta change |
|---------|---------------------|--------------------|
| `frontend/app/utils/navigation.ts` | `padronizar-autorizacao-multitenant` | Diff mínimo; helpers de capacidade como adaptadores |
| `frontend/app/utils/account-navigation.ts` | idem | Agrupar Conta sem implementar RBAC |
| `frontend/app/composables/useDashboard.ts` | idem | Não alterar autoridade de permissão |
| `frontend/app/utils/permissions.ts` | idem | Somente consumir; não redefinir papéis |
| `frontend/app/layouts/default.vue` | idem | Agregar catálogo canônico |
| Specs/APIs multitenant | ownership upstream | Fora de escopo |

## Aliases e destinos finais

| Alias / redirect | Destino canônico |
|------------------|------------------|
| `/notes`, `/notes/:accessKey` | `/docs`, `/docs/:accessKey` |
| `/settings`, `/settings/*` | `/conta`, `/conta/*` (mapeamento existente) |
| `/docs/import-batches` | `/docs/imports` |
| `/admin`, `/admin/departments`, `/admin/owner` | destinos admin existentes |
| `/monitoring/simples-mei/:submodule` | `/monitoring/simples-mei` (modo local) |
| `/monitoring/dctfweb/:submodule` | `/monitoring/dctfweb` (modo local) |
| 2FA challenge/setup | fluxos AUTH (fora do shell) |

## Baseline de rotas

Legenda Resultado: `PENDENTE` até task 4.3 · `PASS` / `FAIL` após inspeção.

### AUTH

| Arquivo | Rota | Área / item ativo | Ações relevantes | Perfis | Desktop | Mobile | Resultado |
|---------|------|-------------------|------------------|--------|---------|--------|-----------|
| `activate.vue` | `/activate` | — | ativação | público | loading/erro | idem | PENDENTE |
| `first-access.vue` | `/first-access` | — | primeiro acesso | autenticado | — | — | PENDENTE |
| `login.vue` | `/login` | — | login | público | — | — | PENDENTE |
| `onboarding.vue` | `/onboarding` | — | onboarding | autenticado | — | — | PENDENTE |
| `two-factor-challenge.vue` | `/two-factor-challenge` | — | challenge | autenticado | — | — | PENDENTE |
| `two-factor/setup.vue` | `/two-factor/setup` | — | setup | autenticado | — | — | PENDENTE |

### REDIRECT (validar destino final)

| Arquivo | Rota | Destino final | Resultado |
|---------|------|---------------|-----------|
| `admin/departments.vue` | `/admin/departments` | alias admin | PENDENTE |
| `admin/index.vue` | `/admin` | alias admin | PENDENTE |
| `admin/owner/index.vue` | `/admin/owner` | alias admin | PENDENTE |
| `docs/import-batches.vue` | `/docs/import-batches` | `/docs/imports` | PENDENTE |
| `notes/index.vue` | `/notes` | `/docs` | PENDENTE |
| `notes/[accessKey].vue` | `/notes/:accessKey` | `/docs/:accessKey` | PENDENTE |
| `settings.vue` + filhos | `/settings/*` | `/conta/*` | PENDENTE |
| `settings/proxies.vue` | `/settings/proxies` | redirect legado | PENDENTE |
| `monitoring/*/ [submodule]` | submódulo legado | path canônico do módulo | PENDENTE |

### SHELL — Início / Trabalho

| Arquivo | Rota | Área | Item ativo (pós) | Ações | Perfis | Desktop | Mobile | Resultado |
|---------|------|------|------------------|-------|--------|---------|--------|-----------|
| `index.vue` | `/` | Início | Início | — | tenant | overflow/foco | idem | PENDENTE |
| `work/index.vue` | `/work` | Trabalho | Minha fila | presets=filtros | `canViewWork` | toolbar | seletor | PENDENTE |
| `work/processes/index.vue` | `/work/processes` | Trabalho | Processos | — | `canViewWork` | — | — | PENDENTE |
| `work/processes/[id].vue` | `/work/processes/:id` | Trabalho (contexto) | Resumo/Tarefas/Comentários/Histórico | retorno | `canViewWork` | substitui tabs área | seletor | PENDENTE |
| `work/calendar.vue` | `/work/calendar` | Trabalho | Calendário | visão local | `canViewWork` | — | — | PENDENTE |
| `work/templates/index.vue` | `/work/templates` | Trabalho | Modelos | CRUD catálogo | `canManageWorkCatalog` | — | — | PENDENTE |
| `work/tasks/[id].vue` | `/work/tasks/:id` | Trabalho | (detalhe tarefa) | mestre–detalhe | `canViewWork` | — | — | PENDENTE |

### SHELL/CHILD — Clientes

| Arquivo | Rota | Área | Item / grupo ativo (pós) | Ações | Perfis | Desktop | Mobile | Resultado |
|---------|------|------|--------------------------|-------|--------|---------|--------|-----------|
| `clients.vue` + `index` | `/clients` | Clientes | Lista | Novo cliente | `canManageClients` p/ criar | tabs ≤5 | seletor | PENDENTE |
| `clients/dashboard.vue` | `/clients/dashboard` | Clientes | Dashboard | — | tenant | — | — | PENDENTE |
| `clients/[id].vue` + index | `/clients/:id` | Clientes (contexto) | Visão geral → Resumo | editar | tenant | grupos+subtabs | seletor | PENDENTE |
| `.../cadastro` | `.../cadastro` | | Dados → Cadastro | salvar | `canManageClients` | — | — | PENDENTE |
| `.../estabelecimentos` | `.../estabelecimentos` | | Dados → Estabelecimentos | — | tenant | — | — | PENDENTE |
| `.../ccmei` | `.../ccmei` | | Fiscal → CCMEI | consultar | tenant | — | — | PENDENTE |
| `.../sicalc` | `.../sicalc` | | Fiscal → Receitas SICALC | — | tenant | — | — | PENDENTE |
| `.../pagamentos` | `.../pagamentos` | | Fiscal → Pagamentos | — | tenant | — | — | PENDENTE |
| `.../renuncias` | `.../renuncias` | | Fiscal → Renúncias | — | tenant | — | — | PENDENTE |
| `.../certificado` | `.../certificado` | | Integrações → Certificado A1 | upload | `canManageCredentials` | — | — | PENDENTE |
| `.../sincronizacao` | `.../sincronizacao` | | Integrações → Sincronização | trigger | `canTriggerSync` | — | — | PENDENTE |
| `.../saidas` | `.../saidas` | | Integrações → Captura de saídas | — | tenant | — | — | PENDENTE |
| `.../comprovantes` | `.../comprovantes` | | path preservado; fora da taxonomia de 10 seções | — | tenant | deep link | — | PENDENTE |

### SHELL — Fiscal (Monitoramento)

| Arquivo | Rota | Grupo → folha (pós) | Controles locais | Perfis | Desktop | Mobile | Resultado |
|---------|------|---------------------|------------------|--------|---------|--------|-----------|
| `monitoring/index.vue` | `/monitoring` | Visão geral → Dashboard | KPIs | tenant | ≤5 grupos | seletor completo | PENDENTE |
| `simples-mei/index.vue` | `/monitoring/simples-mei` | Obrigações → Simples/MEI | PGDAS-D\|PGMEI | tenant | sem 11 itens | — | PENDENTE |
| `dctfweb/index.vue` | `/monitoring/dctfweb` | Obrigações → DCTFWeb/MIT | DCTFWeb\|MIT | tenant | — | — | PENDENTE |
| `declarations.vue` | `/monitoring/declarations` | Obrigações → Declarações | filtros | tenant | — | — | PENDENTE |
| `sitfis.vue` | `/monitoring/sitfis` | Regularidade → SITFIS | — | tenant | — | — | PENDENTE |
| `fgts.vue` | `/monitoring/fgts` | Regularidade → FGTS/eSocial | — | tenant | — | — | PENDENTE |
| `registrations.vue` | `/monitoring/registrations` | Regularidade → Cadastro/Vínculos | — | tenant | — | — | PENDENTE |
| `tax-processes.vue` | `/monitoring/tax-processes` | Regularidade → Processos fiscais | — | tenant | — | — | PENDENTE |
| `installments.vue` | `/monitoring/installments` | Financeiro → Parcelamentos | modalidades | tenant | — | — | PENDENTE |
| `guides.vue` | `/monitoring/guides` | Financeiro → Guias | — | tenant | — | — | PENDENTE |
| `mailbox` + filhos | `/monitoring/mailbox` | Comunicações → Caixas Postais | mestre–detalhe | tenant | — | — | PENDENTE |
| `clients/[clientId].vue` | `/monitoring/clients/:id/:section?` | 5 grupos contextuais; Achados=`findings` | lazy/seção | tenant | grupos+subtabs | seletor | PENDENTE |

### SHELL — Documentos / Operações

| Arquivo | Rota | Área → item (pós) | Ações | Perfis | Desktop | Mobile | Resultado |
|---------|------|-------------------|-------|--------|---------|--------|-----------|
| `docs/index.vue` | `/docs` | Documentos → Por cliente | filtros | tenant | — | — | PENDENTE |
| `docs/catalog.vue` | `/docs/catalog` | Documentos → Catálogo | — | tenant | — | — | PENDENTE |
| `docs/[accessKey].vue` | `/docs/:accessKey` | herda Catálogo | download | tenant | — | — | PENDENTE |
| `docs/imports/index.vue` | `/docs/imports` | Documentos → Processamento → Importações | import | tenant | — | — | PENDENTE |
| `docs/imports/[id].vue` | `/docs/imports/:id` | herda Importações | lote | tenant | — | — | PENDENTE |
| `exports.vue` | `/exports` | Documentos → Processamento → Exportações | criar | `canCreateExport` | — | — | PENDENTE |
| `health.vue` | `/health` | Operações → Saúde | refresh | tenant | — | — | PENDENTE |
| `syncs.vue` | `/syncs` | Operações → Sincronizações | — | tenant | — | — | PENDENTE |
| `closing.vue` | `/closing` | Operações → Fechamento | — | tenant | — | — | PENDENTE |

### SHELL/CHILD — Conta / Admin

| Arquivo | Rota | Grupo → folha (pós) | Ações | Perfis | Desktop | Mobile | Resultado |
|---------|------|---------------------|-------|--------|---------|--------|-----------|
| `conta.vue` + index | `/conta` | Perfil | — | autenticado+office | ≤5 grupos | seletor | PENDENTE |
| `conta/escritorio.vue` | `/conta/escritorio` | Organização → Escritório | salvar | office settings | — | — | PENDENTE |
| `conta/departamentos.vue` | `/conta/departamentos` | Organização → Departamentos | — | work catalog | — | — | PENDENTE |
| `conta/equipe.vue` | `/conta/equipe` | Pessoas e acesso → Equipe | convites | manage team | — | — | PENDENTE |
| `conta/assinatura.vue` | `/conta/assinatura` | Plano → Assinatura | — | office settings | — | — | PENDENTE |
| `conta/consumo.vue` | `/conta/consumo` | Plano → Consumo | — | office settings | — | — | PENDENTE |
| `admin/offices/index.vue` | `/admin/offices` | Admin → Escritórios | — | PLATFORM_ADMIN | — | — | PENDENTE |
| `admin/offices/new.vue` | `/admin/offices/new` | fluxo contextual | criar | PLATFORM_ADMIN | — | — | PENDENTE |
| `admin/offices/[id].vue` | `/admin/offices/:id` | fluxo contextual | editar | PLATFORM_ADMIN | — | — | PENDENTE |
| `admin/serpro.vue` | `/admin/serpro` | SERPRO tabs | — | PLATFORM_ADMIN | Operação/Integração/Canário | seletor | PENDENTE |
| SERPRO children | `/admin/serpro/*` | subtabs por grupo | canário/consumo | PLATFORM_ADMIN | — | — | PENDENTE |

## Matriz de capacidades (baseline → pós)

| Destino | Capacidade atual | Observação pós |
|---------|------------------|----------------|
| Trabalho / fila | `canViewWork` | inalterado |
| Modelos | `canManageWorkCatalog` | omitido se ausente |
| Novo cliente | `canManageClients` | primária ou Mais ações |
| Conta escritório/plano | `canAccessOfficeSettings` | grupos Organização/Plano |
| Equipe | `canManageOfficeTeam` | Pessoas e acesso |
| Departamentos | `canManageWorkCatalog` | Organização |
| Admin / SERPRO | `canAccessPlatformAdmin` | sem tenant fiscal |
| Exportações | `canCreateExport` (ação) | path em Documentos |
| Perfis / Administradores | inexistentes | sem links inativos |

## Equivalência de ações navbar (task 3.2)

| Superfície | Antes | Depois |
|------------|-------|--------|
| `/monitoring/clients/:id` | Cadastro + Dashboard expostos | Dashboard primária; Cadastro em `Mais ações` |
| `/exports` | Refresh + Pedir ZIP | Pedir ZIP primária; Atualizar em `Mais ações` |
| Demais shells com 1 ação | Inalterado | Primária exposta; sem menu vazio |

## Checklist de estados visuais (por rota SHELL/CHILD alterada)

- [ ] Loading
- [ ] Vazio
- [ ] Erro / permissão negada
- [ ] Detalhe / deep link / reload
- [ ] Menu ações (quando aplicável)
- [ ] Teclado + foco visível + nome acessível
- [ ] Sem overflow horizontal da página (desktop e mobile)
- [ ] Tabs ≤5 por camada; mobile com seletor ≥44px quando necessário

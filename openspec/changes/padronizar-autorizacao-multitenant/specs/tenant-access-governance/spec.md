## ADDED Requirements

### Requirement: TAG-01 — Papéis canônicos e autoridades separadas
O sistema SHALL reconhecer exatamente `platform_admin`, `tenant_admin` e `tenant_user` como papéis canônicos de acesso. `platform_admin` SHALL existir em uma membership global separada; `tenant_admin` e `tenant_user` SHALL existir na membership do usuário em cada tenant. Um perfil de permissão MUST NOT ser tratado como papel, e um campo global único em `users` MUST NOT ser autoridade para o papel tenant.

#### Scenario: Conta com duas autoridades
- **WHEN** o mesmo usuário é administrador da plataforma e administrador de seu tenant principal
- **THEN** o sistema mantém `platform_role=platform_admin` na autoridade global e `tenant_role=tenant_admin` na membership do tenant, sem fundir os dois vínculos

#### Scenario: Valores finais do contrato
- **WHEN** uma API ou formulário grava um papel depois do cutover canônico
- **THEN** somente os valores lowercase `platform_admin`, `tenant_admin` e `tenant_user` aplicáveis ao respectivo escopo são aceitos

#### Scenario: Literal legado após a contração
- **WHEN** uma escrita final tenta usar `PLATFORM_ADMIN`, `ADMIN`, `OPERATOR` ou `VIEWER`
- **THEN** o sistema rejeita o valor com erro de validação e não altera a autorização existente

### Requirement: TAG-02 — Resolução efetiva falha de modo restritivo
O sistema SHALL calcular autorização tenant a partir do usuário ativo, tenant atual ativo, modo de acesso, membership ativa, papel tenant e perfil ativo. Ausência, inconsistência ou inatividade de qualquer elemento obrigatório MUST resultar em negação. `tenant_admin` SHALL possuir todas as permissões tenant; `tenant_user` SHALL possuir somente as permissões efetivas de seu perfil.

#### Scenario: Tenant user sem perfil válido
- **WHEN** uma membership `tenant_user` não possui perfil, aponta para perfil inativo ou aponta para perfil de outro tenant
- **THEN** toda mutação e todo acesso não público daquele usuário são negados de modo fail-closed

#### Scenario: Tenant admin ativo
- **WHEN** um `tenant_admin` ativo acessa recurso pertencente ao tenant atual ativo
- **THEN** o resolver concede o baseline administrativo tenant sem exigir duplicação de cada permissão em um perfil

#### Scenario: Ausência de contexto
- **WHEN** uma autorização tenant é avaliada sem tenant atual resolvido
- **THEN** o sistema nega a autorização e não consulta nem enumera dados fiscais

### Requirement: TAG-03 — Catálogo controlado e perfis isolados
O sistema SHALL manter um catálogo global e versionável de chaves de permissão conhecidas e SHALL permitir que `tenant_admin` crie perfis contendo apenas chaves ativas e delegáveis desse catálogo. Cada perfil SHALL pertencer a um único tenant, SHALL possuir nome único dentro dele e MUST NOT conceder capacidades `platform.*` ou invariantes reservadas a `tenant_admin`.

#### Scenario: Criação de perfil válido
- **WHEN** um `tenant_admin` cria um perfil com nome único e chaves delegáveis do catálogo
- **THEN** o perfil é persistido no tenant atual e fica disponível apenas para memberships desse tenant

#### Scenario: Chave arbitrária ou reservada
- **WHEN** a requisição inclui uma chave inexistente, inativa, `platform.*` ou marcada como não delegável
- **THEN** o sistema responde `422` sem criar ou alterar parcialmente o perfil

#### Scenario: Mesmo nome em tenants diferentes
- **WHEN** dois tenants criam um perfil com o mesmo nome
- **THEN** ambos são aceitos e permanecem isolados por tenant

### Requirement: TAG-04 — Perfil obrigatório e integridade da membership
Toda membership `tenant_user` ativa SHALL referenciar exatamente um perfil de permissão ativo do mesmo tenant. Membership `tenant_admin` SHALL ter perfil nulo. A exclusão ou desativação de perfil com memberships ativas MUST ser bloqueada até reatribuição ou desativação explícita dos usuários afetados.

#### Scenario: Atribuição cruzada
- **WHEN** um ator tenta atribuir a uma membership um perfil pertencente a outro tenant
- **THEN** o sistema não revela dados do perfil-alvo e rejeita a operação sem alterar a membership

#### Scenario: Mudança para tenant admin
- **WHEN** um `tenant_admin` promove uma membership permitida de `tenant_user` para `tenant_admin`
- **THEN** o perfil é removido atomicamente e a nova autoridade passa a valer na requisição seguinte

#### Scenario: Perfil ainda utilizado
- **WHEN** um perfil possui ao menos uma membership ativa e alguém solicita sua exclusão
- **THEN** o sistema responde `409` com contagem sanitizada de vínculos e não exclui o perfil

### Requirement: TAG-05 — Limites do tenant admin
`tenant_admin` SHALL administrar usuários, perfis, permissões, configurações, empresas e módulos somente no tenant atual. Ele MUST NOT criar tenant, criar ou promover `platform_admin`, acessar o plano global, alterar ciclo de vida do tenant ou operar recurso pertencente a outro tenant.

#### Scenario: Administração local autorizada
- **WHEN** um `tenant_admin` cria um perfil ou uma membership no tenant atual
- **THEN** a operação é autorizada após validações de integridade, limites comerciais e auditoria aplicáveis

#### Scenario: Tentativa de criar platform admin
- **WHEN** um `tenant_admin` envia `platform_admin` por endpoint tenant ou chama endpoint de administradores da plataforma
- **THEN** o sistema responde `403` e não cria membership global

#### Scenario: Alvo de outro tenant
- **WHEN** um `tenant_admin` usa o identificador de usuário, perfil, cliente ou empresa de outro tenant
- **THEN** o sistema responde sem confirmar a existência do alvo e não modifica dados

### Requirement: TAG-06 — Criação delegada sem elevação
Um `tenant_user` MAY criar outro usuário somente quando seu perfil contiver a permissão semântica de criação de usuários. Nesse caso, o alvo MUST ser `tenant_user`, o perfil atribuído MUST pertencer ao tenant atual e suas permissões MUST formar subconjunto das permissões efetivas delegáveis do ator. `tenant_user` MUST NOT criar ou promover `tenant_admin`, administrar perfis ou criar `platform_admin`.

#### Scenario: Delegação dentro do subconjunto
- **WHEN** um `tenant_user` com permissão de criação convida outro `tenant_user` e atribui perfil cujas capacidades estão contidas nas suas
- **THEN** a membership pendente é criada no tenant atual e o fluxo de ativação é iniciado

#### Scenario: Perfil mais poderoso
- **WHEN** o perfil solicitado contém ao menos uma permissão delegável que o ator não possui
- **THEN** o sistema responde `403` sem criar usuário, membership ou ativação parcial

#### Scenario: Tentativa de elevação de papel
- **WHEN** um `tenant_user` tenta criar ou promover `tenant_admin` ou `platform_admin`
- **THEN** o sistema nega a operação independentemente dos campos enviados pelo cliente

### Requirement: TAG-07 — Proteção do último tenant admin
O sistema MUST impedir que operações concorrentes removam, desativem ou rebaixem o último `tenant_admin` ativo de um tenant operacional, exceto durante desprovisionamento controlado pelo plano global. A verificação SHALL ocorrer sob transação e lock apropriado.

#### Scenario: Único administrador local
- **WHEN** o único `tenant_admin` ativo tenta se desativar ou é rebaixado
- **THEN** o sistema responde `409` e mantém a membership inalterada

#### Scenario: Dois rebaixamentos concorrentes
- **WHEN** duas requisições concorrentes tentam rebaixar os dois últimos administradores
- **THEN** no máximo uma conclui e ao menos um `tenant_admin` permanece ativo

### Requirement: TAG-08 — Múltiplos administradores da plataforma
O sistema SHALL permitir múltiplas memberships globais `platform_admin`. Somente `platform_admin` ativo SHALL listar, criar, reativar ou desativar outros administradores da plataforma, e o sistema MUST impedir a remoção, desativação ou rebaixamento do último `platform_admin` ativo sob concorrência.

#### Scenario: Criação do segundo administrador
- **WHEN** um `platform_admin` ativo cria outro administrador com identidade válida
- **THEN** o sistema cria uma membership global distinta, inicia ativação segura e não cria tenant adicional implicitamente

#### Scenario: Ator tenant tenta usar endpoint global
- **WHEN** `tenant_admin` ou `tenant_user` chama o endpoint de administradores da plataforma
- **THEN** o sistema responde `403` sem revelar a coleção global

#### Scenario: Último administrador global
- **WHEN** uma ou mais transações tentam deixar a instalação sem `platform_admin` ativo
- **THEN** o sistema serializa a verificação, responde `409` às operações conflitantes e preserva ao menos um administrador ativo

### Requirement: TAG-09 — Contexto privilegiado explícito
`platform_admin` SHALL obter capacidades efetivas de `tenant_admin` somente após seleção explícita e auditada de um tenant ativo em contexto privilegiado separado da membership comum. A seleção MUST NOT criar membership fictícia e MUST limitar queries, policies, jobs e respostas ao tenant selecionado.

#### Scenario: Plataforma sem tenant selecionado
- **WHEN** um `platform_admin` autenticado está no plano global sem contexto tenant
- **THEN** ele administra tenants e administradores, mas endpoints tenant/fiscais falham de modo restritivo e nenhuma chamada SERPRO é iniciada

#### Scenario: Seleção do tenant A
- **WHEN** o administrador seleciona explicitamente o tenant A
- **THEN** ele recebe capacidades efetivas de `tenant_admin` somente em A e a aplicação sinaliza `access_mode=platform_privileged`

#### Scenario: Conta dual em modo de membership
- **WHEN** um `platform_admin` também é `tenant_user` em um tenant e entra pelo contexto comum dessa membership
- **THEN** valem as permissões do perfil real até que ele ative explicitamente o contexto privilegiado

#### Scenario: Conta dual em modo privilegiado
- **WHEN** a mesma conta ativa explicitamente o contexto privilegiado para esse tenant
- **THEN** passa a valer a paridade de `tenant_admin`, com auditoria do modo privilegiado e sem alterar sua membership real

### Requirement: TAG-10 — Autorização não vence invariantes operacionais
A implementação MUST NOT instalar `Gate::before` ou equivalente que retorne `true` irrestrito para `platform_admin`. Papel e permissão autorizam a intenção, mas MUST NOT vencer isolamento por tenant, lifecycle, assinatura, consentimento, feature flags, allowlists, limites, reconfirmações, kill switches ou guards de mutação e transporte externo.

#### Scenario: Kill switch ativo
- **WHEN** um `platform_admin` privilegiado ou `tenant_admin` possui permissão para uma operação cuja kill switch está ativa
- **THEN** a operação continua bloqueada antes de qualquer efeito ou chamada externa

#### Scenario: Recurso de outro tenant
- **WHEN** uma policy recebe modelo que não pertence ao `CurrentOffice`
- **THEN** a autorização falha mesmo que o ator seja `platform_admin`

#### Scenario: Gate global introduzido
- **WHEN** o teste de arquitetura encontra bypass global irrestrito para `platform_admin`
- **THEN** o gate de testes falha e impede o merge

### Requirement: TAG-11 — Auditoria de ações privilegiadas
Criação, alteração, ativação e desativação de administradores; seleção e limpeza de tenant; leitura ou mutação privilegiada; alteração de papel, perfil ou permissões SHALL gerar auditoria com ator real, tenant-alvo quando aplicável, ação, resultado, instante, modo de acesso e correlation ID. A auditoria MUST NOT registrar PFX, senha, token, XML completo ou payload fiscal sensível.

#### Scenario: Alteração de perfil
- **WHEN** um perfil tem permissões adicionadas ou removidas
- **THEN** a auditoria registra identificadores, chaves alteradas e ator, sem copiar dados sensíveis de clientes

#### Scenario: Ação privilegiada negada
- **WHEN** uma operação privilegiada é negada por tenant suspenso, ausência de contexto ou guard operacional
- **THEN** o resultado negado e seu código de motivo são auditados sem expor segredo

### Requirement: TAG-12 — Contrato HTTP canônico e janela compatível
As APIs de identidade e membership SHALL expor `platform_role`, `tenant_role`, `real_tenant_role`, `effective_permissions`, resumo do perfil, tenant atual e `access_mode`. Durante a janela expand–cutover, aliases legados MAY ser retornados como derivados somente para leitura; escritas canônicas SHALL usar os novos campos. Após a contração, os aliases SHALL ser removidos.

#### Scenario: Resposta canônica de tenant user
- **WHEN** `/api/v1/me` responde para `tenant_user`
- **THEN** retorna seu papel lowercase, perfil atual e conjunto ordenado de permissões efetivas do tenant atual

#### Scenario: SPA legada durante rollout
- **WHEN** a compatibilidade está habilitada e uma SPA anterior consulta identidade
- **THEN** os aliases documentados continuam coerentes com o estado canônico sem se tornarem fonte de autoridade

#### Scenario: Troca de tenant
- **WHEN** o usuário troca de tenant com request anterior ainda em voo
- **THEN** o cliente invalida estado e permissões anteriores e descarta resposta pertencente ao contexto anterior

### Requirement: TAG-13 — Cache, revogação e consistência imediata
Permissões efetivas MAY ser cacheadas somente com chave que inclua usuário, tenant e versão da membership/perfil. Alteração de membership, papel, perfil, permissões, tenant selecionado ou estado ativo SHALL invalidar o cache e SHALL valer no máximo na requisição autenticada seguinte. Sessões e tokens incompatíveis com mudança sensível SHALL ser revogados.

#### Scenario: Permissão removida
- **WHEN** um `tenant_admin` remove uma permissão do perfil atribuído a usuários ativos
- **THEN** nova requisição desses usuários já não possui a capacidade removida

#### Scenario: Cache de outro tenant
- **WHEN** o usuário alterna do tenant A para B
- **THEN** nenhuma permissão, lista, detalhe ou resposta cacheada de A é reutilizada em B

### Requirement: TAG-14 — Migração determinística com paridade
A migração SHALL mapear `PLATFORM_ADMIN` para `platform_admin`, `ADMIN` para `tenant_admin`, `OPERATOR` para `tenant_user` com perfil de sistema equivalente e `VIEWER` para `tenant_user` com perfil de sistema somente leitura. O backfill SHALL ser idempotente, SHALL registrar contagens sanitizadas e MUST bloquear cutover diante de valor desconhecido, vínculo órfão ou aumento de privilégio.

#### Scenario: Operador legado
- **WHEN** uma membership `OPERATOR` é migrada
- **THEN** ela se torna `tenant_user`, recebe o perfil de sistema Operador do mesmo tenant e preserva exatamente as capacidades anteriores

#### Scenario: Visualizador legado
- **WHEN** uma membership `VIEWER` é migrada
- **THEN** ela se torna `tenant_user`, recebe o perfil de sistema Visualizador do mesmo tenant e continua somente leitura

#### Scenario: Valor não reconhecido
- **WHEN** o preflight encontra papel fora da matriz conhecida ou membership inconsistente
- **THEN** o cutover é interrompido com relatório sanitizado e nenhuma suposição privilegiada é aplicada

#### Scenario: Reexecução
- **WHEN** o backfill é executado novamente após sucesso ou interrupção recuperável
- **THEN** não duplica perfis, permissões, memberships ou eventos de migração

### Requirement: TAG-15 — Frontend orientado a capacidades e corpus coerente
O frontend SHALL usar `effective_permissions` e os papéis canônicos apenas para apresentação e navegação, mantendo o backend como autoridade. As superfícies e specs ativas que hoje dependem de `ADMIN`, `OPERATOR` e `VIEWER` SHALL ser migradas para permissões semânticas. O repositório SHALL impedir novos literais legados fora de migrations, adaptadores temporários, fixtures de migração e documentação histórica explicitamente allowlisted.

#### Scenario: Botão oculto não autoriza API
- **WHEN** um usuário chama manualmente endpoint cujo botão está oculto e não possui a permissão necessária
- **THEN** o backend responde `403` mesmo que o cliente seja adulterado

#### Scenario: Permissão habilita superfície
- **WHEN** um `tenant_user` recebe permissão de criar usuários, exportar ou sincronizar
- **THEN** somente as rotas e ações correspondentes aparecem, sem elevar seu papel

#### Scenario: Literal novo no código
- **WHEN** um teste de arquitetura encontra referência nova aos papéis legados fora da allowlist de transição
- **THEN** o teste falha com arquivo e linha para correção

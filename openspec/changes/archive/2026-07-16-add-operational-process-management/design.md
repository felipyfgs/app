## Contexto

O MonitorHub já possui autenticação Fortify/Sanctum, memberships multi-escritório, papéis `ADMIN` / `OPERATOR` / `VIEWER`, cadastro de clientes, auditoria, Redis/Horizon, PostgreSQL e um painel Nuxt 4/Nuxt UI 4 baseado no template fixado em `0f30c09`. O documento `docs/PROCESSOS.md` descreve uma nova camada de trabalho recorrente que deve aproveitar essas fundações, e não criar cadastros, perfis, autenticação ou frontend paralelos.

O módulo atende funcionários do escritório contábil. Cada processo pertence a um cliente já cadastrado e a uma competência, e se desdobra em tarefas com prazo, responsável, departamento, evidências e histórico. Clientes finais não recebem conta nem acesso. Um mesmo CNPJ pode existir em escritórios distintos, portanto toda consulta, relacionamento, job, arquivo, agregação, lock e exportação precisa permanecer no escritório da sessão.

Os stakeholders são executores (`OPERATOR`), coordenadores/gestores operacionais (`ADMIN` como persona no MVP), usuários de consulta (`VIEWER`) e administradores responsáveis por departamentos e modelos. `PLATFORM_ADMIN` continua no plano de controle e não herda acesso ao conteúdo operacional dos tenants.

## Objetivos / Não-objetivos

**Objetivos:**

- Modelar departamentos, modelos reutilizáveis, processos por competência e tarefas executáveis sem duplicar `Client`, `User`, `Office` ou membership.
- Gerar processos de forma previsível, revisável, idempotente e segura para um ou vários clientes.
- Oferecer uma fila diária determinística, estados coerentes, evidências obrigatórias e trilha auditável.
- Entregar consultas server-side, calendário, dashboard, exportação e ações em lote com isolamento tenant e autorização por papel.
- Estender o frontend atual copiando os arquétipos do template de referência e adaptando apenas domínio, navegação, APIs, permissões e estados.
- Preservar os módulos fiscais, o dashboard de saúde e todas as restrições de segredos já existentes.

**Não-objetivos:**

- Criar novos papéis de segurança, portal de contribuinte ou login de cliente final.
- Gerar processos autonomamente por IA, sugerir responsáveis ou balancear carga automaticamente.
- Disparar tarefas a partir de eventos SERPRO/ADN/SEFAZ, alterar cursores ou executar mutações fiscais.
- Implementar calendário de feriados, SLA por dias úteis, dependências em grafo entre tarefas, chat, notificações externas ou aplicativo nativo.
- Criar BI, controle financeiro, automações externas ou permissões por campo.
- Permitir edição ou exclusão de histórico para simular que uma transição não ocorreu.

## Decisões

### 1. O módulo inteiro pertence ao plano de dados do tenant

Todas as novas tabelas terão `office_id` obrigatório e índices iniciados pelo tenant. Não haverá nova tabela global nem atalho pelo plano de controle. `PLATFORM_ADMIN` não poderá consultar processos, tarefas, comentários, evidências, métricas ou exports sem uma membership tenant válida e um escritório explicitamente selecionado.

As queries usarão o escopo já adotado pelo `BelongsToOffice`/`CurrentOffice`. IDs de `office_id` recebidos em query string ou payload serão ignorados ou rejeitados e nunca serão usados como autoridade. Relacionamentos com cliente, departamento e membership serão validados no mesmo escritório antes da escrita. Para relações críticas serão usados índices/constraints compostos quando viável, complementados por policies e testes negativos de isolamento.

Alternativa rejeitada: compartilhar modelos de processo globalmente entre escritórios. Isso misturaria conteúdo comercial/operacional de tenants e exigiria um ciclo de publicação e cópia que não faz parte do MVP.

### 2. Usuários, clientes e papéis existentes serão reutilizados

O responsável de processo ou tarefa referenciará a linha de membership em `office_user`, não apenas `users.id`, para representar inequivocamente a pessoa naquele escritório. A membership receberá `work_department_id` opcional, permitindo um departamento primário por escritório sem alterar a identidade global do usuário.

Autorização do MVP:

- `ADMIN`: administra departamentos e modelos; cria e gera processos; reatribui, altera prazos, dispensa e executa ações em lote; consulta todo o escritório.
- `OPERATOR`: consulta o trabalho do escritório; cria processos; executa tarefas atribuídas à sua membership; pode assumir tarefa sem responsável do seu departamento; comenta e anexa evidências; não administra modelos/departamentos nem reatribui trabalho de terceiros.
- `VIEWER`: consulta filas, processos, calendário e indicadores, sem mutações nem download de evidência quando a policy do recurso não permitir.

Evidências poderão ser lidas por memberships ativas do mesmo escritório que tenham acesso à tarefa; `VIEWER` permanece somente leitura, inclusive sem remover arquivos. Ações administrativas continuarão sujeitas aos controles de sessão e TOTP já aplicáveis a `ADMIN`.

Alternativa rejeitada: criar papéis `GESTOR` e `EXECUTOR`. O repositório fixa três papéis e a necessidade pode ser atendida por policies e vínculo de atribuição sem migrar toda a matriz de acesso.

### 3. Modelo relacional explícito e snapshots imutáveis de geração

O núcleo será composto por:

- `work_departments`: nome, sigla, cor, ativo e `office_id`.
- `process_templates`: nome, descrição, departamento padrão, regra padrão de prazo do processo, ativo, `lock_version`, criador e `office_id`.
- `process_template_tasks`: ordem, título, descrição, departamento/responsável padrão, regra de prazo, obrigatoriedade, criticidade e exigência de evidência.
- `process_generation_batches` e `process_generation_items`: preview canônico, conflitos, hash/idempotency key, versão do modelo, expiração, estado e resultado por cliente.
- `operational_processes`: cliente, modelo opcional, batch opcional, origem, título, descrição, competência, prazo, prazo-meta, indicador de multa, departamento, responsável, estado derivado, snapshot do modelo e `lock_version`.
- `operational_tasks`: processo, ordem, título, descrição, estado, prazo, prazo-meta, departamento, responsável, obrigatoriedade, criticidade, exigência de evidência, motivo de impedimento, timestamps de início/conclusão e `lock_version`.
- `operational_comments`: comentário append-only associado exatamente a um processo ou tarefa, autor e `office_id`.
- `operational_task_evidences`: metadados do arquivo, hash, tamanho, MIME, objeto opaco do cofre, autor, tarefa e `office_id`.

Processos gerados guardarão o snapshot necessário do modelo e tarefas. Alterar um modelo não reescreverá instâncias já criadas. Exclusões funcionais serão desativação/arquivamento quando existir histórico; não haverá cascata que apague silenciosamente execução, evidência ou auditoria.

Para geração por modelo haverá unicidade por `office_id + template_id + client_id + competence` nas instâncias de origem `TEMPLATE`. Processos manuais poderão coexistir porque não usam essa chave. A integridade será garantida no PostgreSQL e não apenas por consulta prévia.

Alternativa rejeitada: guardar todo o processo e suas tarefas em JSON. O uso intensivo de filtro, atribuição, calendário, agregação, concorrência e auditoria exige linhas relacionais e índices próprios.

### 4. Competência, prazo e estado serão conceitos separados

Competência será representada canonicamente como mês (`YYYY-MM`). Prazos serão datas civis calculadas no timezone do escritório; `offices` receberá timezone válido, inicialmente `America/Sao_Paulo` para registros existentes. O calculador de prazos será um serviço puro e testável.

Regras de modelo suportadas no MVP:

- dia fixo do mês da competência, ajustando dia inexistente para o último dia do mês;
- quantidade de dias corridos após o início da competência;
- quantidade de dias corridos antes do prazo do processo.

Se um modelo usar uma regra dependente do prazo do processo e não possuir prazo padrão calculável, a geração exigirá o prazo no pedido e o preview bloqueará confirmação enquanto ele faltar. Calendário de feriados e dias úteis fica fora do MVP.

O ciclo da tarefa será `A_FAZER`, `EM_PROGRESSO`, `IMPEDIDA`, `CONCLUIDA` ou `DISPENSADA`. O estado materializado do processo será recalculado transacionalmente após cada mudança de tarefa:

- `A_FAZER` enquanto nenhuma tarefa estiver iniciada;
- `EM_PROGRESSO` quando houver execução e nenhuma condição crítica de impedimento;
- `IMPEDIDO` quando existir tarefa crítica impedida;
- `CONCLUIDO` quando todas as tarefas obrigatórias estiverem concluídas ou dispensadas.

“Atrasado”, “em multa”, “sem prazo” e “sem responsável” serão dimensões de risco calculadas, não estados mutuamente exclusivos. Assim uma tarefa pode estar `EM_PROGRESSO` e, simultaneamente, atrasada e em multa. O prazo efetivo da fila será o prazo da tarefa, com fallback para o prazo do processo.

Alternativa rejeitada: persistir `ATRASADO` e `EM_MULTA` no mesmo enum de lifecycle. Isso perderia combinações válidas e exigiria jobs de virada de data apenas para manter o estado coerente.

### 5. Preview persistido e confirmação idempotente antecedem toda geração em lote

O servidor criará um batch `PREVIEWED` a partir de modelo, competência, clientes e eventuais overrides. Cada item conterá o cálculo de processo/tarefas, responsáveis elegíveis, alertas e conflito de duplicidade. O preview retornará um identificador opaco, hash do payload, `lock_version` do modelo e expiração curta.

Na confirmação, o serviço validará novamente escritório, membership, clientes ativos, versão do modelo e duplicidades. Preview expirado ou modelo alterado exigirá nova prévia. A confirmação mudará o batch uma única vez para `QUEUED`; retries usarão a mesma idempotency key. O job processará itens em chunks e cada item criará processo e tarefas em uma transação. Constraint única resolverá corridas concorrentes como `SKIPPED_DUPLICATE`, sem duplicar trabalho.

Geração manual cria uma única instância diretamente, mas ainda usa validação de tenant, `lock_version` e auditoria. Geração automática por scheduler não faz parte desta change.

Alternativa rejeitada: o frontend calcular prazos e enviar todas as tarefas prontas. Isso permitiria adulteração, divergência de timezone e inconsistência entre preview e persistência.

### 6. Transições e operações em lote serão centralizadas em serviços de domínio

`OperationalTaskTransitionService`, `OperationalProcessGenerationService` e `OperationalWorkBulkService` concentrarão invariantes; controllers apenas validarão DTOs, autorizarão e serializarão recursos. Toda mutação verificará `lock_version` para impedir perda silenciosa por duas abas ou usuários concorrentes.

Regras centrais:

- iniciar registra ator e horário;
- impedir exige motivo não vazio;
- concluir exige ao menos uma evidência quando `requires_evidence=true`;
- dispensar exige `ADMIN` e justificativa;
- reabrir tarefa concluída/dispensada exige `ADMIN`, justificativa e novo evento de auditoria;
- comentário é append-only;
- remoção de evidência exige autorização, justificativa e auditoria, e não pode deixar tarefa concluída descumprindo a exigência;
- lote é limitado, valida todos os IDs no mesmo tenant e executa de forma atômica; conflito em qualquer item não produz atualização parcial.

O `AuditLogger` existente receberá ações tipadas e contexto sanitizado com valores anteriores/novos estritamente necessários. O histórico exibido será uma projeção allowlisted de `audit_logs`, comentários e evidências; não retornará payload bruto de auditoria.

Alternativa rejeitada: atualizar status diretamente por CRUD genérico. Transições possuem invariantes, autorização e efeitos derivados que precisam de um único caminho.

### 7. Evidências usarão o cofre existente, com finalidade e AAD próprias

Será acrescentado `SecureObjectPurpose::OperationalTaskEvidence`. O upload validará tamanho configurável (20 MiB por padrão), MIME detectado no servidor, nome sanitizado e allowlist inicial de PDF, PNG, JPEG e texto simples. Os bytes serão gravados pelo `SecureObjectStore` com AAD contendo `purpose`, `office_id`, `task_id`, `sha256` e identificador da evidência.

O banco guardará somente o identificador opaco e metadados. Download será streaming autenticado com `Content-Disposition: attachment`, autorização tenant/task e sem URL pública permanente. API, log, exportação CSV e histórico nunca retornarão `vault_object_id`, caminho físico, chave mestra, PFX, PEM, tokens ou conteúdo do arquivo.

Alternativas rejeitadas: URL pública em bucket, caminho de storage no resource e Base64 em JSON. Todas ampliariam exposição, memória e risco de enumeração. Um storage S3 poderá futuramente implementar a mesma interface sem mudar o domínio.

### 8. A fila usará buckets determinísticos, não um score opaco

`OperationalQueueQuery` produzirá buckets ordenados conforme o produto:

1. em multa;
2. atrasada;
3. vence hoje;
4. vence nos próximos três dias;
5. impedida;
6. sem responsável;
7. demais abertas.

Dentro do bucket, a ordenação será por prazo efetivo ascendente, prioridade crítica, criação e ID. Itens concluídos/dispensados só aparecem na aba histórica. O escopo padrão de `OPERATOR` será tarefas atribuídas à própria membership mais tarefas sem responsável do seu departamento; `ADMIN` poderá alternar para todo o escritório e `VIEWER` consultará sem ações. Datas serão avaliadas no timezone do escritório e a resposta incluirá bucket e motivos allowlisted, permitindo que frontend e export usem a mesma semântica.

Alternativa rejeitada: score numérico enviado sem explicação. Buckets estáveis são auditáveis, testáveis e respondem diretamente por que um item aparece primeiro.

### 9. Consultas, métricas e exports serão server-side e tenant-scoped

Listas de processos e tarefas aceitarão paginação, ordenação e filtros por período, competência, cliente, departamento, responsável, lifecycle e risco. Filtros relevantes permanecerão na URL do SPA. O calendário receberá agregados diários e, sob demanda, a lista paginada de um dia; não carregará toda a base no navegador.

O dashboard acrescentará um bloco próprio de trabalho operacional com totais, atrasadas, em multa, vencem hoje, em progresso, concluídas e sem responsável, além de agrupamentos por departamento/responsável e maiores riscos. Esses dados permanecerão distinguíveis dos KPIs fiscais, da inbox operacional e do backup atuais. Todas as respostas incluirão `generated_at` e filtros efetivos.

Exportação CSV será um job Horizon com seleção congelada de filtros, arquivo privado, estado, expiração e download autorizado, reutilizando os padrões do fluxo `Export` existente sem misturar ZIP/XML fiscal. Colunas usarão texto neutro e não incluirão comentários, bytes de evidência ou conteúdo fiscal.

### 10. A UI copiará arquétipos específicos do template fixado

O shell atual será preservado. A implementação seguirá esta matriz:

| Superfície | Rota proposta | Arquétipo/fonte do template |
|---|---|---|
| Minha fila | `/work` | mestre–detalhe de `app/pages/inbox.vue` + `components/inbox/*` |
| Processos/tarefas | `/work/processes` | lista administrativa de `app/pages/customers.vue` |
| Calendário | `/work/calendar` | shell home de `app/pages/index.vue`; corpo de calendário específico do domínio |
| Detalhe do processo | `/work/processes/[id]` | settings/seções de `app/pages/settings.vue` e `settings/*` |
| Modelos | `/work/templates` | lista administrativa de `app/pages/customers.vue` |
| Editor/preview | `/work/templates/[id]` e modal de geração | settings + `components/customers/AddModal.vue` |
| Departamentos | `/admin/departments` | lista em settings de `settings/members.vue` |
| KPIs operacionais | `/` | home de `app/pages/index.vue` + `components/home/*` |

O frontend trocará mocks por `useApi`, usará paginação server-side, estados loading/vazio/erro/403/409/422, dados do escritório da sessão e ações filtradas por policy. A fila será o primeiro destino de navegação e o redirect pós-login de `OPERATOR`; `ADMIN` continuará chegando ao dashboard gerencial. Em mobile, o detalhe da tarefa será `USlideover` como no inbox. O calendário manterá `UDashboardPanel`, `UDashboardNavbar` e `UDashboardToolbar`; somente o corpo sem arquétipo equivalente será novo.

Não será copiado `TeamsMenu`, `server/api/*`, paginação client-side, toast de marketing ou qualquer seletor livre de escritório. Props/slots incertos serão confirmados no MCP Nuxt UI durante apply; questões de routing SPA serão confirmadas no MCP Nuxt sem introduzir SSR/Node em produção.

### 11. Contratos e testes serão entregues antes da superfície completa

A API terá recursos tipados e endpoints versionados sob `/api/v1/work/*`. Testes de backend cobrirão migrations/constraints, policies, isolamento cruzado, cálculo de prazo, transições, concorrência, idempotência, fila, agregados, evidências, export e sanitização. Testes frontend cobrirão composables, estados de rota/filtro, permissões e componentes; Playwright validará os fluxos críticos e a responsividade desktop/mobile.

Testes de isolamento criarão o mesmo CNPJ, competência e nomes em dois escritórios e tentarão leitura, mutação, download, lote, geração e export cruzados. Scanners de payload verificarão ausência de `vault_object_id`, caminhos, PFX, PEM, tokens, Termo XML e bytes de evidência.

## Riscos / Trade-offs

- **[Fila muito ampla para operadores]** → o padrão limita a membership atual e itens não atribuídos do seu departamento; filtros explícitos e policies impedem mutação indevida.
- **[Corrida entre preview, edição do modelo e confirmação]** → `lock_version`, hash, expiração, revalidação e unicidade no banco tornam a confirmação segura.
- **[Datas incorretas em virada de dia]** → timezone por escritório e datas civis calculadas no backend; frontend apenas apresenta valores e filtros efetivos.
- **[Agregações degradam com volume]** → índices por `office_id`, status, prazo, responsável, departamento e competência; paginação/agregação server-side; projeções adicionais somente após medir necessidade.
- **[Arquivos maliciosos ou grandes]** → allowlist, MIME detectado, teto configurável, download como attachment e cofre cifrado. Antivírus dedicado fica como gate operacional futuro se o piloto aceitar formatos além da allowlist inicial.
- **[Auditoria genérica virar fonte difícil de consultar]** → ações e contexto allowlisted, índices por tenant/subject/data e resource específico de timeline; não duplicar um segundo event store no MVP.
- **[Dashboard ficar visualmente sobrecarregado]** → bloco operacional próprio, filtros coerentes e deep-links; sinais fiscais/infra não serão fundidos em uma única contagem ambígua.
- **[Template não possui calendário]** → preservar shell/navbar/toolbar do arquétipo home e limitar código novo ao corpo de domínio, validado por checklist visual e testes responsivos.
- **[Roll back após criação de evidências]** → desabilitar rotas/nav/jobs sem apagar tabelas ou objetos; dados permanecem recuperáveis quando o código for reativado.

## Plano de migração

1. Congelar contratos e testes de linha de base de auth, tenant switch, clientes, auditoria, export e dashboard atuais.
2. Adicionar enums, policies e serviços puros de competência, prazo, prioridade e transição, com testes unitários.
3. Aplicar migrations do plano de dados, constraints/índices tenant-scoped e timezone do escritório; validar rollback em banco sem dados reais.
4. Implementar departamentos/modelos e geração preview-confirm idempotente; liberar primeiro em testes e seed local.
5. Implementar processos, tarefas, comentários, cofre de evidências e timeline auditada.
6. Implementar fila, consultas, calendário, agregações, lote e export CSV, acompanhados por testes negativos multi-escritório.
7. Copiar os arquétipos do template, integrar navegação/API/permissões e validar lint, typecheck, Vitest e Playwright em desktop/mobile.
8. Executar backup/restore drill, smoke test restrito com dois escritórios fictícios e habilitar piloto sem automações fiscais.

Rollback de aplicação desabilita as novas rotas, navegação e workers, preserva tabelas e objetos do cofre e reverte apenas código. Migrations destrutivas só poderão ser revertidas em ambiente sem dados; após piloto, rollback de schema será forward-only e nunca apagará evidências/auditoria automaticamente.

## Questões em aberto

- Definir com o piloto a retenção e a quota por escritório para evidências e exports; o desenho já exige limites configuráveis e métricas de uso.
- Avaliar em change futura se departamentos múltiplos por membership são necessários; o MVP adota um departamento primário.
- Avaliar depois do MVP regras de dias úteis/feriados e gatilhos vindos do monitoramento fiscal, sem incluí-los implicitamente nesta implementação.


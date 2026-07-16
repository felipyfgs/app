## Contexto

O módulo criado por `add-operational-process-management` já possui tabelas tenant-scoped, policies, serviços de transição/geração, endpoints `/api/v1/work/*`, tipos/composable no frontend e cinco páginas sob `/work`. A inspeção do ambiente local mostrou, porém, `0` processos e `0` tarefas no escritório `demo` selecionado pelos usuários `operador@example.com`, `admin@example.com` e `viewer@example.com`.

Existe um `OperationalWorkDemoSeeder`, mas ele cria `demo-work-alpha` e `demo-work-beta`, não recebe o office `demo` já preparado pelo `DatabaseSeeder` e também não é chamado por ele. As telas consomem a API real, mas o tenant da sessão não possui registros; por isso a interface cai corretamente em um vazio que não serve como demonstração nem como baseline visual.

As páginas atuais também são apenas uma primeira aproximação dos arquétipos. `/work` concentra lista e detalhe dentro de um único `UDashboardPanel`, o calendário é uma grade mensal simples sem visões Semana/Dia e processos/modelos ainda não oferecem toda a hierarquia, densidade e divulgação progressiva necessárias.

A ordem de autoridade desta change é:

1. domínio, `AGENTS.md` e artefatos de `add-operational-process-management`;
2. Nuxt UI Dashboard Template fixado em `.reference/nuxt-dashboard-template` no commit `0f30c09`;
3. skill/MCP Nuxt UI para a API de `UDashboardPanel`, `UCalendar`, `UTabs`, `UProgress`, overlays, formulários e acessibilidade;
4. skill/MCP Nuxt para rotas file-based, query reproduzível e SPA;
5. APIs Laravel reais, memberships, permissões e office da sessão.

A imagem da Agenda Makro contribui com alternância `Dia/Semana/Mês`, densidade por dia e um rail lateral com minicalendário e listas. Ela não é fonte de identidade visual nem de estrutura do shell. O MCP confirma que `UCalendar` seleciona datas e intervalos; não é um scheduler de compromissos. O domínio atual possui datas de prazo, não `starts_at`/`ends_at`, portanto nenhuma grade horária será simulada.

Toda informação operacional pertence ao plano de dados do tenant e exige `office_id`. Esta change não cria nem altera tabelas do plano de controle global, contrato SERPRO, catálogo/preços, fatura consolidada ou memberships de plataforma.

## Objetivos / Não-objetivos

**Objetivos:**

- Preencher o office `demo` local/testing com uma massa operacional rica, idempotente e sanitizada usando persistência e APIs reais.
- Tornar `/work` uma fila mestre–detalhe eficiente para execução diária e navegação por teclado.
- Entregar calendário operacional Mês/Semana/Dia baseado exclusivamente em prazos reais.
- Completar listas, detalhes e fluxos de processos/modelos com os arquétipos exatos do template.
- Apresentar carga e progresso por departamento com métricas reais e deep-links reproduzíveis.
- Cobrir estados preenchido, vazio legítimo, loading, refresh, erro, conflito, somente leitura, mobile e desktop.
- Garantir isolamento multi-escritório e ausência de dados/segredos reais em fixtures e artefatos.

**Não-objetivos:**

- Criar outro starter, design system, sidebar, marca ou cópia visual do Makro.
- Introduzir agenda de reuniões, horários, recorrências, feriados, dias úteis ou sincronização com calendários externos.
- Alterar enums, regras de transição, políticas de evidência, concorrência otimista ou geração já definidos no domínio operacional, salvo correções necessárias para cumprir contratos existentes.
- Usar arrays fake em Vue, `server/api` do template, interceptação de runtime ou endpoint especial de demo.
- Criar novos papéis, portal de contribuinte ou acesso tenant para `PLATFORM_ADMIN`.
- Alterar canais fiscais, cursores, certificados, cofre ou plano de controle global.

## Decisões

### 1. Esta change especializa, sem redefinir, as changes operacionais e de UI existentes

`add-operational-process-management` continua sendo a autoridade de domínio e contratos. `refactor-complete-dashboard-ui-ux` continua sendo o guarda-chuva transversal de todas as páginas. Esta change executará um incremento focado na família `/work` e deverá registrar qualquer tarefa coincidente como satisfeita por evidência compartilhada, sem manter duas implementações concorrentes.

Alternativa rejeitada: editar apenas a change ampla. Isso esconderia a correção objetiva do tenant demo e dificultaria aplicar/verificar o workspace operacional de forma independente.

### 2. Dados demonstrativos serão registros reais do plano de dados

O seeder usará os mesmos models, constraints e serviços que alimentam os endpoints do produto. O frontend nunca terá um caminho `if demo then [...]`. A sessão local verá dados porque o `DatabaseSeeder` chamará o seeder operacional depois de criar office, assinatura, usuários e catálogo demo.

O seeder aceitará o office `demo` como alvo lógico, reutilizará suas memberships e criará departamentos, clientes, modelos, processos, tarefas, comentários e, quando necessário, uma evidência textual sintética pelo `SecureObjectStore`. Qualquer arquivo demonstrativo conterá aviso explícito de ausência de validade fiscal.

Alternativa rejeitada: fixtures somente no Playwright. Elas ajudam testes isolados, mas não resolvem `http://localhost:3000/work` para a sessão humana nem exercitam persistência, resources e policies reais.

### 3. O seeder será fail-closed, idempotente e ancorado no tempo

O seeder MUST abortar fora de `local`/`testing`. Os registros usarão chaves naturais estáveis e namespace identificável, com `updateOrCreate`/upsert transacional em vez de duplicação por execução. A data de referência virá de `DEMO_WORK_ANCHOR_DATE` quando definida; caso contrário, será o “hoje” civil no timezone do office no momento do seed. Testes e screenshots fixarão a variável para resultados reproduzíveis.

Rerodar o seeder deverá reconciliar somente registros marcados como demonstrativos do office alvo, atualizar datas relativas e preservar registros operacionais não demonstrativos. Um relatório final apresentará contagens por entidade sem imprimir payloads ou segredos.

Alternativa rejeitada: `now()` disperso em cada criação com `firstOrCreate`. Isso produz datas inconsistentes e impede que uma nova execução reposicione o cenário em relação ao dia atual.

### 4. A massa cobrirá o vocabulário operacional, não apenas volume

O office `demo` terá ao menos quatro departamentos (`Fiscal`, `Pessoal`, `Contábil`, `Societário`), memberships com papéis existentes, múltiplos clientes, modelos e processos em competências adjacentes. As tarefas cobrirão `A_FAZER`, `EM_PROGRESSO`, `IMPEDIDA`, `CONCLUIDA` e `DISPENSADA`, além de prazos vencido/hoje/próximos dias/sem prazo, risco de multa, crítica, sem responsável, com comentário e com evidência sintética.

Um office sentinela separado reutilizará pelo menos um mesmo CNPJ e rótulo de cliente para provar que listagens, agregados, calendário, busca, detalhes e downloads não atravessam tenant. Os usuários da sessão `demo` nunca receberão membership nesse sentinela.

Alternativa rejeitada: centenas de registros homogêneos. Volume sem variedade não exercita decisões visuais, permissões nem riscos.

### 5. `/work` copiará o split real do arquétipo Inbox

A página será dividida em dois `UDashboardPanel` irmãos:

- lista resizable derivada de `pages/inbox.vue`/`InboxList.vue`, com navbar, contagem, tabs, busca/filtros, prioridade, prazo, cliente, processo, departamento e responsável;
- detalhe derivado de `InboxMail.vue`, com cabeçalho, metadados, ações autorizadas, descrição, riscos, checklist/contexto, comentários, evidências e timeline;
- estado neutro com ícone quando não há seleção em desktop;
- `USlideover`/drawer sob `lg`, com foco devolvido ao item de origem;
- atalhos `ArrowUp`/`ArrowDown` mantendo o item selecionado visível.

Tabs, filtros e `task` selecionada serão representados na query string quando precisarem sobreviver a reload/voltar. O office continuará vindo exclusivamente da sessão.

Alternativa rejeitada: manter lista e detalhe dentro do body de um único painel. Isso perde resize, headers independentes e a forma reconhecível do template.

### 6. O calendário adaptará a Agenda Makro aos dados existentes

`/work/calendar` usará um painel principal e um rail lateral redimensionável. A navbar terá navegação anterior/hoje/próximo e `UTabs` para `Mês`, `Semana` e `Dia`; a toolbar terá filtros por departamento, responsável, cliente, status e risco.

- **Mês:** grade civil com contagem e indicadores de risco por data.
- **Semana:** sete lanes por dia, ordenadas por bucket/prazo, sem eixo de horas.
- **Dia:** fila detalhada da data, com as mesmas ações permitidas em `/work`.
- **Rail:** `UCalendar` como minicalendário e tabs de resumo (`Tarefas`, `Atrasadas`, `Concluídas`) da data selecionada.

A rota refletirá `view`, `date` e filtros. A API retornará apenas o intervalo necessário e DTOs tipados suficientes para renderizar labels, risco e contexto; não carregará toda a base no cliente.

Alternativa rejeitada: grade de 05h–19h como na captura Makro. Sem horário inicial/final, a posição vertical seria um dado inventado.

### 7. Processos e modelos conservarão os arquétipos Customers, Settings e AddModal

`/work/processes` copiará `pages/customers.vue`: utilitários no body, `UTable` com preset oficial, filtros server-side, badges semânticos, ações de linha e rodapé com total/paginação. `/work/processes/{id}` copiará `pages/settings.vue`, com seções reproduzíveis para `Resumo`, `Tarefas`, `Comentários/Evidências` e `Histórico`.

`/work/templates` usará a mesma lista e `AddModal.vue` para criação curta. Geração por modelo será um fluxo com `UStepper`: selecionar modelo/clientes, configurar competência/overrides, obter preview persistido do backend, confirmar e acompanhar batch. Erro de validação ou conflito preservará dados não sensíveis e nunca será convertido em sucesso local.

Alternativa rejeitada: editor e geração inteiros em uma única tabela/modal. O preview, conflitos e resultado possuem estado durável e exigem divulgação progressiva.

### 8. Contratos da API serão enriquecidos somente onde a UI precisa de verdade server-side

Antes de mudar payloads, será feito um inventário dos endpoints existentes. Filtros já aceitos serão reutilizados; dimensões ausentes serão adicionadas a queries tenant-scoped e resources allowlisted. O calendário poderá evoluir para um DTO de intervalo que inclua tarefa, processo, cliente, data efetiva, bucket/risco, departamento e responsável, mantendo paginação na visão diária.

O KPI por departamento evoluirá de simples total aberto para campos nomeados: abertas, concluídas no período, atrasadas, em multa, sem responsável e percentual de conclusão calculável. Agregados e listas usarão o mesmo escopo e data de corte. Nenhuma resposta retornará `office_id` como autoridade do cliente, caminhos, IDs de cofre ou conteúdo de evidência.

Alternativa rejeitada: calcular filtros, riscos e percentuais sobre a página já carregada. Isso diverge do total real e quebra escala.

### 9. Estados vazios serão honestos e o cenário demo não vazará para produção

Quando um office real não possuir processos, a UI apresentará vazio orientado à criação/geração somente para papel autorizado e um vazio de leitura para `VIEWER`. A UI não tentará semear dados. O ambiente local fica preenchido pelo setup explícito do backend; produção permanece totalmente independente de seeders/manifestos demo.

Falha de refresh manterá dados válidos anteriores e data da última atualização; falha inicial, 403, 409 e 422 terão tratamentos distintos. A troca explícita de membership invalidará seleção, cache e requests pendentes antes de renderizar o novo tenant.

Alternativa rejeitada: transformar qualquer vazio em dataset sintético. Isso esconderia problemas reais de onboarding e seria inseguro.

### 10. Aceite será funcional, visual e sanitizado

Playwright cobrirá `1440×900`, `390×844` e overflow em `360 px`, nos temas claro/escuro representativos. Haverá estados preenchido, vazio, loading, erro, refresh, viewer e overlay móvel. Baselines serão por zonas estáveis; datas usarão a âncora fixa de teste.

PHPUnit provará guard de ambiente, idempotência, integridade, tenant isolation e contracts. Vitest cobrirá mapeamento de estados, filtros/URL, permissões e componentes. Screenshots, traces, HTML e logs passarão por scanner de segredos e dados fiscais reais.

Alternativa rejeitada: aceitar a página apenas por screenshot feliz. Uma interface operacional depende de filtro, seleção, ação, permissão e retorno de erro.

## Riscos / Trade-offs

- [Sobreposição com changes ativas] → aplicar por família, revisar diffs antes de cada etapa e compartilhar evidências/tarefas sem sobrescrever trabalho local.
- [Seeder reconciliar registros editados manualmente] → namespace demo explícito, alvo restrito ao office `demo`, transação e atualização somente de registros marcados pelo manifesto.
- [Datas relativas tornarem baselines instáveis] → `DEMO_WORK_ANCHOR_DATE` obrigatória em testes visuais e cálculo único no início do seeder.
- [Massa demo violar invariantes de transição] → preferir serviços de domínio e cofre; quando criação direta for inevitável, validar os mesmos constraints e executar smoke tests completos.
- [Calendário ficar denso demais no mobile] → mês compacto, lista diária em drawer/slideover e filtros prioritários visíveis; nenhum overflow do documento.
- [Rail lateral reduzir área útil] → painel redimensionável/colapsável com largura mínima e migração para overlay no mobile.
- [Novos agregados divergirem da fila] → reutilizar `WorkRiskCalculator`, `QueueBucketResolver`, timezone e filtros server-side compartilhados.
- [Fixture sintética parecer dado fiscal válido] → nomes/rótulos demonstrativos, aviso de ausência de validade e bloqueio de chamadas externas/mutações fiscais.

## Plano de migração

1. Congelar contratos e baselines atuais de `/work`; registrar as lacunas contra os arquivos exatos do template.
2. Refatorar o seeder para o office `demo`, adicionar âncora temporal, guard, manifesto e testes de idempotência/tenancy.
3. Registrar o seeder no `DatabaseSeeder` local/testing e validar uma recriação limpa do banco e uma reexecução sem duplicatas.
4. Completar DTOs/filtros/agregados server-side necessários, preservando compatibilidade dos endpoints já consumidos.
5. Extrair componentes operacionais derivados do Inbox/Home/Customers/Settings e refatorar `/work` e calendário.
6. Refatorar processos, detalhe e modelos/geração.
7. Atualizar Home, navegação/deep-links, estados, permissões e troca de tenant.
8. Executar PHPUnit, Vitest, lint, typecheck, build SPA e Playwright funcional/visual com scanner de segredos.

Rollback de UI ocorrerá por rota, mantendo contratos compatíveis. O registro do seeder pode ser removido sem afetar produção; registros demo só poderão ser limpos por comando/rotina explicitamente limitada ao ambiente e namespace demonstrativo, nunca por migration destrutiva.

## Questões em aberto

- Nenhuma decisão de domínio está bloqueante. Antes do apply, apenas será confirmado se a evidência sintética deve ser criada pelo cofre real local ou por uma implementação de `SecureObjectStore` dedicada a testing; em ambos os casos a API pública e os requisitos de segurança permanecem os mesmos.

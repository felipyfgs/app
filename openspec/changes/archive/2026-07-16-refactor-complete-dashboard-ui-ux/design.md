## Context

O frontend é uma SPA Nuxt 4/Nuxt UI 4 com `ssr: false`, build estático, Nginx same-origin e API Laravel/Sanctum. O shell e várias superfícies já derivam do Nuxt UI Dashboard Template fixado em `.reference/nuxt-dashboard-template` no commit `0f30c09`, mas o produto acumula 51 arquivos de página criados em momentos diferentes. Há bons padrões locais — `UDashboardGroup`, tabelas rastreáveis ao template, mestre–detalhe, Settings, `UAuthForm`, fixtures e screenshots — porém a aplicação completa ainda não possui uma anatomia operacional uniforme.

A pesquisa visual do MakroWeb evidenciou padrões úteis para usuários contábeis experientes: contexto de empresa e período sempre próximo dos dados, alta densidade com hierarquia, filtros por campo, rodapé com contagens/totais, agenda em múltiplas escalas e carga por departamento. Também evidenciou padrões que não devem ser copiados: navegação por ícones sem rótulo, tipografia pequena, contraste baixo, dependência de cor, gráficos de pizza pouco informativos e excesso de controles simultâneos.

A ordem de autoridade permanece:

1. domínio, OpenSpec e `AGENTS.md`;
2. código fixado do Nuxt Dashboard Template para forma, slots e composição;
3. skill e MCP Nuxt UI para props, slots, acessibilidade, formulários e overlays;
4. skill e MCP Nuxt para Nuxt 4, rotas e SPA;
5. APIs reais Laravel, permissões e tenancy do produto.

O MCP Nuxt UI confirmou `UDashboard*`, `UTable`, `UCalendar`, `UStepper`, `UAuthForm` e `UFileUpload`. `UCalendar` seleciona datas; ele não é uma agenda temporal completa. Portanto, a visão semanal/diária operacional será uma composição do produto dentro do arquétipo Dashboard, usando `UCalendar` somente como seletor/minicalendário e sem inventar horários que não existem no domínio.

## Goals / Non-Goals

**Goals:**

- Realinhar todas as páginas ao arquétipo exato do template e registrar a origem por arquivo.
- Criar uma linguagem operacional única para contexto, período, filtros, status, ações, formulários, tabelas, overlays e estados assíncronos.
- Incorporar densidade e eficiência do MakroWeb sem copiar sua identidade visual.
- Tornar as páginas rápidas de escanear por profissionais contábeis, preservando acessibilidade, mobile e modo escuro.
- Manter o escritório ativo visível e separar claramente contexto do tenant, cliente/contribuinte, competência/período, ambiente e origem do dado.
- Evoluir calendário e dashboard operacional com dados já disponíveis e deep-links coerentes.
- Fazer cada arquivo de página passar por aceite estrutural, funcional, visual, responsivo, acessível e de segurança.

**Non-Goals:**

- Trocar o template, criar starter novo ou adotar a estética do MakroWeb.
- Criar uma agenda de reuniões com horários, feriados ou recorrências que o domínio operacional não modela.
- Reescrever regras de negócio, APIs ou banco apenas para preencher espaço visual.
- Transformar todos os componentes em wrappers genéricos ou apagar variações legítimas de domínio.
- Permitir troca livre de `office_id`, expor plano de controle global ou material fiscal/criptográfico sensível.
- Alterar cursores NSU/nNF, filas fiscais, gates de mutação ou cobertura oficial dos módulos.

## Decisions

### 1. O template continuará sendo a forma; MakroWeb será somente uma lente de informação

Cada página autenticada começará pelo arquivo exato do clone fixado: `index.vue` para Home, `customers.vue` para listas, `inbox.vue` para mestre–detalhe, `settings.vue` para seções, `AddModal.vue` para formulários curtos e `MembersList.vue` para listas em card. O código do MakroWeb não será copiado e suas imagens não entrarão no bundle.

As contribuições externas permitidas são exclusivamente:

- contexto visível de entidade e período;
- densidade de informação com alinhamento tabular;
- filtros próximos do dado filtrado;
- totalizações e contagem no rodapé;
- agenda em Dia/Semana/Mês;
- progresso e carga por departamento;
- fluxo operacional em etapas.

Alternativa rejeitada: reproduzir a barra lateral compacta e o chrome do MakroWeb. Isso romperia a fidelidade literal ao template, reduziria acessibilidade e criaria um segundo design system.

### 2. Anatomia única de página autenticada

Todas as páginas autenticadas usarão a seguinte ordem, omitindo somente blocos sem função real:

```text
UDashboardPanel
├── UDashboardNavbar
│   ├── sidebar collapse / voltar
│   ├── título e contexto curto
│   └── no máximo uma ação primária
├── UDashboardToolbar
│   ├── subnavegação ou contexto operacional
│   └── filtros globais da visão
└── body
    ├── feedback persistente / cobertura / origem
    ├── faixa utilitária local
    ├── conteúdo do arquétipo
    └── rodapé de contagem, total ou paginação
```

O escritório ativo permanece no shell. Cliente, competência, período, ambiente e origem aparecem na toolbar ou cabeçalho da superfície, conforme o domínio. Nenhum controle aceita `office_id` livre.

Alternativa rejeitada: criar um componente universal `PageShell`. Ele esconderia slots e dificultaria comparar a árvore de cada rota com o template. Serão compartilhados apenas componentes pequenos de conteúdo/contexto, nunca o `UDashboardPanel` inteiro.

### 3. Densidade será progressiva e semântica

Desktop terá densidade operacional equivalente a `customers.vue`, com nomes/valores principais legíveis e identificadores técnicos como informação secundária. Mobile preservará identidade, estado, prazo/valor e ação principal; dados secundários migrarão para detalhe, slideover ou expansão.

Filtros de uso frequente ficam visíveis; filtros avançados ficam em popover/slideover ou bloco recolhível. Uma tela não exibirá controles que não executam função real. Estados serão comunicados por texto, ícone e cor semântica.

Alternativa rejeitada: inserir uma linha de filtro em cada coluna de toda tabela. Isso reproduziria o excesso visual do concorrente e prejudicaria mobile. Filtros por coluna serão usados apenas quando a coluna representa um critério server-side frequente e inequívoco.

### 4. Tabelas manterão presets explícitos e estado server-side

`DASHBOARD_TABLE_UI`, `DENSE_DASHBOARD_TABLE_UI` e `COMPACT_DASHBOARD_TABLE_UI` continuam sendo as únicas variantes. Cada `UTable` mantém colunas/slots localmente. O rodapé da lista apresentará, conforme o contrato real:

- quantidade retornada e total;
- página e tamanho de página, quando offset/page;
- “carregar mais” e quantidade acumulada, quando cursor;
- totalizações monetárias ou operacionais somente quando calculadas no backend sobre o mesmo escopo;
- seleção e ação em massa somente quando autorizadas e funcionais.

Alternativa rejeitada: tabela universal e paginação client-side. O wrapper esconderia TanStack/slots e a paginação local quebraria escala e consistência.

### 5. Calendário operacional não simulará reuniões

`/work/calendar` terá `Mês`, `Semana` e `Dia` refletidos em estado navegável compatível com a política vigente da rota. A visão mensal mostra contagens por prazo; a semanal distribui tarefas por dia em lanes ordenadas, sem horários fictícios; a diária mostra fila detalhada, riscos e ações permitidas. Um painel lateral usa `UCalendar` como minicalendário e lista tarefas da data selecionada.

Filtros: departamento, responsável, cliente, status e risco. As cores indicam semântica de prazo/risco, não apenas departamento. O detalhe reutiliza o mestre–detalhe de `/work` no desktop e slideover/drawer no mobile.

Alternativa rejeitada: grade horária idêntica à Agenda Makro. O domínio atual possui prazos, não agenda de compromissos com horário inicial/final.

### 6. Dashboard por departamento usará progresso compacto, não pizza

O bloco operacional da Home mostrará por departamento: abertas, concluídas, atrasadas, em multa, sem responsável e proporção de conclusão. A visualização será por cartões compactos e barras `UProgress`, com deep-link para a fila/lista correspondente. Sinais fiscais, backup, saúde e trabalho permanecem em áreas nomeadas e nunca são somados.

Alternativa rejeitada: grandes gráficos de pizza por departamento. Eles consomem espaço, dificultam comparar áreas e tornam percentuais extremos pouco legíveis.

### 7. Fluxos de importação e geração terão divulgação progressiva

Importação XML/ZIP, criação de exportação, geração de processos por modelo e outras operações com configuração/preview seguirão:

```text
Selecionar → Configurar → Validar/Pré-visualizar → Confirmar → Acompanhar resultado
```

`UStepper` comunica progresso; `UFileUpload` trata seleção/preview local; `UForm` e schemas tipados tratam validação; confirmação mutante usa `UModal`; resultado longo usa página de detalhe ou slideover. O backend continua sendo autoridade e nenhum sucesso é simulado.

Alternativa rejeitada: colocar todas as opções em uma única toolbar densa. Isso aumenta erro operacional e dificulta explicar consequências.

### 8. Formulários e autenticação seguirão os componentes especializados

- Login e desafio 2FA continuam com `UAuthForm` e `UPageCard`.
- Setup 2FA migra de `<form>` manual para `UForm` com schema e `UStepper` para senha, QR/confirmação e códigos de recuperação.
- Settings usam `UForm`, `UFormField`, `UPageCard` e separadores do template.
- Erros 422 são associados aos campos; 409 preserva entrada não sensível e oferece recarregar.
- Alterações pendentes recebem aviso antes de abandonar fluxo longo.

Alternativa rejeitada: forçar `UAuthForm` no setup 2FA. O componente é adequado a autenticação simples, mas o setup possui três etapas e artefato de recuperação.

### 9. Overlays serão escolhidos pela natureza da tarefa

- `UModal`: confirmação e formulário curto/focado.
- `USlideover`: detalhe secundário em desktop ou tela menor.
- `UDrawer`/`mode="drawer"`: ações e detalhes mobile.
- rota/página: processo longo, auditável ou com URL canônica.
- `UPopover`: filtro contextual curto.
- `UTooltip`: somente dica não interativa.

Overlays devem conter foco, fechar por teclado, devolver foco ao acionador e manter a rota/lista consistente.

### 10. Rotas serão tratadas por uma matriz completa

| Arquivo de página | Rota/papel | Arquétipo obrigatório | Melhoria principal |
|---|---|---|---|
| `pages/index.vue` | `/` | Home | Contexto temporal real, blocos Fiscal/Trabalho/Operações, progresso departamental e hierarquia de alertas |
| `pages/login.vue` | `/login` | Auth | Consolidar `UAuthForm`, foco, feedback e responsividade |
| `pages/two-factor-challenge.vue` | `/two-factor-challenge` | Auth | Alternância OTP/recuperação clara, erros e foco |
| `pages/two-factor/setup.vue` | `/two-factor/setup` | Auth + Stepper | Fluxo Senha → QR → Recuperação com `UForm` e `UStepper` |
| `pages/clients.vue` | shell `/clients*` | Settings | Tabs Lista/Dashboard e ação primária sem duplicação |
| `pages/clients/index.vue` | `/clients` | Customers | Densidade, filtros, A1/captura, rodapé server-side e ações de linha |
| `pages/clients/dashboard.vue` | `/clients/dashboard` | Home | Carteira, onboarding, certificado e captura com deep-links reais |
| `pages/clients/[id].vue` | shell `/clients/:id*` | Settings | Contexto da raiz, seções responsivas, aside útil e navegação de retorno |
| `pages/clients/[id]/index.vue` | resumo | Home/Settings | Progresso de onboarding e próximos passos |
| `pages/clients/[id]/cadastro.vue` | cadastro | Settings form | Leitura/edição clara, 422/409 e ações persistentes |
| `pages/clients/[id]/estabelecimentos.vue` | estabelecimentos | Members/list | Matriz/filiais, status e criação assistida |
| `pages/clients/[id]/certificado.vue` | certificado | Settings form | Saúde sanitizada, substituição segura e ausência de recuperação |
| `pages/clients/[id]/sincronizacao.vue` | sincronização | Settings + list | Canais, cursor/posição, falhas e ações elegíveis |
| `pages/clients/[id]/saidas.vue` | captura de saídas | Settings + list | Séries/perfis, prazos, lacunas e gates sem segredo |
| `pages/docs/index.vue` | `/docs` | Inbox/Customers | Visão por cliente escaneável e continuidade do contexto |
| `pages/docs/catalog.vue` | `/docs/catalog` | Customers + detail | Catálogo denso, filtros, seleção/export e detalhe sem perder lista |
| `pages/docs/[accessKey].vue` | `/docs/:accessKey` | Detail | Rota canônica, partes/status/chave e ações auditadas |
| `pages/docs/imports/index.vue` | `/docs/imports` | Customers | Histórico, ação primária e entrada no fluxo guiado |
| `pages/docs/imports/[id].vue` | lote | Detail/Settings | Progresso, resumo, filtros, erros, retry e CSV sanitizado |
| `pages/docs/import-batches.vue` | alias legado | Redirect | Redirecionamento sem chrome, loops ou estado inseguro |
| `pages/notes/index.vue` | alias `/notes` | Redirect | Compatibilidade canônica para `/docs` |
| `pages/notes/[accessKey].vue` | alias `/notes/:key` | Redirect | Compatibilidade canônica para detalhe em `/docs` |
| `pages/monitoring/index.vue` | `/monitoring` | Home | Contexto de competência, cobertura, carteira em atenção e execuções |
| `pages/monitoring/simples-mei.vue` | módulo | HomeStats + Customers | Submódulos, situação, origem e próximos prazos |
| `pages/monitoring/dctfweb.vue` | módulo | HomeStats + Customers | Eixos independentes, competência e confirmação reforçada |
| `pages/monitoring/installments.vue` | módulo | HomeStats + Customers | Pedido, parcelas, próxima parcela, atraso e guia |
| `pages/monitoring/sitfis.vue` | módulo | Customers + Slideover | Idade/TTL, findings e detalhe normalizado |
| `pages/monitoring/mailbox.vue` | shell mailbox | Inbox | Mestre–detalhe real, filtros e triagem interna explícita |
| `pages/monitoring/mailbox/index.vue` | mailbox vazio | Inbox empty | Estado neutro acessível |
| `pages/monitoring/mailbox/[id].vue` | mensagem | InboxMail | Rota canônica, corpo/anexos autorizados e foco |
| `pages/monitoring/declarations.vue` | módulo | HomeStats + Customers | Aplicabilidade, vencimento, entrega e evidência |
| `pages/monitoring/guides.vue` | módulo | Customers + Modal | Valor, vencimento, pagamento, versão e download protegido |
| `pages/monitoring/fgts.vue` | módulo | HomeStats + Customers | Cobertura parcial permanente e estados não suportados honestos |
| `pages/monitoring/clients/[clientId].vue` | detalhe fiscal | Settings | Seções lazy, falhas parciais e contexto do contribuinte |
| `pages/work/index.vue` | `/work` | Inbox | Fila mestre–detalhe, filtros, prioridade, ações e timeline |
| `pages/work/calendar.vue` | `/work/calendar` | Home + Inbox | Mês/Semana/Dia, minicalendário e painel da data |
| `pages/work/processes/index.vue` | `/work/processes` | Customers | Busca/filtros/paginação, risco e ação primária |
| `pages/work/processes/[id].vue` | processo | Settings | Resumo, checklist, comentários, evidências e histórico |
| `pages/work/templates/index.vue` | `/work/templates` | Customers + AddModal | Lista, editor guiado, preview e geração em etapas |
| `pages/closing/index.vue` | `/closing` | Customers/HomeStats | Competência, completude conhecida, risco e totalizações |
| `pages/exports/index.vue` | `/exports` | Customers + flow | Lista server-side e criação em etapas com escopo explícito |
| `pages/syncs/index.vue` | `/syncs` | Customers + Slideover | Canais, cursores/posições, detalhe e estados preservados |
| `pages/health/index.vue` | `/health` | Customers | Severidade, tipo, origem, deep-link e atualização segura |
| `pages/settings.vue` | shell `/settings*` | Settings | Subnavegação coerente, permissões e largura canônica |
| `pages/settings/index.vue` | Integra Contador | Settings form | Saúde/gates sanitizados e próxima ação, sem contrato global |
| `pages/settings/cte.vue` | CT-e | Settings form/list | Configuração e saúde por canal sem edição direta de cursor |
| `pages/settings/proxies.vue` | Procurações | Settings + table/form | Evidência referenciada, validade, poderes e ações permitidas |
| `pages/settings/usage.vue` | Consumo | Settings + tables | Período, franquia, saldo, serviços e ledger do tenant |
| `pages/settings/subscription.vue` | Assinatura | Settings | Plano, limites e estado sem gateway de cobrança |
| `pages/admin/index.vue` | `/admin` | Settings | Identidade fiscal, A1 do escritório, backup e gates administrativos |
| `pages/admin/departments.vue` | `/admin/departments` | Members/list | Departamentos, membros, carga e ativação segura |

Rotas adicionadas por outras changes durante o apply deverão entrar na matriz antes de qualquer código da nova rota ser aceito.

### 11. Estado, tenancy e segurança serão parte da apresentação

Toda página tenant-scoped invalida cache/seleção ao trocar explicitamente de escritório autorizado. Loading inicial, atualização, vazio, erro, 403, 409 e 422 são estados diferentes. Dados válidos anteriores permanecem visíveis após falha de refresh. Dados de outro tenant nunca permanecem após troca.

Nenhum componente, fixture, screenshot, trace ou log pode renderizar PFX, senha, PEM, chave privada, Consumer Secret, token, Termo XML, XML fiscal real, cookie, `vault_object_id` ou resposta externa bruta.

### 12. Aceite será por rota e por estado, não somente por screenshot feliz

Cada página aplicável terá evidência em `1440×900`, `390×844` e verificação de overflow em `360 px`. O aceite cobrirá ao menos:

- carregando;
- preenchida;
- vazia;
- falha inicial;
- falha de atualização preservando dados;
- papel autorizado e somente leitura;
- overlay/fluxo principal;
- teclado e foco;
- tema claro e escuro nas superfícies representativas.

## Risks / Trade-offs

- [Escopo de 51 arquivos gerar refatoração longa] → executar por famílias, com gates e baseline por rota, sem big bang.
- [Changes ativas alterarem páginas simultaneamente] → revisar `git diff` e artefatos ativos antes de cada família; incorporar novas rotas à matriz e nunca sobrescrever trabalho local.
- [Fidelidade literal conflitar com melhoria operacional] → preservar árvore/slots/classes do arquétipo e registrar qualquer divergência de domínio na matriz.
- [Densidade reduzir acessibilidade] → impor tamanho de alvo, tooltip/nome acessível, foco visível, contraste e versão mobile do conteúdo prioritário.
- [Contexto persistente virar seletor inseguro] → escritório somente da sessão; seletores de cliente/competência consultam APIs tenant-scoped e nunca aceitam `office_id`.
- [Totalizações divergirem da lista] → exibir somente agregados produzidos pelo mesmo escopo server-side; caso ausentes, mostrar apenas contagem da página/acumulada.
- [Calendário parecer agenda horária] → usar lanes por dia e prazos; horários só serão exibidos quando existirem em dados reais.
- [Uso excessivo de cards] → seguir a regra do Nuxt UI: cards apenas para agrupamentos claros; tabelas e conteúdo simples permanecem em superfícies planas.
- [Baselines mascararem regressões reais] → fixtures determinísticas e máscaras somente para valores variáveis, preservando geometria.
- [Melhoria visual alterar contrato fiscal] → mudanças de API exigem tarefa/spec explícita; CSS/componentes não inferem novos estados de negócio.

## Migration Plan

1. Congelar inventário, baseline visual, testes e estado das changes concorrentes.
2. Realinhar tokens, shell, navegação, padrões de contexto, estados e componentes pequenos compartilhados.
3. Migrar autenticação e Home, formando a referência interna de qualidade.
4. Migrar famílias de alta frequência: Trabalho/Calendário, Clientes, Documentos/Importações e Monitoramento.
5. Migrar Operações, Configurações e Administração.
6. Validar aliases/redirecionamentos, permissões, troca de tenant, acessibilidade e artefatos sanitizados.
7. Executar lint, typecheck, testes, Playwright visual/funcional, build SPA e `openspec validate`.

Rollback será por família de rotas, preservando contratos de API e migrações inexistentes. Novos componentes compartilhados só poderão substituir o caminho anterior depois que todos os consumidores da família passarem nos gates; a versão anterior permanecerá recuperável pelo histórico Git, sem flags permanentes de dois designs.

## Open Questions

- A política conflitante existente entre filtros reproduzíveis na URL e estado tabular local deverá ser resolvida antes do apply de cada rota: destinos/seções continuam em paths; filtros seguem a decisão mais recente da capability após sincronização das changes pendentes.
- A visão semanal do calendário usará somente prazos por data no primeiro incremento. A introdução futura de compromissos com hora inicial/final exigirá change de domínio separado.

## ADDED Requirements

### Requirement: Matriz integral e versionada de páginas
O sistema SHALL manter uma matriz de paridade que cubra cada arquivo de página autenticada, página de autenticação, página aninhada e redirecionamento legado, vinculando arquivo destino, rota, arquétipo, fonte exata no template, divergências autorizadas e evidências de aceite.

#### Scenario: Página existente entra na refatoração
- **WHEN** uma página listada em `frontend/app/pages/` é preparada para refatoração
- **THEN** a matriz identifica seu arquétipo e arquivo-fonte antes de qualquer alteração de markup

#### Scenario: Nova página surge durante a change
- **WHEN** outra change adiciona uma página enquanto esta refatoração está em andamento
- **THEN** a nova página entra na matriz e recebe os mesmos gates antes de ser considerada concluída

#### Scenario: Redirecionamento legado
- **WHEN** uma rota existe somente para compatibilidade
- **THEN** a matriz a classifica como redirecionamento, testa destino e ausência de chrome duplicado e não exige uma tela visual artificial

### Requirement: Aprendizado externo subordinado ao template
O sistema MAY incorporar de referências externas somente padrões de hierarquia da informação, densidade, contexto, filtros, totalizações, agenda e progresso que possam ser compostos com os arquétipos do template, e MUST NOT copiar identidade visual, chrome, iconografia, paleta ou composição que substitua a referência fixada.

#### Scenario: Padrão externo compatível
- **WHEN** contexto de período ou totalização melhora uma lista sem alterar a árvore canônica de navbar, toolbar, body e footer
- **THEN** o padrão pode ser adaptado com componentes Nuxt UI e sua origem/justificativa é registrada

#### Scenario: Padrão externo conflitante
- **WHEN** uma referência externa propõe sidebar, ação, densidade ou navegação incompatível com o arquétipo do template ou com acessibilidade
- **THEN** o padrão é rejeitado e a tela preserva a composição do clone fixado

#### Scenario: Controle externo sem função real
- **WHEN** a referência apresenta filtro, gráfico ou ação sem contrato de dados real no produto
- **THEN** o sistema não reproduz o controle apenas por semelhança visual

### Requirement: Paridade por estados e interações principais
O aceite de cada rota SHALL cobrir sua composição preenchida e todos os estados aplicáveis de carregamento, vazio, falha inicial, falha de atualização com dados preservados, somente leitura, overlay principal e responsividade; uma screenshot do caminho feliz isolado MUST NOT encerrar a rota.

#### Scenario: Falha de atualização com dados válidos
- **WHEN** uma página já preenchida falha ao atualizar
- **THEN** a evidência comprova que os dados anteriores continuam visíveis e que a falha oferece nova tentativa

#### Scenario: Página com mutação por papel
- **WHEN** a mesma rota é aberta por papel autorizado e por `VIEWER`
- **THEN** o aceite comprova que leitura permanece disponível e ações proibidas não são apresentadas ao `VIEWER`

#### Scenario: Cobertura responsiva
- **WHEN** a rota possui conteúdo de dados, formulário ou detalhe
- **THEN** ela é validada em `1440×900`, `390×844` e sem overflow obrigatório em `360 px`

### Requirement: Composição reconhecível após abstrações locais
Componentes compartilhados criados durante a refatoração MUST permanecer pequenos, explícitos e rastreáveis e MUST NOT esconder o `UDashboardPanel`, a API do `UTable`, os slots principais do template ou as decisões de responsividade da página.

#### Scenario: Preset tabular compartilhado
- **WHEN** uma tabela reutiliza um preset visual
- **THEN** suas colunas, slots, paginação, seleção e ações continuam declarados de forma inspecionável no consumidor

#### Scenario: Wrapper universal proposto
- **WHEN** uma abstração exigiria configurar navbar, toolbar, body, tabela e overlays por um objeto genérico
- **THEN** a abstração é rejeitada em favor da cópia/adaptação explícita do arquétipo

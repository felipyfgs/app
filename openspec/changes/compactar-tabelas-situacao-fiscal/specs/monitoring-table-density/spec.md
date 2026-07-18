## ADDED Requirements

### Requirement: MTD-01 — Densidade compacta no shell desktop
O shell desktop das grades de monitoramento (`ModuleDataTable` / `UTable`) SHALL aplicar densidade compacta: cabeçalho sem padding vertical generoso e células com padding horizontal reduzido em relação ao default Nuxt UI das carteiras densas. O comportamento de cards no viewport mobile (&lt; md) MUST permanecer disponível quando `mobileCards` estiver ativo.

#### Scenario: Grade monitoring com padding compacto
- **WHEN** o operador abre uma carteira de monitoramento em viewport desktop (md+)
- **THEN** a grade usa o shell compartilhado com densidade compacta (sem o padding vertical generoso de cabeçalho das grades não compactadas)

#### Scenario: Mobile continua em cards
- **WHEN** o operador abre a mesma carteira em viewport &lt; md com cards habilitados
- **THEN** a UI apresenta cards por cliente e MUST NOT exigir scroll horizontal da grade desktop

### Requirement: MTD-02 — SITFIS cabe sem buracos de coluna
A grade SITFIS SHALL definir larguras explícitas nas colunas desktop e MUST NOT deixar apenas a coluna Ações com largura fixa enquanto as demais se expandem de forma ociosa. A célula de franquia/agenda SHALL ocupar no máximo duas linhas densas de conteúdo principal. A coluna Ações MUST NOT repetir um botão texto “Cliente” quando a identidade do cliente já é a primeira coluna de dados.

#### Scenario: Colunas SITFIS com largura definida
- **WHEN** o operador visualiza a carteira SITFIS em desktop ~1280px de largura útil
- **THEN** as colunas padrão (incluindo Observado e Ações) permanecem legíveis sem corte da última coluna útil e sem faixas vazias exageradas entre colunas

#### Scenario: Franquia/agenda densa
- **WHEN** uma linha SITFIS exibe saldo, snapshot e próxima execução
- **THEN** esses dados aparecem em no máximo duas linhas densas na célula (não três blocos empilhados altos)

### Requirement: MTD-03 — Simples/MEI com Cliente à esquerda e colunas secundárias opcionais
Nas grades PGDAS-D e PGMEI, a coluna Cliente SHALL ser a primeira coluna de dados após a seleção (quando houver). Colunas secundárias de consulta/histórico (`consulted`, `history`) SHALL iniciar ocultas e MUST permanecer restauráveis via controle Exibir. A soma das larguras mínimas da visão padrão MUST caber em viewport desktop típico (~1280px) sem cortar a última coluna útil padrão; scroll horizontal MAY existir apenas como fallback em viewports mais estreitos.

#### Scenario: Ordem Cliente no Simples
- **WHEN** o operador abre Simples Nacional / MEI (PGDAS-D ou PGMEI) em desktop
- **THEN** a coluna Cliente aparece imediatamente após o checkbox de seleção (quando a seleção estiver habilitada)

#### Scenario: Secundárias ocultas por padrão
- **WHEN** a grade Simples/MEI carrega com a visibilidade inicial padrão
- **THEN** as colunas Última Busca e Histórico de Busca começam ocultas e podem ser reexibidas pelo menu Exibir

#### Scenario: Sem corte da visão padrão
- **WHEN** o operador usa viewport desktop ~1280px com a visibilidade padrão de colunas
- **THEN** todas as colunas visíveis por padrão cabem sem corte na borda direita (scroll horizontal não é necessário para a visão padrão)

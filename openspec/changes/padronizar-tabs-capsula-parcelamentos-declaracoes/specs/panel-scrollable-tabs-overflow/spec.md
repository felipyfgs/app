## ADDED Requirements

### Requirement: Tabs locais de carteira reutilizam a cápsula canônica

Controles locais de alternância em carteiras de monitoramento SHALL reutilizar o mesmo componente, tamanho e variante pill/primary da faixa KPI de Simples Nacional. As páginas MUST limitar seus overrides à contenção estrutural e MUST NOT redefinir localmente slots visuais de lista, trigger ou indicador.

#### Scenario: Modalidades de Parcelamentos seguem a faixa KPI

- **WHEN** o operador abre `/monitoring/installments`
- **THEN** as tabs de modalidade usam `ShellScrollableTabs` em tamanho `md` e a aparência pill/primary canônica
- **AND** a faixa ocupa no máximo a largura do pai e preserva scroll horizontal interno

#### Scenario: Obrigações de Declarações seguem a faixa KPI

- **WHEN** o operador abre `/monitoring/declarations`
- **THEN** as tabs de obrigação usam o mesmo componente, tamanho e contenção da faixa KPI de Simples Nacional
- **AND** a ação `Operações` permanece junto ao controle sem alterar a largura intrínseca nem o overflow interno das tabs

#### Scenario: Aparência permanece centralizada no tema

- **WHEN** uma página de carteira instancia a cápsula canônica
- **THEN** ela MUST NOT fornecer override local de `list`, `trigger` ou `indicator`
- **AND** cor primary, variante pill, raio, indicador e estados ativos/desabilitados permanecem definidos pelo wrapper e pelo tema Nuxt UI

### Requirement: Tabs locais exibem contadores reais no slot nativo

As tabs locais de Parcelamentos e Declarações SHALL fornecer `badge` numérica em cada item, usando contagens tenant-scoped do read model da carteira. As contagens MUST respeitar filtros globais e MUST remover somente a dimensão controlada pela própria faixa para permanecerem estáveis durante a alternância.

#### Scenario: Contadores de modalidade permanecem estáveis

- **WHEN** o operador abre Parcelamentos e alterna entre `Todos`, `PARCSN` e `PARCMEI`
- **THEN** cada tab exibe a quantidade de clientes correspondente à modalidade
- **AND** selecionar uma modalidade MUST NOT recalcular as demais badges dentro do escopo já carregado

#### Scenario: Contadores de obrigação permanecem estáveis

- **WHEN** o operador abre Declarações e alterna entre as obrigações
- **THEN** cada tab exibe a quantidade de clientes correspondente à obrigação
- **AND** `DIRF` exibe zero enquanto a cobertura permanecer unsupported

#### Scenario: Primeiro carregamento não inventa contagem

- **WHEN** o primeiro overview ainda está em carregamento e não existe mapa válido anterior
- **THEN** as badges exibem um placeholder de carregamento
- **AND** após uma resposta válida passam a exibir os inteiros retornados em `metrics.tab_counts`

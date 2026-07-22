## ADDED Requirements

### Requirement: Acionamento visual e exclusão backend
A skill `ui-archetype` SHALL ser acionada para criar, editar, revisar ou padronizar páginas, layouts, componentes, tabelas, monitoramento, formulários, overlays, navegação, tema, responsividade, acessibilidade e qualquer outra mudança visual ou de UX em `apps/web`. Ela MUST NOT ser acionada por uma mudança exclusivamente backend sem impacto de interface.

#### Scenario: Mudança de interface
- **WHEN** um agente recebe uma tarefa que altera uma superfície visual ou a experiência de uso em `apps/web`
- **THEN** ele usa `ui-archetype` antes de implementar ou concluir a revisão

#### Scenario: DTO exclusivamente Laravel
- **WHEN** um agente altera apenas um DTO Laravel sem contrato ou impacto visual em `apps/web`
- **THEN** ele não aciona `ui-archetype`

### Requirement: Fontes com precedência explícita
A skill SHALL ordenar suas fontes por requisitos do usuário, OpenSpec, domínio, permissões e tenancy; componentes, utilitários e testes atuais do produto; forma visual do template fixado; API instalada e tema gerado do Nuxt UI; e convenções gerais do Nuxt, nesta ordem. O agente MUST abrir o arquivo exato do template e o análogo atual do produto antes de decidir a composição.

#### Scenario: Template diverge da implementação produtiva
- **WHEN** o template não cobre um estado, comportamento responsivo ou requisito de acessibilidade já codificado no produto
- **THEN** o agente preserva o contrato produtivo e usa o template somente como referência estrutural

### Requirement: Orquestração de Nuxt e Nuxt UI
A skill SHALL encaminhar decisões de routing, layout, renderização e data flow à skill `nuxt`, e seleção de componentes, formulários, overlays e tema à skill `nuxt-ui`. O agente MUST conferir a API instalada ou `.nuxt/ui/<component>.ts` antes de assumir props, slots ou tokens que possam variar por versão.

#### Scenario: Escolha de componente Nuxt UI
- **WHEN** uma interface requer um componente, slot ou override `ui`
- **THEN** o agente consulta `nuxt-ui` e a definição instalada relevante antes da implementação

### Requirement: Reutilização dos arquétipos e cascas produtivas
A skill SHALL mapear páginas comuns, settings, listas, monitoramento, overlays e mestre–detalhe para as cascas produtivas `Shell*` e `Monitoring*`. `UTable` ou `<table>` direto MUST ficar restrito a conteúdo interno não paginável, com justificativa e teste dedicado.

#### Scenario: Lista comum paginável
- **WHEN** uma página apresenta uma coleção paginável fora das carteiras fiscais
- **THEN** o agente escolhe `ShellListFilterToolbar` ou `ShellFilterToolbarLite` com `ShellDataTable`

#### Scenario: Carteira de monitoramento
- **WHEN** uma página apresenta uma carteira fiscal operacional
- **THEN** o agente escolhe `MonitoringModuleTable` e `MonitoringModuleDataTable`

#### Scenario: Mestre–detalhe responsivo
- **WHEN** uma superfície combina lista e detalhe
- **THEN** o agente usa painéis irmãos no desktop e `USlideover` abaixo de `lg`

### Requirement: Contrato visual, responsivo e acessível
A skill SHALL exigir identidade green/zinc em light e dark, classes semânticas, conteúdo `pt_BR`, nomes acessíveis, navegação por teclado, foco correto e status comunicado além da cor. Superfícies assíncronas MUST tratar loading, idle, vazio, filtrado, erro com retry, sucesso, indisponibilidade, permissão e dados stale ou parciais quando aplicáveis.

#### Scenario: Tabela responsiva
- **WHEN** uma lista ou carteira é criada ou editada
- **THEN** ela usa IDs estáveis, seleção apenas com ação em massa real, sorting conectado à API, paginação 10/20/50, cards mobile e nenhuma largura artificial sem justificativa

#### Scenario: Componente legado tocado
- **WHEN** uma mudança edita um componente visual legado
- **THEN** o agente normaliza o componente inteiro aos contratos aplicáveis sem incluir automaticamente componentes vizinhos

### Requirement: Fluxo de mudança e evidências
A skill SHALL exigir classificação da superfície, inspeção das duas implementações de referência, reutilização das cascas, implementação de estados e acessibilidade, testes na mesma change e execução dos gates da área. Mudanças de produto MUST passar por `openspec-propose` e `openspec-apply-change`; auditorias somente leitura não exigem uma change.

#### Scenario: Mudança visual concluída
- **WHEN** o agente entrega uma mudança visual ou de UX
- **THEN** ele relata arquétipo, arquivo do template, casca produtiva, desvios intencionais, testes e resultados de `lint`, `typecheck`, `generate`, Vitest, `test:fidelity` e `test:artifacts`, além de inspeção desktop/mobile e light/dark quando relevante

#### Scenario: Nova tabela validada
- **WHEN** uma tabela ou componente tabular novo é entregue
- **THEN** há teste específico além de `test:fidelity`, pois a matriz atual não contém página `LIST`

### Requirement: Instalação local íntegra
O projeto SHALL manter `ui-archetype` em `.codex/skills/ui-archetype/` como fonte canônica e uma cópia idêntica em `.cursor/skills/ui-archetype/`, sem alterar `.gitignore`. A skill MUST conter `SKILL.md`, `agents/openai.yaml` e as quatro referências definidas, sem assets, scripts próprios ou caminhos legados.

#### Scenario: Validação da skill
- **WHEN** a skill é criada ou atualizada
- **THEN** `quick_validate.py` passa, `SKILL.md` possui menos de 500 linhas, o template permanece no commit fixado, todos os caminhos citados existem, não há referências a `.reference/` ou `frontend/` e `diff -qr` não encontra divergência entre os espelhos

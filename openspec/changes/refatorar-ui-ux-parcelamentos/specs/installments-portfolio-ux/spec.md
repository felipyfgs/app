## ADDED Requirements

### Requirement: Hierarquia operacional da carteira de Parcelamentos

A página `/monitoring/installments` SHALL preservar o shell canônico do dashboard e organizar o conteúdo na sequência tabs de modalidade, KPIs de situação, filtros e carteira. Em viewport desktop de 1366×639, o controle MUST permanecer compacto e MUST NOT exibir um alerta neutro permanente antes da tabela.

#### Scenario: Abrir carteira em viewport desktop

- **WHEN** o operador abre `/monitoring/installments` em viewport 1366×639
- **THEN** vê título e ação principal no navbar, seguidos pelas tabs de modalidade, KPIs, filtros e carteira
- **AND** cada modalidade oficial permanece acessível diretamente na faixa rolável

#### Scenario: Alertas representam falhas reais

- **WHEN** catálogo e overview carregam sem erro
- **THEN** a carteira MUST NOT exibir um alerta neutro permanente apenas para informar a quantidade de modalidades produtivas
- **AND** falhas reais de catálogo ou overview continuam visíveis como alertas persistentes

### Requirement: Tab por modalidade e fail-closed de prospecção

A carteira SHALL oferecer uma tab “Todos” e uma tab própria para `PARCSN`, `PARCSN-ESP`, `PERTSN`, `RELPSN`, `PARCMEI`, `PARCMEI-ESP`, `PERTMEI`, `RELPMEI`, `PARC-PAEX` e `PARC-SIPADE`. Cada modalidade produtiva SHALL filtrar seu código individual. PAEX e SIPADE SHALL ser identificadas como “em prospecção”, permanecer desabilitadas e MUST NOT aplicar filtro nem disparar consulta.

#### Scenario: Selecionar modalidade produtiva

- **WHEN** o operador escolhe uma das oito modalidades produtivas
- **THEN** a carteira aplica no filtro server-side já existente somente o código da modalidade escolhida
- **AND** a tab correspondente permanece ativa

#### Scenario: Identificar modalidade em prospecção

- **WHEN** o operador percorre a faixa de tabs
- **THEN** a UI mostra PAEX e SIPADE separadamente como modalidades indisponíveis em prospecção
- **AND** as tabs desabilitadas não alteram a modalidade ativa nem criam run fiscal

#### Scenario: Proporção idêntica à faixa de KPIs

- **WHEN** a carteira é exibida em viewport desktop de 1366×639
- **THEN** as tabs de modalidade usam o mesmo tamanho, padding de lista e altura de trigger das tabs de KPI
- **AND** não há override local de `list` ou `trigger` que altere a proporção entre as duas faixas

#### Scenario: Usar o controle em viewport estreita

- **WHEN** a carteira é exibida em viewport mobile
- **THEN** a faixa usa scroll touch contido sem causar overflow horizontal da página
- **AND** rótulos e estado desabilitado continuam acessíveis ao leitor de tela

### Requirement: Spine fiscal compacta e detalhe preservado

A tabela de Parcelamentos SHALL priorizar Situação no início, manter Cliente antes das ações finais e condensar atraso no resumo de Parcelas. A grade MUST oferecer um único acesso explícito ao pedido nas ações da linha; saldo, próxima parcela, documentos, pagamentos e histórico local MUST continuar acessíveis na grade ou no slideover, sem consulta SERPRO ao abrir o detalhe.

#### Scenario: Ler uma linha com atraso

- **WHEN** uma linha possui parcelas vencidas sinalizadas pela projeção local
- **THEN** a célula Parcelas exibe a contagem e o indicativo de atraso com cor semântica de erro
- **AND** a tabela não cria uma coluna redundante apenas para repetir o estado de atraso

#### Scenario: Abrir detalhe do pedido

- **WHEN** o operador aciona “Ver pedido” na linha
- **THEN** o slideover local mantém resumo, navegação entre pedidos, documento, parcelas e pagamentos disponíveis
- **AND** a abertura do detalhe não enfileira consulta nem chama a SERPRO

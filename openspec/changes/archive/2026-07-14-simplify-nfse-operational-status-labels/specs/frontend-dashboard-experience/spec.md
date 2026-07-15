## ADDED Requirements

### Requirement: Situação operacional Autorizada/Cancelada/Em revisão na UI de Notas
O sistema SHALL apresentar a situação da NFS-e no catálogo, chips, insights e exportação com **apenas** os labels operacionais **Autorizada**, **Cancelada** e **Em revisão**, conforme o agrupamento de domínio:

- **Autorizada** ← `ACTIVE`, `SUBSTITUTE`, `JUDICIAL`
- **Cancelada** ← `CANCELLED`, `SUPERSEDED`
- **Em revisão** ← `UNKNOWN`

O sistema MUST NOT exibir como chip principal da grade os labels por enum “Gerada”, “Substituta”, “Substituída” ou “Decisão judicial”. Essas nuances MUST aparecer no detalhe da nota (cStat, eventos, textos oficiais).

#### Scenario: Chip na lista para nota válida
- **WHEN** a listagem exibe uma nota com `status=ACTIVE` ou `status=SUBSTITUTE`
- **THEN** o chip mostra **Autorizada** (tom de sucesso) e não “Gerada” nem “Substituta”

#### Scenario: Chip na lista para nota inválida
- **WHEN** a listagem exibe uma nota com `status=CANCELLED` ou `status=SUPERSEDED`
- **THEN** o chip mostra **Cancelada** (tom de erro)

#### Scenario: Chip Em revisão
- **WHEN** a listagem exibe uma nota com `status=UNKNOWN`
- **THEN** o chip mostra **Em revisão** (tom de alerta)

### Requirement: Filtros e export usam grupos operacionais
O sistema SHALL oferecer no filtro de situação do catálogo de Notas e na tela de Exportações as opções **Autorizada**, **Cancelada** e **Em revisão** (além de “todas”), e MUST acionar a API com o grupo ou com a expansão de enums correspondente.

#### Scenario: Filtro Autorizada
- **WHEN** o usuário seleciona situação Autorizada e aplica o filtro
- **THEN** a consulta reinicia a paginação e retorna apenas notas do grupo autorizado

#### Scenario: Export com situação Cancelada
- **WHEN** o usuário gera export com filtro de situação Cancelada
- **THEN** o escopo inclui notas canceladas e supersedidas conforme o grupo

### Requirement: Insights de triagem por grupo operacional
O sistema SHALL calcular cards de triagem de notas por grupo operacional (autorizadas/válidas, canceladas, em revisão), sem contar `AUTHORIZED` de vocabulário de NF-e de mercadoria e sem exigir cards separados para Substituta/Substituída.

#### Scenario: Contagem de canceladas
- **WHEN** o insights é calculado
- **THEN** o card de canceladas inclui `CANCELLED` e `SUPERSEDED`

#### Scenario: Contagem de revisão
- **WHEN** o insights é calculado
- **THEN** o card de revisão inclui apenas `UNKNOWN` (e critérios de parse documentados no backend), não `ACTIVE` nem `SUBSTITUTE`

### Requirement: Detalhe da nota mostra operacional e oficial
O sistema SHALL, no modal/painel de detalhe da nota, exibir o badge de situação **operacional** e, em seção ou linha de situação fiscal, o **cStat** e a descrição oficial curta quando existirem, além de eventos e indicação de substituição quando aplicável.

#### Scenario: Detalhe cStat 100
- **WHEN** o usuário abre o detalhe de uma nota com cStat 100
- **THEN** vê badge **Autorizada** e indicação de situação oficial Gerada / cStat 100

#### Scenario: Detalhe cStat 101
- **WHEN** o usuário abre o detalhe de uma nota com cStat 101 e `status=SUBSTITUTE`
- **THEN** vê badge **Autorizada** e indicação de que se trata de NFS-e de substituição (cStat 101)

#### Scenario: Detalhe supersedida
- **WHEN** o usuário abre o detalhe de uma nota `SUPERSEDED`
- **THEN** vê badge **Cancelada** e texto legível de que a nota foi substituída (quando a API fornecer dados)

## MODIFIED Requirements

### Requirement: Catálogo de Notas em mestre–detalhe responsivo
O sistema SHALL apresentar Notas como catálogo mestre–detalhe, com painel de lista e painel adjacente redimensionável em desktop e detalhe em slideover/modal em viewport menor que `lg` (ou padrão já adotado no painel), mantendo `/notes/:accessKey` como rota canônica da seleção. Chips de situação na lista MUST usar o vocabulário operacional Autorizada/Cancelada/Em revisão.

#### Scenario: Seleção de nota em desktop
- **WHEN** o usuário seleciona uma nota em viewport `lg` ou maior
- **THEN** a rota muda para `/notes/:accessKey`, a linha fica selecionada e o detalhe aparece no painel adjacente sem desmontar o catálogo

#### Scenario: Seleção de nota no mobile
- **WHEN** o usuário seleciona uma nota em viewport menor que `lg`
- **THEN** a rota muda para `/notes/:accessKey` e o detalhe abre em slideover/modal que pode ser fechado por teclado ou controle visível

#### Scenario: Catálogo sem seleção no desktop
- **WHEN** nenhuma nota está selecionada em viewport `lg` ou maior
- **THEN** o painel de detalhe apresenta estado neutro orientando a selecionar uma nota

#### Scenario: Navegação pelo teclado
- **WHEN** o foco está no catálogo e o usuário usa os comandos de seleção documentados
- **THEN** a seleção avança ou recua entre registros visíveis e o item selecionado permanece visível

#### Scenario: Abertura direta do detalhe
- **WHEN** o usuário abre diretamente `/notes/:accessKey`
- **THEN** o sistema carrega o detalhe autorizado e disponibiliza retorno ao catálogo sem revelar existência de nota de outro escritório

#### Scenario: Chip operacional na grade
- **WHEN** a grade de notas renderiza a coluna de situação
- **THEN** o chip usa Autorizada, Cancelada ou Em revisão conforme o `status` granular da nota

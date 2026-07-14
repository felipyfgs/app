## ADDED Requirements

### Requirement: Home com alerta de backup e atalho da inbox
O sistema SHALL apresentar no dashboard home o estado de backup da instância (ok, atrasado ou nunca) e um bloco de atenção operacional com os itens mais graves da inbox e link para a lista completa de saúde, sem botões de restore em produção.

#### Scenario: Backup atrasado no home
- **WHEN** o resumo indica backup `stale` ou `never`
- **THEN** o home exibe alerta visual de severidade adequada e não mostra a chave mestra nem caminhos de dump

#### Scenario: Itens críticos na inbox
- **WHEN** a inbox retorna itens de severidade crítica ou alta
- **THEN** o home lista um subconjunto priorizado com deep-link funcional para o destino do item

### Requirement: Lista de saúde operacional
O sistema SHALL oferecer uma lista server-side de itens da inbox com filtros de severidade e tipo refletidos na URL, estados de carregamento/vazio/erro e paginação ou cursor alinhados à API, no visual de tabela administrativa do template.

#### Scenario: Filtro por severidade
- **WHEN** o usuário aplica filtro `critical` na lista de saúde
- **THEN** a URL reflete o filtro, a API é consultada de novo e apenas itens críticos são exibidos

#### Scenario: Lista vazia saudável
- **WHEN** a inbox não retorna itens
- **THEN** a UI mostra estado vazio positivo (sem problemas operacionais) sem inventar alertas cosméticos

### Requirement: Slideover de alertas alimentado pela inbox
O sistema SHALL preferir a inbox operacional como fonte do painel global de alertas e SHALL degradar de forma sanitizada se a inbox falhar, sem exibir segredos nem corpo bruto de erros remotos.

#### Scenario: Carregamento do slideover
- **WHEN** o usuário abre o painel de alertas
- **THEN** os itens exibidos correspondem a entradas da inbox ou a um fallback explícito de erro de carga

#### Scenario: Clique no alerta de cursor
- **WHEN** o usuário ativa um alerta de cursor bloqueado
- **THEN** a navegação leva ao destino de sincronização do cliente associado

### Requirement: Status de backup na Administração
O sistema SHALL exibir, na área de Administração restrita a `ADMIN` com segundo fator quando exigido, o último backup `SUCCESS`, o último restore drill e o estado de atraso, em modo somente leitura.

#### Scenario: Admin com 2FA
- **WHEN** um administrador autorizado abre Administração
- **THEN** o card de backup mostra timestamps e status sem oferecer restore pela UI

#### Scenario: Não administrador
- **WHEN** um `OPERATOR` ou `VIEWER` tenta a rota de Administração
- **THEN** o conteúdo administrativo de backup não é oferecido além do alerta já presente no home

# franquia-agendamento-monitor-serpro

## Purpose

Franquia comercial e agendamento mensal de monitores SERPRO: entitlements por plano, ledger comercial separado do técnico, política de dia por office+monitor, scheduler com spillover e canais em tempo real fora da franquia.

## Requirements

### Requirement: Entitlements dos planos por monitor
Todos os planos SHALL acessar todos os monitores SERPRO implementados e habilitados. Para cada período da assinatura, a franquia por cliente + monitor MUST ser 5 no Starter, 7 no Professional e 10 no Enterprise; o máximo padrão de clientes MUST ser respectivamente 100, 150 e 200.

#### Scenario: Cliente usa dois monitores
- **WHEN** um cliente do plano Starter consulta SITFIS e DCTFWeb no mesmo período
- **THEN** cada monitor SHALL possuir seu próprio saldo de cinco unidades para esse cliente

#### Scenario: Monitor está desabilitado por flag
- **WHEN** um monitor implementado estiver desabilitado por feature flag ou kill switch
- **THEN** o plano MUST NOT tornar a capacidade disponível nem contornar o gate

### Requirement: Limite negociado acima de duzentos clientes
Somente `PLATFORM_ADMIN` SHALL poder definir para um office um limite negociado de clientes acima de 200. A alteração MUST manter o nome do plano e MUST NOT alterar ou ampliar a franquia por cliente + monitor.

#### Scenario: Enterprise recebe limite negociado
- **WHEN** a plataforma eleva o máximo de clientes de um office Enterprise para 260
- **THEN** o office SHALL poder cadastrar até 260 clientes e continuará com dez unidades por cliente + monitor

### Requirement: Período alinhado à assinatura
A franquia MUST usar `current_period_starts_at` e `current_period_ends_at` da assinatura, renovando no aniversário comercial e não no mês-calendário nem no ciclo de faturamento do SERPRO. Unidades não usadas MUST expirar ao fim do período, sem rollover, compra, top-up ou excedente.

#### Scenario: Assinatura renova no dia quinze
- **WHEN** o período atual termina e o seguinte começa no dia 15
- **THEN** novos saldos SHALL iniciar no dia 15 e os saldos anteriores SHALL expirar

#### Scenario: Saldo acaba antes da renovação
- **WHEN** uma combinação cliente + monitor consumir todas as unidades
- **THEN** novas consultas SHALL ser bloqueadas até o próximo período sem oferta de crédito adicional

### Requirement: Consulta inaugural gratuita e única
Cada cliente + monitor SHALL receber uma única consulta inaugural após sua ativação. Ela MUST ser registrada no ledger comercial com `quota_units=0`, MUST NOT reduzir a franquia e MUST NOT ser recriada por renovação, troca de plano ou novo período.

#### Scenario: Primeiro uso do monitor
- **WHEN** um monitor é ativado pela primeira vez para um cliente elegível
- **THEN** o sistema SHALL permitir uma execução inaugural auditada sem debitar o saldo

#### Scenario: Novo período após uso inaugural
- **WHEN** a assinatura renovar após a consulta inaugural já ter sido usada
- **THEN** o sistema SHALL criar somente o saldo normal e não outra gratuidade inaugural

### Requirement: Ledger comercial separado do ledger técnico
Uma unidade comercial SHALL representar o primeiro despacho remoto real de uma consulta lógica para um cliente + monitor. O consumo MUST ser idempotente e correlacionado, mas distinto das chamadas técnicas registradas pelo gateway; validação, enfileiramento, bloqueio anterior ao transporte ou repetição idempotente MUST NOT consumir unidade.

#### Scenario: Consulta lógica usa várias chamadas técnicas
- **WHEN** um refresh exigir solicitar, monitorar e obter resultado em chamadas separadas
- **THEN** o ledger comercial SHALL debitar no máximo uma unidade e o ledger técnico SHALL registrar cada chamada conforme suas regras

#### Scenario: Procuração ausente bloqueia antes do transporte
- **WHEN** a operação exigir procuração e o cliente não estiver autorizado
- **THEN** nenhum consumo comercial SHALL ocorrer

### Requirement: Saldo compartilhado por consultas manuais e agendadas
Consultas manuais e scheduled MUST consumir o mesmo saldo cliente + monitor + período. Cada cliente + monitor MUST ter no máximo uma execução automática por período; se consultas manuais esgotarem o saldo antes do despacho automático, o item scheduled SHALL ser bloqueado sem chamada externa.

#### Scenario: Usuário esgota saldo manualmente
- **WHEN** todas as unidades forem consumidas por consultas manuais antes da data automática
- **THEN** o scheduler SHALL registrar bloqueio por franquia e não despachar aquele cliente + monitor

### Requirement: Política mensal por office e monitor
Cada office + monitor SHALL possuir um dia de execução entre 1 e 28 para toda a carteira, editável por `OfficeRole::ADMIN`. Sem escolha explícita, o sistema MUST aplicar um dia determinístico e distribuído derivado do office e monitor; não haverá seleção de horário pelo usuário.

#### Scenario: Office ainda não configurou o dia
- **WHEN** o monitor for ativado sem política personalizada
- **THEN** uma data padrão estável entre 1 e 28 SHALL ficar ativa automaticamente

#### Scenario: Administrador informa dia inválido
- **WHEN** o dia enviado for 0, 29 ou outro valor fora de 1–28
- **THEN** o sistema SHALL rejeitar a alteração e preservar a política anterior

### Requirement: Distribuição, spillover e idempotência do scheduler
No dia devido, o scheduler SHALL criar itens idempotentes por cliente + monitor + período e Horizon SHALL distribuí-los respeitando rate limits, intervalos oficiais, flags, orçamento e locks. Itens não concluídos no dia escolhido MUST continuar nos dias seguintes até concluir ou encontrar bloqueio terminal, e a unidade SHALL ser consumida somente no despacho remoto real.

#### Scenario: Carteira não termina no dia escolhido
- **WHEN** limites de carga impedirem concluir todos os clientes até o fim do dia
- **THEN** os itens restantes SHALL continuar nos dias seguintes sem criar segunda execução nem consumir antecipadamente

#### Scenario: Scheduler é executado duas vezes
- **WHEN** dois ciclos tentarem criar o mesmo item do período
- **THEN** a unicidade SHALL preservar um único item e no máximo um consumo

### Requirement: Repetição manual informada e limitada
Antes de uma consulta manual recente, a API SHALL informar o último horário, o estado de recência e que prosseguir consumirá uma unidade, e a UI MUST exigir confirmação. O backend MUST rejeitar despachos abaixo do intervalo mínimo oficial ou do módulo mesmo após confirmação.

#### Scenario: Usuário confirma refresh recente permitido
- **WHEN** existe snapshot recente, o intervalo mínimo já passou e o usuário confirma
- **THEN** o sistema SHALL despachar uma nova consulta e consumir uma unidade disponível

#### Scenario: Intervalo mínimo ainda não passou
- **WHEN** o usuário confirma mas a regra oficial ainda proíbe nova chamada
- **THEN** o servidor SHALL bloquear a execução sem consumo

### Requirement: Canais em tempo real fora da franquia
Eventos e consultas de NFS-e, SEFAZ e autXML em tempo real MUST permanecer fora da franquia e do agendamento mensal de monitores SERPRO.

#### Scenario: Documento chega por autXML
- **WHEN** um documento fiscal for obtido pelo fluxo autXML
- **THEN** nenhum saldo de monitor SERPRO SHALL ser consumido

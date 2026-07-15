## ADDED Requirements

### Requirement: Eventos e indicadores direcionam consultas da Caixa Postal
O sistema SHALL persistir eventos/indicadores oficiais de nova mensagem por contribuinte e SHALL consultar lista ou detalhe somente quando autorização, poder, TTL e orçamento permitirem.

#### Scenario: Nova mensagem indicada
- **WHEN** evento oficial informa nova mensagem para contribuinte elegível
- **THEN** o sistema agenda consulta idempotente da Caixa Postal desse contribuinte no tenant correto

#### Scenario: Evento repetido
- **WHEN** o mesmo evento é recebido novamente
- **THEN** nenhuma mensagem, chamada ou alerta é duplicado

### Requirement: Mensagens e anexos são dados fiscais restritos
O sistema MUST armazenar conteúdo, metadados e anexos com classificação sensível, hash, origem e retenção e MUST restringir acesso ao `office_id` e aos papéis autorizados.

#### Scenario: Viewer autorizado abre mensagem
- **WHEN** `VIEWER` do tenant possui permissão de leitura de Caixa Postal
- **THEN** o sistema registra acesso e exibe somente a mensagem pertencente ao escritório ativo

#### Scenario: Exportação genérica
- **WHEN** usuário tenta incluir conteúdo de mensagem em exportação não específica
- **THEN** o sistema exclui o conteúdo e exige fluxo de exportação fiscal autorizado

### Requirement: Leitura operacional é distinta da leitura oficial
O sistema SHALL manter estado interno de triagem (`NEW`, `IN_REVIEW`, `RESOLVED`) separado de qualquer indicador oficial de leitura e MUST NOT executar ação remota de leitura apenas porque um operador abriu o detalhe interno.

#### Scenario: Operador abre mensagem no MonitorHub
- **WHEN** uma mensagem nova é visualizada internamente
- **THEN** a trilha registra a visualização, mas o estado oficial remoto não é alterado sem operação explícita coberta

### Requirement: Alertas preservam sigilo
O sistema SHALL criar alertas com remetente/categoria sanitizados, severidade, prazo quando disponível e deep-link interno; título/body da inbox MUST NOT copiar conteúdo fiscal integral.

#### Scenario: Mensagem urgente
- **WHEN** metadados oficiais indicam prazo próximo ou categoria crítica
- **THEN** a inbox gera alerta acionável sem expor corpo, anexo ou token em listagens e logs

### Requirement: DTE e Caixa Postal mantêm proveniência própria
O sistema MUST identificar se o estado veio de Caixa Postal, indicador DTE ou outro serviço oficial e MUST NOT inferir adesão, ciência ou leitura de uma fonte com base apenas na outra.

#### Scenario: DTE ativo sem mensagens consultadas
- **WHEN** o indicador confirma DTE, mas a Caixa Postal não foi consultada
- **THEN** a UI mostra DTE confirmado e mensagens como `UNKNOWN`, sem fundir os estados


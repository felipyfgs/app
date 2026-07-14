## ADDED Requirements

### Requirement: Inbox operacional tipada e priorizada
O sistema SHALL expor uma inbox operacional do escritório ativo com itens derivados de cursores, execuções de sincronização recentes, credenciais A1 em alerta ou vencidas e estado de backup da instância, cada um com tipo em lista permitida, severidade, título, corpo sanitizado, motivos em código, horários e vínculos estáveis ao cliente e ao estabelecimento quando aplicável.

#### Scenario: Cursor bloqueado gera item
- **WHEN** um estabelecimento do escritório possui cursor `BLOCKED`
- **THEN** a inbox contém um item `cursor_blocked` de severidade crítica com deep-link para a sincronização do cliente e sem corpo remoto bruto do ADN

#### Scenario: A1 a vencer em sete dias
- **WHEN** a credencial ACTIVE de um cliente vence em sete dias ou menos e ainda não venceu
- **THEN** a inbox contém item de credencial com severidade alta e link para a seção de certificado

#### Scenario: Backup nunca executado
- **WHEN** a instância não possui backup `SUCCESS` registrado
- **THEN** a inbox contém item `backup_never` de severidade crítica sem expor a chave mestra

### Requirement: Isolamento e ausência de segredos na inbox
O sistema MUST restringir a inbox ao escritório da sessão e MUST NOT incluir PFX, senha, chave privada, PEM, XML fiscal, `vault_object_id`, cookie, token ou `VAULT_MASTER_KEY` em qualquer campo da resposta.

#### Scenario: Office forjado
- **WHEN** a requisição tenta filtrar ou injetar outro `office_id`
- **THEN** o sistema ignora o valor do cliente e devolve somente itens do escritório da sessão

#### Scenario: Varredura de payload
- **WHEN** a resposta da inbox é inspecionada em testes automatizados
- **THEN** não aparecem marcadores de material sensível proibidos pelo domínio

### Requirement: Ações permitidas por papel na inbox
O sistema SHALL listar, por item, apenas ações autorizadas ao papel do usuário; `VIEWER` permanece somente leitura; sincronização manual só aparece quando a policy e a elegibilidade atuais permitem, sem avançar NSU pela inbox.

#### Scenario: Viewer consulta a inbox
- **WHEN** um `VIEWER` lista a inbox
- **THEN** os itens são retornados e nenhuma ação de `trigger_sync` ou mutação é oferecida

#### Scenario: Operador com estabelecimento elegível
- **WHEN** um `OPERATOR` vê item de falha recente em estabelecimento elegível
- **THEN** a ação `trigger_sync` pode ser listada e o disparo reutiliza o fluxo existente de sync manual sem editar o NSU

### Requirement: Contagens da inbox no resumo operacional
O sistema SHALL incluir no resumo operacional contagens agregadas da inbox (ao menos total e críticos/altos) e o bloco de estado de backup, junto ao `generated_at`, para alimentar o painel e o slideover de alertas.

#### Scenario: Abertura do painel com bloqueios
- **WHEN** existem cursores bloqueados e o usuário carrega o resumo
- **THEN** as contagens da inbox refletem pelo menos esses itens e o horário de geração é atualizado

## MODIFIED Requirements

### Requirement: Saúde por cliente e estabelecimento
O sistema SHALL exibir último sucesso, próximo agendamento, NSU atual, estado do cursor e erro sanitizado de cada estabelecimento e SHALL destacar estabelecimentos em estado operacional problemático (`BLOCKED`, `ERROR` ou falha recente) na inbox e no painel, com o motivo operacional e a ação permitida ao perfil do usuário, sem oferecer edição de NSU.

#### Scenario: Estabelecimento bloqueado
- **WHEN** uma sincronização passa a `BLOCKED`
- **THEN** o painel e a inbox destacam o estabelecimento, o motivo operacional sanitizado e a ação permitida ao perfil do usuário

#### Scenario: Estabelecimento com erro recuperável
- **WHEN** o cursor está `ERROR` com mensagem sanitizada
- **THEN** a inbox inclui item `cursor_error` e o detalhe de sincronização do cliente permanece acessível por deep-link

### Requirement: Estado de backup verificável
O sistema SHALL apresentar a data e o resultado do último backup e do último teste de restauração registrado, bem como indicadores de atraso (mais de 24 horas sem sucesso) e de ausência total de backup, sem expor a chave mestra nem paths de custódia offline.

#### Scenario: Backup desatualizado
- **WHEN** não existe backup bem-sucedido nas últimas 24 horas
- **THEN** o painel exibe um alerta operacional sem expor a chave mestra

#### Scenario: Restore drill recente
- **WHEN** um restore drill `SUCCESS` foi registrado
- **THEN** o resumo operacional expõe o horário do drill para o administrador e demais usuários autenticados do escritório conforme a superfície de UI

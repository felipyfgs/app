# Operations Dashboard

## Purpose

Painel operacional com resumo, saúde por estabelecimento, histórico de sync, auditoria, inbox operacional e métricas sem segredos.

## Requirements

### Requirement: Resumo operacional
O sistema SHALL apresentar totais do escritório para clientes ativos, estabelecimentos, documentos, trabalhos pendentes, falhas e credenciais próximas do vencimento.

#### Scenario: Abertura do painel
- **WHEN** um usuário autenticado acessa o painel
- **THEN** o sistema mostra somente métricas agregadas do escritório ativo com horário da última atualização

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

### Requirement: Saúde por cliente e estabelecimento
O sistema SHALL exibir último sucesso, próximo agendamento, NSU atual, estado do cursor e erro sanitizado de cada estabelecimento e SHALL destacar estabelecimentos em estado operacional problemático (`BLOCKED`, `ERROR` ou falha recente) na inbox e no painel, com o motivo operacional e a ação permitida ao perfil do usuário, sem oferecer edição de NSU.

#### Scenario: Estabelecimento bloqueado
- **WHEN** uma sincronização passa a `BLOCKED`
- **THEN** o painel e a inbox destacam o estabelecimento, o motivo operacional sanitizado e a ação permitida ao perfil do usuário

#### Scenario: Estabelecimento com erro recuperável
- **WHEN** o cursor está `ERROR` com mensagem sanitizada
- **THEN** a inbox inclui item `cursor_error` e o detalhe de sincronização do cliente permanece acessível por deep-link

### Requirement: Histórico de sincronizações
O sistema SHALL manter e listar execuções com início, fim, cursor inicial/final, documentos processados, páginas, resultado e número de tentativas.

#### Scenario: Execução sem documentos
- **WHEN** o ADN não entrega documentos novos
- **THEN** o histórico registra sucesso sem documentos e o próximo horário previsto

### Requirement: Trilha de auditoria
O sistema MUST registrar autenticação relevante, alterações de cadastro, gestão de certificados, sincronizações manuais, downloads e exportações com ator, alvo, resultado, horário e IP quando disponível.

#### Scenario: Substituição de certificado
- **WHEN** um administrador ativa uma nova credencial
- **THEN** a auditoria registra os identificadores e fingerprints envolvidos sem registrar senha ou material criptográfico

### Requirement: Logs e métricas sem segredos
O sistema MUST produzir logs estruturados e métricas de fila, atraso, sucesso, falha, 429 e uso de disco sem incluir PFX, senha, chave privada ou XML fiscal.

#### Scenario: Erro remoto do ADN
- **WHEN** uma chamada falha e a resposta contém dados potencialmente sensíveis
- **THEN** o log mantém apenas código, identificador de correlação e mensagem sanitizada

### Requirement: Estado de backup verificável
O sistema SHALL apresentar a data e o resultado do último backup e do último teste de restauração registrado, bem como indicadores de atraso (mais de 24 horas sem sucesso) e de ausência total de backup, sem expor a chave mestra nem paths de custódia offline.

#### Scenario: Backup desatualizado
- **WHEN** não existe backup bem-sucedido nas últimas 24 horas
- **THEN** o painel exibe um alerta operacional sem expor a chave mestra

#### Scenario: Restore drill recente
- **WHEN** um restore drill `SUCCESS` foi registrado
- **THEN** o resumo operacional expõe o horário do drill para o administrador e demais usuários autenticados do escritório conforme a superfície de UI

### Requirement: Inbox para falhas de canais SEFAZ
O sistema SHALL incluir na inbox operacional itens acionáveis para cursors SEFAZ bloqueados, consumo indevido (656), falhas consecutivas de decode e A1 impactando canais DistDFe e CT-e, com deep-link para sincronização do cliente. O sistema MUST NOT produzir item operacional para MDF-e.

#### Scenario: Consumo indevido DistDFe
- **WHEN** um cursor DistDFe registra cStat 656 ou bloqueio equivalente
- **THEN** a inbox contém item de severidade alta ou crítica com canal DistDFe e sem envelope SOAP bruto

#### Scenario: Cursor MDF-e legado
- **WHEN** existe cursor MDF-e legado em banco
- **THEN** ele não aparece na inbox nem nas contagens operacionais

### Requirement: Resumo de saúde multi-canal
O sistema SHALL refletir no resumo de operações a existência de problemas em cursors não-ADN (além dos já cobertos para ADN).

#### Scenario: Health com DistDFe em erro
- **WHEN** há estabelecimento com cursor DistDFe em ERROR/BLOCKED
- **THEN** o resumo/inbox não ignora o problema por ser canal diferente do ADN

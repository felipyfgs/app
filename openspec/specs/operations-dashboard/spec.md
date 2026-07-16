# Operations Dashboard

## Purpose

Painel operacional com resumo, saúde por estabelecimento, histórico de sync, auditoria, inbox operacional e métricas sem segredos.
## Requirements

### Requirement: Resumo operacional
O sistema SHALL apresentar totais do escritório ativo para clientes, estabelecimentos, documentos, trabalhos, falhas, credenciais, autorizações fiscais, procurações, pendências, consumo SERPRO e franquia, com horário da última atualização e indicação de cobertura.

#### Scenario: Abertura do painel
- **WHEN** um usuário autenticado acessa o painel
- **THEN** o sistema mostra somente métricas agregadas do escritório ativo com horário da última atualização

#### Scenario: Integração parcialmente configurada
- **WHEN** o contrato global está saudável, mas o Termo do escritório ou procurações estão ausentes
- **THEN** o resumo mostra bloqueio do tenant e a próxima ação, sem expor credenciais globais

#### Scenario: Consumo próximo do limite
- **WHEN** o uso mensal do escritório alcança o limiar configurado
- **THEN** o resumo mostra consumo, franquia/saldo e deep-link para o detalhamento do próprio tenant

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
O sistema SHALL exibir último sucesso, próximo agendamento, estado do cursor e erro sanitizado de cada estabelecimento. Canais NSU SHALL mostrar NSU atual; séries outbound MA SHALL mostrar modelo, série, posição `nNF`, lacunas e recuperações pendentes. O sistema SHALL destacar `BLOCKED`, `ERROR`, falha recente e incidente fiscal na inbox/painel, sem oferecer edição direta de NSU ou `nNF`.

#### Scenario: Estabelecimento bloqueado
- **WHEN** uma sincronização passa a `BLOCKED`
- **THEN** o painel e a inbox destacam o estabelecimento, o motivo operacional sanitizado e a ação permitida ao perfil do usuário

#### Scenario: Estabelecimento com erro recuperável
- **WHEN** o cursor está `ERROR` com mensagem sanitizada
- **THEN** a inbox inclui item `cursor_error` e o detalhe de sincronização do cliente permanece acessível por deep-link

#### Scenario: Saúde de série outbound
- **WHEN** o perfil MA possui séries modelos 55 e 65
- **THEN** cada série aparece separadamente com posição `nNF`, última tentativa e contagens de lacuna/chave/XML, sem campo NSU

### Requirement: Histórico de sincronizações
O sistema SHALL manter e listar execuções com início, fim, canal, cursor inicial/final apropriado, documentos processados, páginas ou números consultados, resultado e número de tentativas. Histórico outbound MUST usar posição `nNF` e contagens de descoberta/recuperação; histórico NSU mantém seus campos existentes.

#### Scenario: Execução sem documentos
- **WHEN** o ADN não entrega documentos novos
- **THEN** o histórico registra sucesso sem documentos e o próximo horário previsto

#### Scenario: Execução outbound sem chave
- **WHEN** job MA consulta números e não recupera XML
- **THEN** o histórico registra posições, resultados/lacunas e próximo horário sem reportar documento persistido

#### Scenario: Execução interrompida por limite
- **WHEN** job MA alcança dez números
- **THEN** histórico encerra a execução como limitada/reagendada, preservando a posição confirmada

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
O sistema SHALL incluir na inbox operacional itens acionáveis para cursors SEFAZ bloqueados, consumo indevido 656, falhas consecutivas de decode, A1 impactando DistDFe/CT-e/saída MA e falhas de série ou recuperação outbound, com deep-link para sincronização do cliente. O sistema MUST NOT produzir item operacional para MDF-e nem incluir envelope SOAP/resposta bruta.

#### Scenario: Consumo indevido DistDFe
- **WHEN** um cursor DistDFe registra cStat 656 ou bloqueio equivalente
- **THEN** a inbox contém item de severidade alta ou crítica com canal DistDFe e sem envelope SOAP bruto

#### Scenario: Consumo indevido outbound MA
- **WHEN** consulta de protocolo MA registra cStat 656
- **THEN** a inbox contém item crítico da raiz/série, novos jobs são bloqueados e a mensagem não contém chave candidata completa

#### Scenario: A1 vencido afeta saída MA
- **WHEN** o A1 da raiz vence com perfis outbound ativos
- **THEN** a inbox identifica os canais afetados e nenhuma consulta/recuperação automática é enfileirada

#### Scenario: Cursor MDF-e legado
- **WHEN** existe cursor MDF-e legado em banco
- **THEN** ele não aparece na inbox nem nas contagens operacionais

### Requirement: Resumo de saúde multi-canal
O sistema SHALL refletir no resumo de operações a existência de problemas em cursors não-ADN (além dos já cobertos para ADN).

#### Scenario: Health com DistDFe em erro
- **WHEN** há estabelecimento com cursor DistDFe em ERROR/BLOCKED
- **THEN** o resumo/inbox não ignora o problema por ser canal diferente do ADN

### Requirement: Saúde operacional do recovery SVRS
O sistema SHALL incluir no resumo e na saúde operacional backlog, idade da pendência mais antiga, capturas, retries, bloqueios, estado do circuit breaker e horário da última captura SVRS, sempre restritos ao escritório ativo.

#### Scenario: Backlog de XML NFC-e
- **WHEN** existem recuperações `QUEUED`, `RUNNING` ou `RETRY_SCHEDULED`
- **THEN** o dashboard mostra contagem e idade agregadas sem expor chave completa ou CNPJ em labels de métrica

### Requirement: Inbox tipada para falhas SVRS
O sistema SHALL gerar itens de inbox distintos para A1 indisponível/não relacionado, contrato do wrapper alterado, autenticação proibida, rate limit persistente, XML/assinatura inválidos, divergência de identidade/bytes, breaker aberto e tentativas esgotadas.

#### Scenario: Contrato alterado
- **WHEN** o parser bloqueia o canal por `RESPONSE_CONTRACT_CHANGED`
- **THEN** a inbox cria item crítico com deep-link ao canal, orientação de fallback e sem HTML remoto

#### Scenario: Tentativas esgotadas
- **WHEN** uma chave fica `NOT_AVAILABLE_VISIBLE`
- **THEN** a inbox cria item acionável para retry elegível ou upload assistido conforme papel

### Requirement: Controles operacionais protegidos
O dashboard SHALL permitir somente a ADMIN com 2FA recente ativar kill switch, resetar breaker ou alterar allowlist. OPERATOR SHALL ver ações de retry/fallback elegíveis e VIEWER MUST ver somente estado.

#### Scenario: Reset do breaker
- **WHEN** ADMIN com 2FA recente confirma reset após corrigir a causa
- **THEN** a auditoria registra ator, motivo e escopo sem registrar certificado, chave fiscal ou resposta remota

### Requirement: Logs e métricas sanitizados do canal SVRS
O sistema MUST registrar métricas por ambiente, resultado, classe HTTP e motivo tipado sem usar CNPJ, chave completa, XML, HTML, PFX, cookie ou senha como label/campo. Logs MUST usar correlação e identificadores internos sanitizados.

#### Scenario: Falha HTTP com página de erro
- **WHEN** a SVRS retorna página de erro contendo dados inesperados
- **THEN** logs e métricas registram apenas classe HTTP, motivo tipado, latência e correlação

### Requirement: Kill switch do canal de saída MA
O sistema SHALL possuir kill switch global e por raiz para impedir novos jobs de consulta, recuperação e mutação MA, com operação restrita a ADMIN com 2FA recente, motivo obrigatório e auditoria. O kill switch MUST preservar cursores, pendências, XML e incidentes existentes.

#### Scenario: Acionamento global
- **WHEN** ADMIN com 2FA recente aciona o kill switch global e informa motivo
- **THEN** nenhum novo job externo MA inicia, jobs ainda não mutantes encerram com segurança e o painel mostra bloqueio crítico

#### Scenario: Kill switch durante cancelamento pendente
- **WHEN** há documento inesperadamente autorizado com cancelamento em reconciliação
- **THEN** o sistema impede novas sondas, mas permite somente a reconciliação idempotente do incidente já aberto

#### Scenario: Reativação
- **WHEN** ADMIN tenta reativar após incidente fiscal
- **THEN** o sistema exige incidente resolvido, motivo, 2FA recente e todos os gates de elegibilidade novamente válidos

### Requirement: Inbox tipada para sequência, recuperação e incidente fiscal
O sistema SHALL produzir itens allowlisted e sanitizados para lacuna esgotada, 562 sem chave, consumo indevido 656, recuperação expirada, XML divergente, autorização inesperada e cancelamento falho. Autorização inesperada ou cancelamento não confirmado MUST ter severidade crítica e deep-link estável.

#### Scenario: Lacuna esgotada
- **WHEN** número passa a `EXHAUSTED_VISIBLE`
- **THEN** a inbox inclui estabelecimento, modelo, série, `nNF`, tentativas e ação de revisão permitida

#### Scenario: Recuperação expirada
- **WHEN** solicitação oficial expira antes do download
- **THEN** a inbox inclui competência, modelo e ação de nova solicitação/upload sem referência secreta externa

#### Scenario: Incidente de cancelamento
- **WHEN** cancelamento de documento técnico não possui protocolo confirmado
- **THEN** a inbox cria item crítico não descartável por simples retry e o resumo reflete kill switch ativo

### Requirement: Métricas e logs do canal MA sem segredo
O sistema SHALL medir atraso, números consultados, chaves descobertas, XML pendentes/capturados, lacunas, cStat por classe, 429/656, recuperações e incidentes, e MUST NOT incluir PFX, senha, CSC, ID CSC, chave privada, PEM, cookie, token, XML fiscal, chave candidata completa ou resposta remota bruta em labels/logs.

#### Scenario: Falha remota com conteúdo sensível
- **WHEN** autorizador ou fonte MA devolve payload que contém dado fiscal ou sensível
- **THEN** logs retêm somente código, correlação, classe de resultado e mensagem sanitizada

#### Scenario: Métrica por escritório
- **WHEN** métricas operacionais são agregadas para a UI
- **THEN** a API aplica `office_id` da sessão e não expõe séries ou contagens de outro escritório

### Requirement: Auditoria das ações de alto risco
O sistema MUST auditar cadastro/substituição de CSC, mandato, allowlist, ativação, reset, consulta manual, upload de pacote, kill switch, inutilização, sonda, autorização e cancelamento com ator, alvo, resultado, horário e motivo, sem conteúdo secreto ou XML bruto.

#### Scenario: Reset auditado
- **WHEN** ADMIN reseta posição de uma série
- **THEN** auditoria registra posição anterior/nova, modelo, série e motivo sem apagar histórico

#### Scenario: Mutação recusada
- **WHEN** ação mutante é recusada por gate incompleto
- **THEN** auditoria registra ator, alvo e código do gate ausente sem materializar ou registrar credenciais

### Requirement: Fechamento mensal sobre documentos conhecidos
O dashboard SHALL exibir por escritório, competência e modelo o total conhecido, capturado, pendente, em atenção, contingência, risco de capacidade e vencido. A UI/API MUST denominar a métrica “completude sobre documentos conhecidos” e MUST NOT alegar universo fiscal absoluto.

#### Scenario: Competência incompleta
- **WHEN** existem cem chaves conhecidas e noventa XMLs canônicos
- **THEN** o painel mostra 90% de completude conhecida e detalha as dez pendências por faixa/fonte

### Requirement: Capacidade e conclusão prevista
O resumo SHALL mostrar exchanges automáticos planejáveis, folga, conclusão estimada, fontes de resolução e quantidade que exige contingência, sem revelar dados de outro tenant ou material fiscal bruto.

#### Scenario: Capacidade insuficiente
- **WHEN** a previsão não atende `target_at`
- **THEN** a inbox alerta antes do prazo e oferece lote XML/ZIP, `autXML` ou pacote oficial conforme elegibilidade

### Requirement: Alertas sem retry urgente
Itens `CONTINGENCY` e `OVERDUE` SHALL oferecer ações assistidas e MUST NOT oferecer aumento de taxa, antecipação de cooldown ou retry remoto fora do slot.

#### Scenario: Operador abre item vencido
- **WHEN** um OPERATOR acessa pendência vencida com breaker aberto
- **THEN** vê prazo, motivo e importação assistida como ação, sem botão de forçar SVRS

### Requirement: Inbox fiscal tipada
O sistema SHALL incluir itens sanitizados para Termo expirado, procuração ausente, consulta bloqueada, fonte indisponível, pendência fiscal, mensagem nova, guia vencendo, consumo elevado e resultado externo incerto.

#### Scenario: Procuração expirada
- **WHEN** poder necessário expira para um cliente vinculado
- **THEN** a inbox cria item acionável com módulo, cliente interno e serviço, sem conteúdo do instrumento ou token

#### Scenario: Resultado incerto de emissão
- **WHEN** uma operação mutante termina como `UNKNOWN_RESULT`
- **THEN** a inbox cria item crítico de reconciliação e não oferece retry imediato

### Requirement: Saúde SERPRO sanitizada por tenant
O sistema SHALL apresentar ao escritório somente disponibilidade, última execução, estado da sua autorização e bloqueios aplicáveis; detalhes do contrato global e incidentes de outros tenants MUST permanecer ocultos.

#### Scenario: Circuit breaker global aberto
- **WHEN** falha global impede chamadas de todos os tenants
- **THEN** o escritório vê indisponibilidade geral sanitizada sem métricas, consumo ou identidade de outros escritórios

### Requirement: Métricas fiscais não afirmam cobertura absoluta
O dashboard MUST separar pendência confirmada, atenção, desconhecido, não aplicável e não suportado e MUST NOT somar `UNKNOWN`/`UNSUPPORTED` como “em dia”.

#### Scenario: FGTS com cobertura parcial
- **WHEN** há fechamento eSocial conhecido, mas guia/pagamento não são consultáveis
- **THEN** o indicador explicita cobertura parcial e não apresenta o cliente como integralmente regular no FGTS Digital

### Requirement: Bloco de trabalho operacional no dashboard
O sistema SHALL acrescentar ao dashboard existente um bloco tenant-scoped com tarefas totais, atrasadas, em multa, vencem hoje, em progresso, concluídas e sem responsável, mantendo esses números semanticamente separados da inbox fiscal, sincronizações e backup.

#### Scenario: Dashboard com trabalho e alertas fiscais
- **WHEN** o escritório possui tarefas atrasadas e também cursor fiscal bloqueado
- **THEN** o dashboard mostra ambos em áreas identificadas, sem somar ou rotular uma categoria como se fosse a outra

#### Scenario: Filtro de competência
- **WHEN** usuário aplica competência ao bloco operacional
- **THEN** somente os KPIs de trabalho e agrupamentos compatíveis são recalculados, preservando a semântica dos indicadores fiscais existentes

### Requirement: Riscos e carga com deep-links
O sistema SHALL mostrar maiores riscos, processos sem dono e agrupamentos por departamento/responsável com deep-links para `/work/processes` ou `/work` usando filtros equivalentes.

#### Scenario: KPI sem responsável
- **WHEN** existem tarefas abertas sem responsável no escritório
- **THEN** o card exibe a contagem e seu deep-link abre a lista tenant-scoped com risco `SEM_RESPONSAVEL`

#### Scenario: Acesso sem permissão de mutação
- **WHEN** `VIEWER` abre um deep-link gerencial
- **THEN** a lista é exibida em modo somente leitura e não oferece reatribuição ou lote

### Requirement: Resumo operacional protegido e sanitizado
O sistema MUST derivar os indicadores de processo do escritório da sessão e MUST NOT incluir comentário, evidência, conteúdo fiscal, identificador do cofre ou material sensível no resumo ou nos agrupamentos.

#### Scenario: Tentativa de filtro por outro office
- **WHEN** requisição do dashboard injeta `office_id` de outro tenant
- **THEN** o valor não altera contagens, séries, riscos ou deep-links do escritório ativo

#### Scenario: Varredura do payload
- **WHEN** a resposta consolidada é inspecionada em teste automatizado
- **THEN** ela não contém `vault_object_id`, path, bytes de evidência, PFX, PEM, senha, token, Consumer Secret ou Termo XML

### Requirement: Preservação dos sinais operacionais existentes
O sistema SHALL manter o estado de backup, a inbox operacional, saúde de canais e demais métricas existentes ao adicionar o módulo de processos.

#### Scenario: Escritório sem processos
- **WHEN** não existem processos no escritório
- **THEN** o novo bloco apresenta estado vazio/zero e os cards de backup, inbox e saúde continuam funcionais

### Requirement: Progresso operacional por departamento
O dashboard SHALL apresentar para cada departamento do office ativo abertas, concluídas no período, atrasadas, em multa, sem responsável e proporção de conclusão, derivados do mesmo corte temporal e regras da fila.

#### Scenario: Dashboard preenchido
- **WHEN** o endpoint operacional retorna departamentos com trabalho
- **THEN** cada departamento aparece em bloco compacto com contagens, `UProgress` acessível e horário da última atualização válida

#### Scenario: Departamento sem atividade
- **WHEN** um departamento ativo não possui tarefas no corte
- **THEN** o dashboard apresenta zero de forma neutra ou omite conforme contrato explícito, sem inventar percentual ou tendência

#### Scenario: Percentual calculado
- **WHEN** existem tarefas abertas e concluídas no período
- **THEN** numerador, denominador e percentual usam o mesmo conjunto tenant-scoped e o texto acessível comunica o valor além da barra visual

### Requirement: Agenda operacional derivada dos mesmos prazos da fila
Dashboard, calendário, fila e detalhe SHALL usar a mesma definição tenant-scoped de prazo efetivo, risco e bucket, de modo que contagens e deep-links reconciliem sem criar compromissos ou horários ausentes no modelo.

#### Scenario: Tarefa vencendo hoje
- **WHEN** uma tarefa aparece no KPI `Vencem hoje`
- **THEN** ela aparece no dia correspondente do calendário e no deep-link da fila sob os mesmos filtros

#### Scenario: Tarefa impedida e atrasada
- **WHEN** uma tarefa possui riscos combinados
- **THEN** calendário e fila mostram prazo e impedimento como estados distintos, sem substituir um pelo outro

#### Scenario: Isolamento por escritório
- **WHEN** o usuário troca explicitamente de escritório
- **THEN** agenda, departamentos e contagens são recalculados somente para o novo tenant antes de exibição

### Requirement: Hierarquia calma de riscos e próximas ações
O dashboard SHALL priorizar riscos acionáveis e próximas ações sem transformar todo estado pendente em alerta crítico e MUST manter severidade, prazo, falha técnica e cobertura como dimensões distintas.

#### Scenario: Prazo saudável com canal fiscal bloqueado
- **WHEN** uma tarefa ainda está no prazo e um canal fiscal relacionado está bloqueado
- **THEN** as áreas correspondentes mostram os dois estados separadamente e não rotulam a tarefa como atrasada

#### Scenario: Sobrecarga sem falha técnica
- **WHEN** um departamento possui volume elevado mas nenhuma falha de canal
- **THEN** o dashboard mostra carga/progresso e não inventa um incidente fiscal ou de infraestrutura

### Requirement: Deep-links do trabalho preservam o recorte
Indicadores e blocos departamentais SHALL abrir fila ou processos com filtros representáveis na URL e semanticamente equivalentes ao número acionado.

#### Scenario: Abrir atrasadas do Fiscal
- **WHEN** o usuário ativa a contagem de atrasadas do departamento Fiscal
- **THEN** `/work` abre com tab/risco e departamento correspondentes, consulta a API e não aplica filtro apenas no cliente

#### Scenario: Abrir processos sem responsável
- **WHEN** o usuário ativa um item de processo sem responsável
- **THEN** o detalhe autorizado ou a lista filtrada é aberta sem perder o office da sessão

### Requirement: Sinais de trabalho permanecem separados dos demais domínios
O dashboard MUST manter métricas de trabalho operacional separadas de saúde fiscal, sincronização, backup e infraestrutura, sem somar contagens heterogêneas ou usar cores como único identificador.

#### Scenario: Home com múltiplos blocos
- **WHEN** a Home recebe indicadores fiscais e de trabalho
- **THEN** títulos, descrições e deep-links distinguem os domínios e nenhuma tarefa é apresentada como finding fiscal ou falha de infraestrutura

#### Scenario: Falha parcial do bloco Work
- **WHEN** o endpoint de trabalho falha e os demais blocos carregam
- **THEN** somente o bloco Work apresenta erro/retry sanitizado e os demais dados válidos permanecem visíveis

### Requirement: Saúde operacional dos canais CT-e
O dashboard operacional SHALL agregar separadamente saúde de `CTE_DISTDFE` por cliente e `CTE_AUTXML_DISTDFE` por escritório, incluindo cursores, fila, atraso, última/próxima execução, validade da credencial, cStat, falhas consecutivas, quarentena e estado de cobertura, sempre filtrado por `office_id` derivado do servidor.

#### Scenario: Cliente saudável e escritório bloqueado
- **WHEN** os cursores dos clientes estão alcançados, mas o canal `autXML` está bloqueado
- **THEN** o resumo apresenta os dois estados separadamente e não degrada todos os canais como uma falha única

#### Scenario: Consulta de outro escritório
- **WHEN** usuário tenta acessar saúde por identificador pertencente a outro `office_id`
- **THEN** o sistema responde como recurso não acessível e não revela CNPJ, cursor ou contagens

### Requirement: Inbox tipada para CT-e
A inbox SHALL criar itens tipados para A1 ausente/expirado, `593`, `656`, cinco falhas de decode, cursor sem heartbeat, conflito de consumidor externo, `UNEXPECTED_OWN_ISSUER_DOCUMENT`, documento/evento sem vínculo, `AUTXML_REDACTED`, divergência de bytes e `PENDING_IMPORT`. Cada item SHALL conter severidade, ação permitida, correlação e mensagem sanitizada.

#### Scenario: Documento emitido sem fonte automática
- **WHEN** a cobertura identifica CT-e emitido pendente sem `autXML` ou entrega do emissor
- **THEN** a inbox cria pendência de importação com ação XML/ZIP e não oferece automação de portal

#### Scenario: Quinta falha de decode
- **WHEN** o mesmo stream e ponto acumulam cinco falhas consecutivas de Base64/GZip
- **THEN** o cursor é bloqueado e a inbox cria alerta crítico sem incluir `docZip` ou XML

#### Scenario: Artefato redigido aceito
- **WHEN** um CT-e `AUTXML_REDACTED` é catalogado
- **THEN** a inbox ou indicador informativo registra a limitação sem tratá-la automaticamente como corrupção

### Requirement: Ações CT-e protegidas por papel e circuito
ADMIN com 2FA recente SHALL poder gerir flags, allowlist, credencial do escritório e resolver quarentena sensível; OPERATOR SHALL poder importar e solicitar reparo de NSU conhecido quando elegível; VIEWER MUST ser somente leitura. Nenhum papel SHALL poder forçar chamada durante quiet ou circuito `656`.

#### Scenario: Operator tenta retry durante circuito
- **WHEN** OPERATOR solicita nova chamada antes do desbloqueio por `656`
- **THEN** a ação é recusada sem executar rede e informa o horário mínimo permitido

#### Scenario: ADMIN promove quarentena
- **WHEN** ADMIN com 2FA recente associa item a estabelecimento inequívoco do mesmo escritório após validação
- **THEN** a promoção é auditada com antes/depois, ator e correlação, sem registrar XML bruto

### Requirement: Métricas e logs CT-e sem segredos
Métricas e logs SHALL registrar somente canal, IDs internos, cStat, latência, páginas, contagens, `ultNSU`, `maxNSU`, estado e códigos sanitizados. O sistema MUST NOT registrar XML, Base64, PFX, senha, PEM, chave privada, cookie de portal ou cabeçalhos sensíveis.

#### Scenario: Job concluído
- **WHEN** um job CT-e termina
- **THEN** o log estruturado contém cursor, páginas, documentos, duração e último NSU sem conteúdo fiscal ou segredo

#### Scenario: Exceção remota
- **WHEN** o endpoint retorna erro inesperado
- **THEN** o sistema registra classificação e correlação sanitizadas, preserva o corpo somente quando necessário em custódia privada e nunca o despeja no log comum

### Requirement: Saúde compartilhada do portal SVRS
O resumo operacional SHALL exibir estado da coorte, breaker, causa sanitizada, `next_probe_at`, exchanges consumidos/restantes, última tentativa e backlog separado por NF-e/NFC-e. Métricas globais de coorte MUST NOT revelar dados fiscais ou contagens de outros escritórios além do necessário para explicar indisponibilidade compartilhada.

#### Scenario: NFC-e bloqueia o host
- **WHEN** uma tentativa NFC-e abre o breaker global e um escritório acompanha uma NF-e pendente
- **THEN** o painel informa indisponibilidade compartilhada e próxima prova sem expor a chave ou o tenant que originou o bloqueio

### Requirement: Inbox para bloqueio e contingência
O sistema SHALL criar alertas distintos para múltiplas consultas, orçamento esgotado, contrato alterado, A1 inelegível, XML/assinatura divergente e canário bloqueado. Cada alerta SHALL oferecer ação permitida por papel e fallback, sem corpo remoto bruto.

#### Scenario: Cooldown ativo
- **WHEN** o breaker está em cooldown por múltiplas consultas
- **THEN** a inbox mostra prazo e contingência, mas não oferece retry imediato

### Requirement: Saúde da identidade e do A1 do escritório separada dos clientes
O sistema SHALL apresentar no resumo e na inbox o estado da identidade fiscal e da credencial A1 do escritório usada pelo canal autXML, incluindo ausência, validade, alertas de 30/7/1 dia, expiração e último uso bem-sucedido, sem misturar essas métricas com credenciais de clientes. A resposta MUST conter somente metadados públicos permitidos e MUST NOT expor PFX, senha, objeto de vault, PEM ou material de chave.

#### Scenario: A1 do escritório a vencer
- **WHEN** a credencial ACTIVE do escritório vence em sete dias ou menos
- **THEN** a inbox cria item `office_credential_expiring` de severidade alta com link para a gestão da identidade fiscal, sem apontar para Cliente fictício

#### Scenario: A1 do escritório vencido
- **WHEN** a credencial do escritório está expirada e o canal autXML está habilitado
- **THEN** o painel mostra o canal inelegível/bloqueado e mantém separadas as contagens de credenciais de clientes

#### Scenario: A1 de cliente vencido
- **WHEN** somente uma credencial de cliente vence
- **THEN** o painel não marca o A1 do escritório como inválido nem o cursor autXML como dependente daquela credencial

### Requirement: Saúde operacional do cursor central autXML
O sistema SHALL exibir por identidade fiscal e ambiente o estado do cursor `NFE_AUTXML_DISTDFE`, `last_nsu`, `max_nsu_seen`, último sucesso, próximo agendamento, atraso, chamadas/páginas recentes, falhas consecutivas de decodificação e último `cStat`/motivo sanitizado. O painel MUST distingui-lo dos cursores por estabelecimento e MUST NOT oferecer edição manual de NSU.

#### Scenario: Consumo indevido 656
- **WHEN** o cursor autXML registra `cStat=656` ou circuit breaker equivalente
- **THEN** a inbox cria item `autxml_consumo_indevido` alto/crítico com identidade, ambiente, backoff e deep-link, sem envelope SOAP ou XML

#### Scenario: Falha de decodificação recorrente
- **WHEN** o stream autXML acumula falhas consecutivas de Base64/GZip
- **THEN** o painel mostra contador e severidade crescente; ao bloquear na quinta falha, a inbox cria item crítico acionável

#### Scenario: Cursor de cliente saudável
- **WHEN** o cursor autXML está bloqueado e os cursores DistDFe de clientes estão saudáveis
- **THEN** o resumo atribui a falha somente ao canal autXML e não reduz artificialmente a saúde dos canais de cliente

#### Scenario: Usuário tenta alterar NSU
- **WHEN** qualquer perfil consulta as ações disponíveis para o cursor autXML
- **THEN** nenhuma ação de editar/retroceder `last_nsu` é oferecida; reprocessamento usa o fluxo idempotente e autorizado

### Requirement: Pendências de roteamento e quarentena visíveis
O sistema SHALL contabilizar e listar operacionalmente documentos autXML/importados sem estabelecimento, tag divergente, chave/bytes divergentes ou validação fiscal incompleta, agrupados por motivo tipado e origem. Esses itens MUST permanecer fora das métricas de documentos capturados/entregues e suas respostas comuns MUST NOT conter XML bruto, partes sensíveis do payload ou referência de vault.

#### Scenario: Emitente não vinculado
- **WHEN** XML válido fica em quarentena por não corresponder a estabelecimento do escritório
- **THEN** a inbox cria item `document_unmatched` com origem, data e identificador interno estável para resolução, sem atribuí-lo a cliente arbitrário

#### Scenario: autXML divergente
- **WHEN** documento recebido no stream não contém o CNPJ esperado em `autXML`
- **THEN** a inbox cria item `autxml_authorization_mismatch` de severidade alta e o resumo não o contabiliza como NF-e capturada

#### Scenario: Mesma chave com bytes divergentes
- **WHEN** uma aquisição entra em quarentena por conflito com o canônico
- **THEN** a inbox informa `document_bytes_conflict`, as duas origens e hashes abreviados/identificadores permitidos sem trocar o download vigente

#### Scenario: Quarentena de outro escritório
- **WHEN** o usuário tenta consultar identificador de pendência pertencente a outro `office_id`
- **THEN** a API não revela sua existência, motivo, chave, origem ou vínculo

### Requirement: Monitoramento de lotes de importação em massa
O sistema SHALL listar lotes de XML/ZIP do escritório com estado `UPLOADED`, `QUEUED`, `PROCESSING`, `COMPLETED`, `COMPLETED_WITH_ERRORS` ou `FAILED`, progresso, totais de itens e contagens de importados, duplicados, sem vínculo, divergência da restrição de cliente, inválidos, não suportados e em quarentena. O histórico SHALL permitir inspeção paginada por item e retomada idempotente quando autorizada, sem armazenar o conteúdo XML ou nomes de caminho inseguros em logs e métricas.

#### Scenario: Lote em processamento
- **WHEN** worker processa ZIP multiempresa
- **THEN** o painel atualiza progresso e contagens sem aguardar a conclusão da requisição de upload

#### Scenario: Lote parcialmente concluído
- **WHEN** alguns XMLs são válidos e outros inválidos ou sem vínculo
- **THEN** o lote termina `COMPLETED_WITH_ERRORS`, preserva os documentos importados e oferece relatório item a item sem rollback global nem perda de idempotência

#### Scenario: Worker interrompido
- **WHEN** o job termina por timeout ou indisponibilidade depois de processar parte do lote
- **THEN** o lote fica retomável a partir dos itens ainda pendentes e os itens concluídos não são importados novamente

#### Scenario: Lote totalmente duplicado
- **WHEN** todos os XMLs já existem com os mesmos hashes
- **THEN** o lote conclui com contagem de duplicados, sem erro operacional e sem crescimento de bytes no vault

#### Scenario: Falha de segurança do arquivo
- **WHEN** ZIP excede limites ou contém entrada aninhada, criptografada, link ou caminho inseguro
- **THEN** o lote/item registra código sanitizado de rejeição e a inbox só gera alerta quando a política operacional exigir, sem ecoar conteúdo ou caminho malicioso

### Requirement: Ações operacionais respeitam proprietário, papel e elegibilidade
O sistema SHALL derivar ações da inbox e do histórico pela policy do recurso correto: gestão/substituição do A1 do escritório permanece restrita a ADMIN com 2FA recente; reexecução de cursor ou lote exige papel e estado elegíveis; e resolução de quarentena exige vínculo no mesmo escritório e motivo auditável. Ações de um recurso MUST NOT receber identificador de credencial, cursor, lote ou estabelecimento de outro proprietário/tenant.

#### Scenario: VIEWER acompanha operação
- **WHEN** VIEWER abre saúde autXML, lote ou quarentena
- **THEN** recebe apenas metadados autorizados em modo leitura e nenhuma ação de gestão, retry ou resolução

#### Scenario: OPERATOR tenta substituir A1 do escritório
- **WHEN** OPERATOR usa deep-link de alerta de credencial
- **THEN** a UI/API não oferece nem executa upload/substituição do segredo

#### Scenario: Retry de lote elegível
- **WHEN** usuário com permissão solicita retomada de lote `FAILED` ou de itens elegíveis em `COMPLETED_WITH_ERRORS`
- **THEN** o backend deriva `office_id`, reprocessa somente itens elegíveis e registra ator/resultado sem alterar cursor NSU

#### Scenario: Ação forjada de outro tenant
- **WHEN** uma ação informa recurso pertencente a outro escritório
- **THEN** o sistema responde sem revelar existência externa e não enfileira job

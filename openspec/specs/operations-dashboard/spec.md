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

<!-- scenario synced from hub into Resumo operacional -->
#### Scenario: Integração parcialmente configurada
- **WHEN** o contrato global está saudável, mas o Termo do escritório ou procurações estão ausentes
- **THEN** o resumo mostra bloqueio do tenant e a próxima ação, sem expor credenciais globais

<!-- scenario synced from hub into Resumo operacional -->
#### Scenario: Consumo próximo do limite
- **WHEN** o uso mensal do escritório alcança o limiar configurado
- **THEN** o resumo mostra consumo, franquia/saldo e deep-link para o detalhamento do próprio tenant

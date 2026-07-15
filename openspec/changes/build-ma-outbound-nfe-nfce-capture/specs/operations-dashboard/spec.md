## ADDED Requirements

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

## MODIFIED Requirements

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


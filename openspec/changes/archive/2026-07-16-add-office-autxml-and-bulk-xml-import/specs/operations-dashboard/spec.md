## ADDED Requirements

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

## ADDED Requirements

### Requirement: Criação idempotente de recovery por chave
O orquestrador SHALL criar no máximo uma recuperação lógica ativa por `office_id`, perfil, chave e origem SVRS, reutilizando o registro existente em enfileiramentos repetidos.

#### Scenario: Dois disparos concorrentes
- **WHEN** scheduler e operador tentam enfileirar a mesma chave simultaneamente
- **THEN** uma única recuperação ativa é persistida e no máximo um job remoto é executado

### Requirement: Estados duráveis de recuperação
O sistema SHALL persistir as transições `ELIGIBLE`, `QUEUED`, `RUNNING`, `RETRY_SCHEDULED`, `CAPTURED`, `NOT_AVAILABLE_VISIBLE` e `BLOCKED`, com motivo tipado, tentativa, horários e correlação. `KEY_DISCOVERED` MUST NOT equivaler a `XML_CAPTURED`.

#### Scenario: Chave descoberta
- **WHEN** o motor de sequência persiste uma chave válida sem XML
- **THEN** o número permanece `XML_PENDING` e uma recuperação elegível pode ser criada

#### Scenario: Todas as retentativas esgotadas
- **WHEN** a quinta tentativa recuperável falha
- **THEN** a recuperação fica `NOT_AVAILABLE_VISIBLE`, permanece na inbox e não é marcada como capturada

### Requirement: Finalização somente após ingestão atômica
O sistema MUST marcar a recuperação e o número como capturados somente depois de persistir bytes no `SecureObjectStore`, aquisição, documento imutável e projeção do catálogo. Falha intermediária SHALL ser reconciliada por chave e hash sem duplicar o documento.

#### Scenario: Falha após gravação do objeto
- **WHEN** o objeto seguro foi criado mas a transação de projeção falha
- **THEN** nova execução reconcilia o objeto pelo hash, conclui a projeção e não cria segundo objeto canônico

#### Scenario: Ingestão completa
- **WHEN** vault, aquisição e projeção são confirmados
- **THEN** o número transita atomicamente de `XML_PENDING` para `XML_CAPTURED`

### Requirement: Retentativa diferenciada por motivo
O orquestrador SHALL reagendar somente falhas classificadas como recuperáveis nos intervalos 15 minutos, 1 hora, 6 horas e 12 horas, com jitter. Falha de identidade, assinatura, contrato ou autenticação persistente MUST bloquear em vez de repetir cegamente.

#### Scenario: HTTP 503
- **WHEN** a tentativa retorna falha transitória 503
- **THEN** o sistema agenda retry com backoff e mantém histórico da tentativa

#### Scenario: Identidade divergente
- **WHEN** o XML válido pertence a outra chave ou estabelecimento
- **THEN** o recovery fica `BLOCKED`, a série/perfil aplicável é protegido e um alerta crítico é criado

### Requirement: Fallback assistido preservado
Quando o canal SVRS estiver desligado, bloqueado, indisponível ou esgotado, o sistema SHALL manter a pendência apta a ser satisfeita por upload XML/ZIP ou pacote oficial. Uma ingestão assistida válida MUST reconciliar e encerrar a recuperação automática pendente da mesma chave.

#### Scenario: Upload resolve pendência SVRS
- **WHEN** o operador importa um `nfeProc` válido para uma chave em retry
- **THEN** o sistema marca o número capturado, encerra o recovery como resolvido por outra fonte e cancela jobs futuros dessa chave

#### Scenario: Wrapper alterado
- **WHEN** o breaker global abre por contrato alterado
- **THEN** a UI continua oferecendo o upload assistido sem afirmar que a SVRS capturou o XML

### Requirement: Locks por série, chave e raiz
O sistema SHALL combinar lock por estabelecimento+ambiente+modelo+série, unicidade no banco por recovery e limitador por raiz. Expiração de lock MUST permitir retomada segura sem apagar tentativa anterior.

#### Scenario: Worker morre em RUNNING
- **WHEN** o lock expira sem conclusão e não existe chamada em voo confirmada
- **THEN** um reconciliador move a recuperação para retry seguro preservando a tentativa interrompida

### Requirement: Tenancy derivada do servidor
Jobs, queries, API e ingestão MUST derivar `office_id`, cliente e estabelecimento das relações persistidas e da sessão autenticada, ignorando qualquer `office_id` fornecido pelo cliente.

#### Scenario: Office forjado no reprocessamento
- **WHEN** usuário envia identificador de recovery pertencente a outro escritório ou injeta `office_id`
- **THEN** o sistema responde como recurso não acessível e não enfileira trabalho externo

### Requirement: Autorização de ações de recovery
ADMIN com 2FA recente SHALL poder gerir allowlist, flags operacionais e breaker; OPERATOR SHALL poder enfileirar/reprocessar item elegível e usar import assistido; VIEWER MUST permanecer somente leitura.

#### Scenario: Viewer tenta reprocessar
- **WHEN** um VIEWER chama a ação de retry
- **THEN** a API responde 403 e nenhuma transição ou chamada SVRS ocorre

#### Scenario: Operator reprocessa item bloqueado por contrato
- **WHEN** um OPERATOR tenta reprocessar enquanto o breaker global está aberto
- **THEN** a ação é recusada com motivo sanitizado e orientação de fallback

### Requirement: Eventos não inferidos pelo recovery
O sistema MUST NOT concluir que uma NFC-e está ativa ou não cancelada apenas porque o `nfeProc` recuperado possui protocolo de autorização 100. Eventos posteriores SHALL continuar sendo projetados somente quando uma fonte válida os fornecer.

#### Scenario: nfeProc autorizado sem evento
- **WHEN** o XML recuperado contém protocolo 100 e nenhum evento de cancelamento
- **THEN** o sistema registra a autorização capturada sem inventar ausência definitiva de cancelamento


# tax-guide-management Specification

## Purpose

Sincronizado a partir de `build-complete-fiscal-monitoring-hub` (2026-07-15).

## Requirements

### Requirement: Emissão de guia usa operação oficial e idempotente
O sistema SHALL emitir guia somente por serviço oficialmente catalogado, com tenant, contribuinte, competência, débito e autorização consistentes e chave de idempotência.

#### Scenario: Guia emitida com sucesso
- **WHEN** operação elegível retorna documento de arrecadação válido
- **THEN** o sistema preserva bytes/hash, código identificador, valor, vencimento, serviço e evidência da emissão

#### Scenario: Requisição repetida
- **WHEN** a mesma emissão é solicitada novamente e existe guia ainda válida
- **THEN** o sistema reutiliza a guia ou aplica a regra oficial de reemissão sem criar mutação duplicada silenciosa

### Requirement: Versões e substituições são preservadas
O sistema MUST manter histórico de guias geradas, expiradas, canceladas ou substituídas e MUST identificar qual versão está vigente sem sobrescrever artefatos anteriores.

#### Scenario: Reemissão altera valor ou vencimento
- **WHEN** nova guia oficial difere da anterior
- **THEN** a anterior permanece histórica e a nova recebe vínculo de substituição e estado vigente

### Requirement: Emissão e pagamento são estados independentes
O sistema MUST NOT marcar guia como paga por ter sido emitida, baixada ou entregue internamente; pagamento exige fonte oficial e evidência próprias.

#### Scenario: Guia baixada pelo operador
- **WHEN** usuário baixa o documento
- **THEN** a auditoria registra a entrega interna e o estado de pagamento permanece inalterado

#### Scenario: Pagamento confirmado
- **WHEN** Integra-Pagamento ou serviço específico confirma pagamento compatível
- **THEN** o sistema vincula a evidência à guia sem apagar histórico de emissão

### Requirement: Acesso e entrega interna são controlados
O sistema SHALL autorizar visualização/download por papel e tenant e MUST gerar links temporários ou resposta protegida sem expor path de storage ou segredo.

#### Scenario: Usuário de outro tenant usa identificador válido
- **WHEN** usuário solicita guia pertencente a outro escritório
- **THEN** o sistema responde como não encontrada e não gera URL de download

### Requirement: Operações de guia de alto risco exigem confirmação reforçada
O sistema MUST exigir papel permitido, 2FA recente quando aplicável, resumo de contribuinte/competência/valor, custo estimado e confirmação antes de emissão mutante.

#### Scenario: Sessão administrativa sem 2FA recente
- **WHEN** `ADMIN` tenta emitir operação classificada como alto risco sem confirmação recente
- **THEN** o sistema exige novo desafio antes de reservar consumo ou chamar a fonte

### Requirement: Falha após envio gera estado incerto
O sistema SHALL registrar `UNKNOWN_RESULT` quando timeout ou falha ocorrer após possível processamento remoto e MUST reconciliar antes de repetir a emissão.

#### Scenario: Timeout após POST
- **WHEN** o transporte não recebe resposta após enviar a solicitação
- **THEN** a plataforma bloqueia retry imediato, preserva correlação e agenda consulta de reconciliação

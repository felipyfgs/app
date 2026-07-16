# sitfis-official-monitoring

## Purpose

Especificação da capability `sitfis-official-monitoring` (sincronizada a partir de change).

## Requirements

### Requirement: SITFIS solicita e emite relatório pelo protocolo 2.0
O sistema MUST solicitar protocolo por `SITFIS/SOLICITARPROTOCOLO91/2.0` em `/Apoiar` com `dados` vazio e emitir por `SITFIS/RELATORIOSITFIS92/2.0` em `/Emitir` usando o poder `00002`.

#### Scenario: Fluxo completo
- **WHEN** um contribuinte elegível é atualizado
- **THEN** o sistema persiste o protocolo da solicitação, aguarda o prazo oficial, emite o relatório e somente então cria evidência e snapshot normalizado

#### Scenario: Protocolo ausente
- **WHEN** a solicitação retorna sucesso sem protocolo correlacionável
- **THEN** o run falha com erro operacional e nenhuma emissão sem protocolo é tentada

### Requirement: Espera e polling respeitam a resposta oficial
O sistema MUST interpretar `tempoEspera` do corpo e do `ETag`, tratar 202 e 204 como processamento e reencaminhar o job sem espera bloqueante até o limite configurado.

#### Scenario: Relatório ainda processando
- **WHEN** a emissão retorna 204 com tempo de espera
- **THEN** o run permanece em processamento e é reencaminhado para depois do prazo indicado

#### Scenario: Limite de polling atingido
- **WHEN** o número máximo de tentativas é alcançado sem relatório
- **THEN** o run termina em erro recuperável, conserva o protocolo e gera alerta sanitizado

### Requirement: Atualização diária e manual reutilizam snapshot recente
O sistema SHALL agendar uma atualização diária com espalhamento determinístico e SHALL reutilizar snapshot válido por 24 horas quando ADMIN ou OPERATOR solicitar atualização manual.

#### Scenario: Clique dentro do TTL
- **WHEN** existe snapshot verificável observado há menos de 24 horas
- **THEN** nenhuma chamada externa é criada e a API informa o snapshot e a próxima atualização possível

#### Scenario: VIEWER solicita atualização
- **WHEN** um VIEWER tenta iniciar refresh SITFIS
- **THEN** o sistema responde 403 sem revelar a existência de dados de outro tenant

### Requirement: Resultado SITFIS preserva evidência antes de atualizar estado
O sistema MUST armazenar a resposta original imutável e seu hash antes de publicar o snapshot normalizado como estado atual.

#### Scenario: Parser desconhece o layout
- **WHEN** a resposta é válida mas possui layout SITFIS desconhecido
- **THEN** a evidência é preservada, o snapshot não é promovido como situação conclusiva e um alerta de parse é criado

### Requirement: API SITFIS comunica estado operacional
As rotas atuais SHALL permanecer disponíveis e retornar proveniência, verificação, observação, próxima atualização, permissão de refresh, bloqueio e correlação sanitizada.

#### Scenario: Processamento assíncrono
- **WHEN** a consulta foi enfileirada e ainda não concluiu
- **THEN** a API informa estado de processamento e a UI pode acompanhar sem bloquear a navegação

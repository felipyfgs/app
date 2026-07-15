## ADDED Requirements

### Requirement: Eventos direcionam a reconciliação DCTFWeb
O sistema SHALL persistir e deduplicar Eventos de Última Atualização de DCTFWeb antes de enfileirar consulta direcionada ao contribuinte e competência afetados.

#### Scenario: Evento de transmissão
- **WHEN** chega evento novo de declaração original ou retificadora
- **THEN** o sistema agenda reconciliação idempotente e não varre contribuintes sem evento

### Requirement: Apurações, recibos, relatórios e XMLs preservados
O sistema SHALL monitorar estados de DCTFWeb/MIT e preservar recibos, relatório completo, XML e demais artefatos oficialmente retornados com hash, competência e versão.

#### Scenario: Declaração transmitida
- **WHEN** a consulta confirma transmissão e retorna recibo
- **THEN** a competência é projetada como entregue com evidência imutável e horário oficial conhecido

#### Scenario: Artefato diverge do snapshot anterior
- **WHEN** consulta posterior retorna XML ou relatório diferente para a mesma competência
- **THEN** o sistema cria nova versão de evidência e sinaliza retificação sem sobrescrever bytes anteriores

### Requirement: Documento de arrecadação não prova pagamento
O sistema SHALL vincular DARF emitido à declaração/apuração correspondente e MUST NOT marcar pagamento sem evento ou fonte oficial de pagamento.

#### Scenario: DARF gerado
- **WHEN** a API gera documento de arrecadação
- **THEN** o sistema registra emissão, valor e vencimento mantendo pagamento como desconhecido até confirmação oficial

### Requirement: MIT e DCTFWeb mantêm estados independentes
O sistema MUST representar encerramento/apuração MIT e transmissão DCTFWeb como etapas correlacionadas, porém distintas, sem inferir sucesso de uma apenas pela outra.

#### Scenario: MIT encerrado sem transmissão confirmada
- **WHEN** o MIT está encerrado e não existe recibo DCTFWeb
- **THEN** o painel mostra a primeira etapa concluída e a transmissão como `UNKNOWN` ou `PENDING`

### Requirement: Transmissão exige controles mutantes
O sistema MUST exigir `ADMIN`, 2FA recente, procuração específica, confirmação, idempotência e coorte habilitada para transmitir DCTFWeb ou encerrar MIT.

#### Scenario: Timeout após transmissão
- **WHEN** a chamada mutante é enviada e o resultado fica incerto
- **THEN** o sistema bloqueia repetição e agenda reconciliação antes de permitir nova tentativa


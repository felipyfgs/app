## ADDED Requirements

### Requirement: Faturabilidade depende de operação e resultado
O sistema MUST classificar como não faturáveis chamadas `/Apoiar` e `/Monitorar`, simulações e respostas HTTP 204, 304, 400, 401, 404, 429, 500 e 503; demais resultados usarão a regra versionada da operação.

#### Scenario: SITFIS solicita protocolo
- **WHEN** `SOLICITARPROTOCOLO91` conclui em `/Apoiar`
- **THEN** o ledger registra a tentativa e a classifica como não faturável

#### Scenario: Emissão retorna rate limit
- **WHEN** `RELATORIOSITFIS92` retorna HTTP 429
- **THEN** a tentativa é auditada mas não aumenta a quantidade faturável

### Requirement: Simulação não consome orçamento
Chamadas `SIMULATED` MUST ser visíveis em telemetria de desenvolvimento e MUST NOT criar reserva, consumo faturável, franquia utilizada ou custo para escritório.

#### Scenario: Fluxo simulado completo
- **WHEN** o simulador solicita e emite um relatório
- **THEN** o run conclui sem alterar uso ou custo SERPRO do tenant

### Requirement: Preço desconhecido não vira zero
O sistema MUST manter preço e custo monetário nulos enquanto não existir tabela contratual válida, sem apresentar valores shadow como oficiais.

#### Scenario: Operação real sem preço
- **WHEN** uma chamada futuramente real é classificável mas não há versão contratual de preço
- **THEN** a quantidade é registrada, o custo fica desconhecido e o orçamento monetário não presume valor zero

### Requirement: Correlação permite futura conciliação
Cada chamada real SHALL enviar `X-Request-Tag` determinístico e registrar correlação sanitizada suficiente para reconciliar relatório oficial sem alterar o ledger original.

#### Scenario: Importação futura de relatório
- **WHEN** um relatório oficial contém a tag de uma chamada
- **THEN** a conciliação pode associar a linha ao run e ao escritório sem expor CNPJ ou segredo em labels


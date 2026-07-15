## ADDED Requirements

### Requirement: Cobertura explícita de Simples Nacional e MEI
O sistema SHALL disponibilizar somente operações catalogadas para PGDAS-D, DEFIS, Regime de Apuração, PGMEI, CCMEI e DASN-SIMEI e SHALL identificar serviço, versão, mutabilidade e procuração exigida.

#### Scenario: Cliente MEI elegível
- **WHEN** um contribuinte MEI possui autorização e poderes válidos
- **THEN** o sistema oferece apenas consultas e emissões oficialmente cobertas para seu regime

#### Scenario: Operação ausente do catálogo
- **WHEN** a UI solicita operação não catalogada para o regime
- **THEN** o sistema retorna `UNSUPPORTED` sem tentar endpoint aproximado ou portal

### Requirement: Monitoramento por competência com evidência
O sistema SHALL consolidar declarações, recibos, extratos, regime e situações por contribuinte e competência, preservando resposta oficial e data da consulta.

#### Scenario: Declaração entregue
- **WHEN** PGDAS-D ou DASN-SIMEI retorna declaração e recibo válidos
- **THEN** a competência fica `UP_TO_DATE` para essa obrigação com vínculo à evidência e ao recibo

#### Scenario: Competência sem resposta conclusiva
- **WHEN** a fonte não confirma entrega nem pendência
- **THEN** a situação permanece `UNKNOWN`, sem presumir omissão

### Requirement: Guias distinguem emissão de pagamento
O sistema SHALL permitir gerar DAS somente quando a operação oficial, autorização e papel permitirem e MUST manter emissão/validade separadas de confirmação de pagamento.

#### Scenario: DAS emitido
- **WHEN** a emissão retorna documento válido
- **THEN** o sistema armazena o artefato, vencimento e origem sem marcar a obrigação como paga

### Requirement: Transmissões permanecem bloqueadas no piloto
O sistema MUST classificar entrega de PGDAS-D, DEFIS ou DASN-SIMEI como operação mutante e MUST bloqueá-la enquanto a coorte estiver configurada como somente leitura.

#### Scenario: Operador tenta transmitir no piloto
- **WHEN** qualquer usuário tenta entregar declaração em tenant somente leitura
- **THEN** o sistema rejeita antes da chamada externa e audita a tentativa

### Requirement: Dados de regimes diferentes não se misturam
O sistema MUST manter resultados de SN e MEI vinculados ao mesmo `office_id`, contribuinte, sistema e competência, sem usar uma resposta de regime para inferir situação de outro.

#### Scenario: Contribuinte muda de regime
- **WHEN** a fonte confirma mudança entre períodos
- **THEN** o sistema preserva histórico por vigência e recalcula apenas competências aplicáveis


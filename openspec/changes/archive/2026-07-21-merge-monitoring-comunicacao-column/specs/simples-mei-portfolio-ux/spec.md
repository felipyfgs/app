## MODIFIED Requirements

### Requirement: Simples MEI portfolio keeps compact informative columns

A carteira `/monitoring/simples-mei` (PGDAS-D e PGMEI) SHALL manter as colunas canônicas Cliente, Situação, Ações, Comunicação e Consulta. A coluna Comunicação SHALL incluir Send, Switch de envio automático e no máximo um ícone de rastreio por linha (sem trio redundante status+download+busca no pedaço de rastreio). Preferências de canal e demais ações informativas de configuração SHALL permanecer no menu ⋮ (não como ícones soltos na grade).

#### Scenario: Tracking cell is single affordance

- **WHEN** o usuário vê uma linha PGDAS-D ou PGMEI
- **THEN** a coluna Comunicação tem um único ícone de rastreio além de Send e Switch
- **AND** não há trio de botões status + download + search no pedaço de rastreio

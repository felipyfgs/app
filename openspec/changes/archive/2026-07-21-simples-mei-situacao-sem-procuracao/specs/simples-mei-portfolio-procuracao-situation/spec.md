## ADDED Requirements

### Requirement: Portfolio Simples/MEI expõe status de procuração e-CAC
O endpoint de clientes do módulo `simples_mei` SHALL incluir no `detail` de cada linha o campo `procuracao_status` derivado da projeção oficial local (`ClientProcuracaoValidityResolver`), sem consultar a SERPRO na listagem.

#### Scenario: Cliente sem outorga sincronizada
- **WHEN** a projeção oficial do cliente estiver `missing`
- **THEN** o `detail.procuracao_status` MUST ser `missing`

#### Scenario: Cliente com procuração autorizada
- **WHEN** a projeção oficial do cliente estiver `authorized` (ou `expiring`)
- **THEN** o `detail.procuracao_status` MUST refletir esse status

### Requirement: Coluna Situação mostra Sem procuração
Nas tabelas PGDAS-D e PGMEI da carteira Simples/MEI, a célula Situação SHALL exibir o rótulo **Sem procuração** quando `detail.procuracao_status` for `missing`, com precedência sobre `declaration_state` / `debt_state`.

#### Scenario: Precedência sobre não verificado fiscal
- **WHEN** o cliente tem `procuracao_status=missing` e `declaration_state=UNVERIFIED` (ou `debt_state=UNVERIFIED`)
- **THEN** a coluna Situação MUST mostrar «Sem procuração» e MUST NOT mostrar «Não verificado»

#### Scenario: Com procuração, mantém estado fiscal
- **WHEN** o cliente tem `procuracao_status=authorized` (ou status distinto de `missing`)
- **THEN** a coluna Situação MUST continuar a mapear o estado PGDAS-D/PGMEI existente

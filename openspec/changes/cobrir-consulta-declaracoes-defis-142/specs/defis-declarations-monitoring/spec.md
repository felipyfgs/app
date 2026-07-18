## ADDED Requirements

### Requirement: Consulta DEFIS 142 explícita e sanitizada

O sistema SHALL executar `defis.consdeclaracao` somente após confirmação
explícita, com cliente resolvido por `CurrentOffice`, autorização de
sincronização e contrato oficial válido. A resposta SHALL passar por codec
fail-closed e persistir somente ano-calendário e tipo permitidos.

#### Scenario: Consulta autorizada em ambiente simulado

- **WHEN** um usuário autorizado confirma a consulta para cliente do escritório
  atual em ambiente fake/simulated
- **THEN** o sistema enfileira a operação 142 e projeta somente declarações
  sanitizadas sem `idDefis` ou identificador fiscal

#### Scenario: Cliente estrangeiro ou resposta ambígua

- **WHEN** a requisição referencia cliente de outro escritório ou a resposta
  não atende ao contrato oficial
- **THEN** o sistema nega ou falha fechado sem criar observação nem expor body
  de origem

### Requirement: Histórico local sem coleta implícita

O sistema SHALL listar somente a projeção DEFIS 142 pertencente ao
`CurrentOffice`; GET, carregamento da página e abertura do modal MUST NOT
chamar a SERPRO.

#### Scenario: Abertura do histórico

- **WHEN** o usuário abre o histórico DEFIS de um cliente do escritório atual
- **THEN** a interface mostra estados local, vazio, carregamento e erro sem
  dados brutos, tokens ou identificadores fiscais

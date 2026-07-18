## ADDED Requirements

### Requirement: Consulta explícita da opção de regime 103

O sistema SHALL executar `regimeapuracao.consultaropcaoregime` somente com
contrato oficial validado, cliente resolvido por `CurrentOffice`, autorização
de sincronização e ação explícita; a resposta SHALL passar por codec
fail-closed antes de qualquer projeção.

#### Scenario: Solicitação autorizada em teste

- **WHEN** um usuário autorizado confirma uma consulta válida usando cliente
  do escritório atual em ambiente fake/simulated
- **THEN** o sistema enfileira a operação 103 e persiste somente a projeção
  sanitizada correspondente

#### Scenario: Cliente de outro escritório ou resposta ambígua

- **WHEN** a requisição referencia cliente fora do escritório atual ou o
  retorno não satisfaz o codec
- **THEN** o sistema nega ou falha fechado sem criar projeção nem evidência
  sensível

### Requirement: Histórico local e interface sem coleta implícita

O sistema SHALL listar somente dados sanitizados da projeção 103 pertencente
ao `CurrentOffice`; GET, montagem de página e abertura de modal MUST NOT
chamar a SERPRO.

#### Scenario: Abertura do histórico

- **WHEN** o usuário abre o histórico de opção de regime de um cliente do
  escritório atual
- **THEN** a interface apresenta estados local, vazio, carregamento e erro sem
  representar payload bruto ou identificador fiscal

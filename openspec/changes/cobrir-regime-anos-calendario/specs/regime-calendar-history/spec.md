## ADDED Requirements

### Requirement: Consulta de anos-calendário do regime

O sistema SHALL executar `regimeapuracao.consultaranoscalendarios` pela
coordenada oficial `CONSULTARANOSCALENDARIOS102`, sem parâmetros de negócio,
com capability `simples_mei` e validação fail-closed da resposta antes de
persistir períodos por escritório e cliente.

#### Scenario: Fixture válida em teste

- **WHEN** o cliente fake/simulated entrega anos e regimes válidos para 102
- **THEN** o sistema normaliza e persiste somente os períodos do
  `CurrentOffice`, sem conexão de rede

#### Scenario: Retorno malformado

- **WHEN** `dados` não contiver anos de quatro dígitos e regimes reconhecidos
- **THEN** a consulta falha sem criar ou alterar períodos de regime

### Requirement: Histórico local de regimes na interface

O sistema SHALL permitir consultar e visualizar somente o histórico já
persistido do cliente do escritório atual e MUST NOT disparar uma coleta
externa ao abrir a tela ou o componente.

#### Scenario: Cliente de outro escritório

- **WHEN** a sessão solicita o histórico de um cliente de outro office
- **THEN** a API responde ausência sem listar, projetar ou revelar dados

## ADDED Requirements

### Requirement: Seletor de ano-calendário no histórico PGDAS-D do cliente

Na superfície de histórico local PGDAS-D do detalhe do cliente (`/monitoring/clients/:id` aba PGDAS-D), a UI SHALL exibir um seletor de ano-calendário (rótulo no espírito “Ano da busca”) que permite filtrar os períodos armazenados localmente. A troca de ano MUST NOT enfileirar consulta SERPRO — apenas recarrega o histórico local.

#### Scenario: Filtrar por ano

- **WHEN** o usuário seleciona um ano-calendário no seletor
- **THEN** a UI solicita o histórico com o parâmetro `year` correspondente e exibe apenas períodos daquele ano

#### Scenario: Todos os anos

- **WHEN** o usuário seleciona a opção de todos os anos (ou equivalente)
- **THEN** a UI carrega o histórico local sem filtrar por `year`

#### Scenario: Identificador estável

- **WHEN** a view de histórico está renderizada
- **THEN** o seletor está disponível com `data-testid` `pgdasd-history-year` (ou equivalente documentado na implementação)

## Purpose

Capability `declarations-obligation-hub` — requisitos sincronizados das changes OpenSpec.

## Requirements

### Requirement: Declarações hub exposes obligation tabs

O painel `/monitoring/declarations` SHALL exibir abas locais PGDAS, DCTFWeb, FGTS, DEFIS e DIRF. A URL SHALL permanecer `/monitoring/declarations` (submódulo não entra no path nem na query de navegação). A aba padrão SHALL ser PGDAS. O título da superfície SHALL refletir a aba ativa (ex.: `PGDAS - Declarações`).

#### Scenario: Default tab is PGDAS

- **WHEN** o usuário abre `/monitoring/declarations`
- **THEN** a aba PGDAS está selecionada e a carteira carrega com `submodule=PGDAS`

#### Scenario: Switching tabs stays on the same path

- **WHEN** o usuário seleciona a aba DEFIS
- **THEN** a URL permanece `/monitoring/declarations`
- **AND** a carteira recarrega com `submodule=DEFIS` (página/filtros/modais resetados)

### Requirement: Portfolio filters declarations by obligation submodule

A API de portfolio do módulo `declarations` SHALL aceitar `submodule` em `{PGDAS, DCTFWEB, FGTS, DEFIS, DIRF}` (e MAY aceitar `DECLARACOES` como agregado legado). Overview e lista de clients SHALL aplicar o mesmo filtro de obrigação/origem. Valores não listados em `knownSubmodules()` SHALL ser rejeitados.

#### Scenario: PGDAS filters PGDAS_D projections

- **WHEN** o cliente solicita `GET /api/v1/fiscal/modules/declarations/clients?submodule=PGDAS`
- **THEN** as linhas e detalhes refletem projeções/obrigação `PGDAS_D` (não misturam DEFIS/DCTFWEB como “próxima” da aba)

#### Scenario: DIRF returns honest empty unsupported

- **WHEN** o cliente solicita a carteira com `submodule=DIRF`
- **THEN** o sistema NÃO inventa fixtures
- **AND** a UI apresenta estado vazio ou `UNSUPPORTED` honesto

### Requirement: PGDAS tab list matches MonitorHub columns

Na aba PGDAS, a tabela SHALL exibir as colunas: Situação da declaração, Últ. Declaração, Cliente, Última Busca, Histórico de Busca. A ação Histórico de Busca SHALL abrir o histórico DAS do cliente.

#### Scenario: Open DAS history from list

- **WHEN** o usuário clica em Histórico de Busca em uma linha PGDAS
- **THEN** abre o modal “DAS Simples Nacional - Histórico” para aquele `client_id`
- **AND** NÃO dispara consulta SERPRO apenas por abrir o modal (histórico local)

### Requirement: PGDAS DAS history modal with nested declarations

O modal de histórico DAS SHALL incluir aviso de que MAEDs não são enviadas automaticamente aos clientes, identificação do cliente (nome/CNPJ mascarado), filtro por ano da busca, e tabela com Período, Pagamento, Busca, Valor total, Vencimento, Declarações, Malha, MAED e Download (Baixar DAS quando houver artefato). A ação Declarações na linha SHALL abrir o modal aninhado “Histórico de Declarações” com Operação, Nº Declaração, Transmissão, Recibo, Declaração, Malha, MAED, Nº DAS, Data Emissão e Extrato, reutilizando artefatos/downloads do domínio PGDAS-D existente.

#### Scenario: Nested declarations history

- **WHEN** o usuário clica em Declarações em um período do modal DAS
- **THEN** abre o modal “Histórico de Declarações” com as declarações/DAS daquele período
- **AND** downloads usam os endpoints/artefatos PGDAS-D já existentes (sem bytes embutidos na UI)

#### Scenario: Year filter scopes periods

- **WHEN** o usuário seleciona um ano no filtro “Ano da busca”
- **THEN** a tabela do modal DAS lista apenas períodos daquele ano-calendário

### Requirement: Other obligation tabs reuse existing surfaces

As abas DCTFWeb, FGTS e DEFIS SHALL apresentar lista filtrada (ou contrato de módulo correspondente) e MAY abrir modais de histórico já existentes (DCTFWeb history, DEFIS declarations/latest/specific). FGTS SHALL NÃO inventar status de guia/pagamento produtivos além da cobertura parcial já documentada.

#### Scenario: DCTFWeb history from declarations hub

- **WHEN** o usuário está na aba DCTFWeb e solicita histórico de um cliente
- **THEN** o sistema abre o fluxo de histórico DCTFWeb existente (sem nova integração SERPRO nesta change)

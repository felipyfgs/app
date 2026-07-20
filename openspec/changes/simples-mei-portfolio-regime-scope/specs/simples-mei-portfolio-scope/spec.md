## ADDED Requirements

### Requirement: Carteira Simples/MEI filtra por família de regime do submodule
O portfolio do módulo `simples_mei` SHALL restringir os clientes da carteira (lista e overview) conforme o `submodule` informado, usando `clients.tax_regime` normalizado pela família canônica: `PGDASD` apenas Simples Nacional; `PGMEI` apenas MEI. Clientes de outros regimes, regime vazio ou desconhecido MUST NOT aparecer em nenhuma das duas abas. O filtro SHALL ser aplicado na query de IDs escopados (`scopedClientIdsQuery` ou equivalente) para que lista e agregações compartilhem a mesma população. O escopo MUST continuar pinando `office_id` do `CurrentOffice` (nunca do body/query HTTP).

#### Scenario: Aba PGDASD lista só Simples Nacional
- **WHEN** a API de portfolio `simples_mei` é consultada com `submodule=PGDASD` e o escritório possui clientes ativos de matriz com regimes Simples Nacional, MEI e outro
- **THEN** a carteira retorna apenas o(s) cliente(s) da família Simples Nacional e exclui MEI e demais regimes

#### Scenario: Aba PGMEI lista só MEI
- **WHEN** a API de portfolio `simples_mei` é consultada com `submodule=PGMEI` no mesmo escritório com clientes mistos
- **THEN** a carteira retorna apenas o(s) cliente(s) da família MEI e exclui Simples Nacional e demais regimes

#### Scenario: Overview respeita o mesmo escopo de regime
- **WHEN** o overview do módulo `simples_mei` é consultado com `submodule=PGDASD` ou `PGMEI`
- **THEN** `totalClients` e contadores refletem somente clientes da família correspondente ao submodule

#### Scenario: Isolamento por escritório permanece
- **WHEN** existem clientes elegíveis pelo regime em outro `office_id`
- **THEN** a carteira do escritório atual MUST NOT incluir esses clientes

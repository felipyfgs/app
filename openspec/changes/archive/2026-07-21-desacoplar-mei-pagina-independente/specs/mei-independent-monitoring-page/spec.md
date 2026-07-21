## ADDED Requirements

### Requirement: Carteira MEI em rota própria
O painel SHALL expor a carteira operacional PGMEI em `/monitoring/mei`, usando o módulo fiscal API `simples_mei` com submodule `PGMEI`, sem abas locais de troca para PGDAS-D.

#### Scenario: Abrir monitoramento MEI
- **WHEN** o usuário navega para `/monitoring/mei`
- **THEN** a superfície exibe título MEI, KPIs e lista escopados a clientes da família de regime MEI (via portfolio `simples_mei` + `submodule=PGMEI`)

#### Scenario: Sem cápsula Simples na página MEI
- **WHEN** o usuário está em `/monitoring/mei`
- **THEN** a UI MUST NOT oferecer tab ou controle para alternar para PGDAS-D / Simples Nacional na mesma página

### Requirement: Item de navegação MEI dedicado
A navegação de monitoramento SHALL incluir um item distinto para MEI apontando para `/monitoring/mei`, separado do item Simples Nacional.

#### Scenario: Rail lista Simples e MEI
- **WHEN** o usuário autenticado com acesso ao monitoramento visualiza o menu de monitoramento
- **THEN** existem entradas distintas “Simples Nacional” (`/monitoring/simples-mei`) e “MEI” (`/monitoring/mei`)

### Requirement: Carteira Simples Nacional só PGDAS-D
A rota `/monitoring/simples-mei` SHALL operar exclusivamente a cápsula PGDAS-D (Simples Nacional), sem tabs locais SN↔MEI e sem rótulo agregado “Simples Nacional | MEI” no título da superfície.

#### Scenario: Abrir Simples Nacional
- **WHEN** o usuário navega para `/monitoring/simples-mei`
- **THEN** a superfície exibe título Simples Nacional, KPIs e lista escopados a PGDAS-D / regime Simples Nacional
- **AND** MUST NOT exibir tab MEI / PGMEI nessa página

### Requirement: Pós-create e deep-link legado
Após criar cliente MEI, o painel SHALL navegar para `/monitoring/mei`. Deep-links legados sob `/monitoring/simples-mei` que pediam cápsula PGMEI SHALL redirecionar para `/monitoring/mei`.

#### Scenario: Create cliente MEI
- **WHEN** um cliente com regime MEI é criado e o fluxo de pós-create de monitoramento se aplica
- **THEN** o destino é `/monitoring/mei` (sem depender de sessionStorage de cápsula)

#### Scenario: Legacy pgmei sob simples-mei
- **WHEN** o usuário acessa um path legado que selecionava a cápsula PGMEI em `/monitoring/simples-mei/...`
- **THEN** o sistema redireciona para `/monitoring/mei`

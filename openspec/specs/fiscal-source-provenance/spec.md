# fiscal-source-provenance

## Purpose

Especificação da capability `fiscal-source-provenance` (sincronizada a partir de change).

## Requirements

### Requirement: Proveniência acompanha toda evidência fiscal
Runs, artefatos e snapshots MUST registrar `SIMULATED`, `SERPRO_REAL` ou `UNVERIFIED`, e a origem MUST ser definida pelo driver, nunca pelo payload ou frontend.

#### Scenario: Run simulado
- **WHEN** o driver interno produz uma resposta SITFIS
- **THEN** run, evidência e snapshot recebem `SIMULATED` de forma consistente e não podem ser promovidos para `SERPRO_REAL`

### Requirement: Legado sem prova não representa estado atual
O sistema SHALL migrar registros anteriores sem proveniência para `UNVERIFIED`, preservá-los para auditoria e excluí-los das consultas de situação fiscal atual.

#### Scenario: Snapshot legado é o mais recente cronologicamente
- **WHEN** existe snapshot `UNVERIFIED` mais novo que o último snapshot verificável
- **THEN** a consulta atual ignora o legado e usa o verificável ou informa ausência de estado atual

### Requirement: Proveniência respeita tenancy e ambiente
Toda consulta e persistência de proveniência do plano de dados MUST manter `office_id` obrigatório e MUST impedir mistura do mesmo CNPJ entre escritórios.

#### Scenario: Mesmo CNPJ em dois escritórios
- **WHEN** dois tenants monitoram contribuintes com o mesmo CNPJ
- **THEN** runs, evidências, snapshots e origem permanecem isolados pelo escritório autenticado

### Requirement: Simulação não constitui evidência oficial
O sistema MUST impedir que dados `SIMULATED` satisfaçam gates, alertas conclusivos, exportações oficiais ou indicadores declarados como fonte SERPRO real.

#### Scenario: Dashboard recebe snapshot simulado
- **WHEN** um ambiente de desenvolvimento consulta dados simulados
- **THEN** a resposta identifica a origem e nenhuma métrica de consumo ou validação produtiva é incrementada

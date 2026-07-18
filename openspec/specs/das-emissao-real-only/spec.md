## Purpose

Impedir que emissões DAS sintéticas sejam tratadas como resultado fiscal real.

## Requirements

### Requirement: Emissão DAS não possui fallback local sintético

O sistema MUST NOT fabricar guia, número, vencimento, documento, protocolo ou evidência quando a emissão DAS real estiver desabilitada; a operação SHALL retornar bloqueio explícito.

#### Scenario: GERAR_DAS com mutações desligadas

- **WHEN** a operação `GERAR_DAS` é solicitada sem autorização/capability real
- **THEN** nenhuma linha `FiscalGuideStub` é criada, nenhuma auditoria `SUCCESS` é emitida e a execução termina bloqueada

#### Scenario: Default de configuração

- **WHEN** nenhuma variável específica de fallback DAS é definida
- **THEN** o fallback local permanece desligado

### Requirement: Stub histórico não é resultado operacional

Registros históricos `STUB-*` ou com `is_external_call=false` SHALL permanecer apenas para quarentena/reconciliação e MUST NOT aparecer como guia emitida, evidência real, KPI ou prontidão.

#### Scenario: Consulta de guia do cliente

- **WHEN** existem apenas registros históricos sintéticos
- **THEN** a API/UI não os apresenta como guia oficial e sinaliza ausência de evidência real

### Requirement: Superfície guide-stubs não permite novo uso

As rotas, links e consumers de `guide-stubs` MUST ser removidos ou substituídos por estado de bloqueio sem documento sintético.

#### Scenario: Frontend após remoção do fallback

- **WHEN** o usuário acessa a área de guias DAS
- **THEN** a interface não consulta nem oferece `guide-stubs` como resultado emitido

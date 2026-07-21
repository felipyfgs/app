## Purpose

Capability `monitoring-communication-send-guards` — requisitos sincronizados das changes OpenSpec.

## Requirements

### Requirement: Roteamento de comunicação automática por service_code

Após consulta agendada bem-sucedida no módulo `simples_mei`, o sistema SHALL enfileirar comunicação automática apenas para o submódulo correspondente ao `service_code` da run: PGMEI → comunicação PGMEI; PGDASD → comunicação PGDAS-D. O sistema MUST NOT enfileirar comunicação PGDAS-D para outros serviços Simples/MEI (DEFIS, CCMEI, DASN ou equivalentes).

#### Scenario: Sucesso PGDASD enfileira PGDAS-D

- **WHEN** uma run agendada `simples_mei` com `service_code` contendo `PGDASD` conclui com sucesso e o cliente tem `automatic_requested` elegível com documentos locais
- **THEN** o sistema enfileira dispatch(es) no submódulo `pgdasd` com `idempotency_key` ≤ 64

#### Scenario: Sucesso DEFIS não enfileira PGDAS-D

- **WHEN** uma run agendada `simples_mei` com `service_code` de DEFIS (ou outro serviço não PGDASD/PGMEI) conclui com sucesso
- **THEN** o sistema MUST NOT chamar o serviço de comunicação PGDAS-D nem criar dispatches `submodule_key=pgdasd` por esse gancho

#### Scenario: Sucesso PGMEI enfileira PGMEI

- **WHEN** uma run agendada `simples_mei` com `service_code` contendo `PGMEI` conclui com sucesso e o cliente está elegível para automático PGMEI
- **THEN** o sistema enfileira no submódulo `pgmei` com `idempotency_key` ≤ 64

### Requirement: Envio PGDAS-D exige documentos locais

Para o submódulo PGDAS-D (`module_key=simples_mei`, `submodule_key=pgdasd`), envio manual e automático SHALL exigir ao menos um artefato local (`pgdasd_artifacts`) do cliente, alinhado ao `can_send` da prévia. Sem artefato, o envio manual MUST responder 422 e o automático MUST NOT enfileirar. Provider externo permanece fail-closed por default.

#### Scenario: Send manual sem documentos

- **WHEN** o operador chama `communication-send` PGDAS-D com canais elegíveis mas sem artefatos locais
- **THEN** a API responde 422 e nenhum dispatch é criado

#### Scenario: Send manual com documentos

- **WHEN** o operador chama `communication-send` PGDAS-D com canais elegíveis e ao menos um artefato local
- **THEN** a API enfileira dispatch(es) e respeita o kill-switch do provider

#### Scenario: Automático sem documentos

- **WHEN** o gancho pós-consulta tentaria enfileirar automático PGDAS-D mas o cliente não tem artefatos locais
- **THEN** nenhum dispatch é criado

### Requirement: Idempotência e dedupe do envio automático

Dispatches de comunicação (`client_communication_dispatches`) SHALL usar `idempotency_key` com no máximo 64 caracteres. No gancho pós-consulta (`scheduled_consult`), a chave SHALL ser estável por office+cliente+módulo+submódulo+canal+`period_key` de forma que uma segunda execução elegível no mesmo período MUST NOT criar novo dispatch. Envio manual SHALL permanecer reenviável com chave única curta. Provider externo permanece fail-closed por default.

#### Scenario: Automático cria dispatch com chave curta

- **WHEN** o gancho pós-consulta enfileira comunicação automática elegível (ex.: PGDAS-D com documentos locais)
- **THEN** o dispatch é persistido com `idempotency_key` de comprimento ≤ 64

#### Scenario: Segunda consulta no mesmo período não duplica

- **WHEN** o gancho automático elegível roda de novo para o mesmo cliente/canal/`period_key` já enfileirado
- **THEN** nenhum dispatch adicional é criado

#### Scenario: Send manual permanece reenviável

- **WHEN** o operador chama `communication-send` duas vezes com canais elegíveis e documentos locais
- **THEN** ambos os envios enfileiram (chaves distintas ≤ 64) sem colidir na unique office+idempotency

## ADDED Requirements

### Requirement: Falha de rede do sidecar MEI é classificada para fallback
O sistema SHALL traduzir falhas de conexão/DNS do cliente HTTP da automação MEI (`MeiAutomationClient`) em `MeiAutomationTransportException` com código `AUTOMATION_TRANSPORT_ERROR`, de modo que o provider portal possa emitir `PORTAL_UNAVAILABLE` elegível a fallback SERPRO quando a política for `portal_then_serpro`.

#### Scenario: Host do sidecar inalcançável na criação do job
- **WHEN** a política inclui Receita portal antes de SERPRO e a chamada `POST /v1/jobs` ao sidecar falha por conexão/DNS
- **THEN** o transporte não propaga `ConnectionException` crua ao job e o roteador tenta o provider SERPRO da mesma operação

#### Scenario: HTTP de erro do sidecar continua classificado
- **WHEN** o sidecar responde com status HTTP de erro (ex.: 503)
- **THEN** o cliente lança `MeiAutomationTransportException` como já fazia e o fallback elegível permanece disponível

### Requirement: SERPRO é o caminho canônico padrão para PGMEI dívida ativa
O produto SHALL documentar e manter defaults de configuração (`MEI_AUTOMATION_PROVIDER_PGMEI_DEBT` e afins em `.env.example`) como `serpro`, usando Integra Contador (`idSistema` PGMEI / `idServico` DIVIDAATIVA24) sem exigir o serviço Compose `mei`.

#### Scenario: Default de exemplo aponta SERPRO
- **WHEN** um ambiente é provisionado a partir de `apps/api/.env.example`
- **THEN** `MEI_AUTOMATION_PROVIDER_PGMEI_DEBT` vale `serpro` e a consulta PGMEI de dívida ativa não depende de resolver o host `mei`

### Requirement: Run de monitoramento não permanece RUNNING após falha do job
O sistema SHALL, no callback `failed` de `ExecuteFiscalMonitoringRunJob`, marcar a `FiscalMonitoringRun` associada como `FAILED` com código `JOB_UNHANDLED_EXCEPTION` quando a run ainda não estiver em estado terminal, sem alterar o reconcile específico de PGDASD RBT12.

#### Scenario: Exceção não tratada deixa a run terminal
- **WHEN** o job de execução da run falha com exceção não recuperada e a run está `RUNNING` (ou outro estado não terminal)
- **THEN** a run passa a `FAILED` com `error_code` `JOB_UNHANDLED_EXCEPTION` e mensagem sanitizada

#### Scenario: Run já terminal não é reescrita
- **WHEN** o callback `failed` executa e a run já está em estado terminal
- **THEN** o sistema não altera o status/resultado da run

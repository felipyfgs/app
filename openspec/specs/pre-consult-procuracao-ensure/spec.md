## Purpose

Capability `pre-consult-procuracao-ensure` — requisitos sincronizados das changes OpenSpec.
## Requirements
### Requirement: Ensure procurações before Integra consult that requires e-CAC power

Antes de executar uma consulta Integra Contador que exige poder e-CAC (incluindo monitoramento/consulta Simples/MEI PGDASD, monitoramento/refresh SITFIS com poder `00002`, e operações equivalentes nos mesmos adapters), o sistema SHALL assegurar evidência de procuração usável do cliente no office/ambiente corrente. O fluxo SHALL ser: (1) verificar tabelas locais de poderes/snapshot; (2) se o poder exigido estiver ausente, revogado, expirado ou stale, chamar Integra Procurações (`procuracoes.obter`); (3) atualizar a projeção local; (4) só então seguir para a consulta alvo. O sistema SHALL NÃO pular a consulta alvo quando a evidência local já for usável. Se após o sync a evidência continuar insuficiente, o sistema SHALL bloquear a consulta com código de elegibilidade de procuração (ex. `PROXY_POWER_MISSING`) e SHALL NÃO chamar o serviço alvo (ex. PGDASD ou SITFIS).

#### Scenario: Local power usable skips remote sync

- **WHEN** uma consulta PGDASD ou SITFIS é disparada para um cliente e já existe poder e-CAC usável localmente para o requisito da operação
- **THEN** o sistema NÃO chama Integra Procurações
- **AND** prossegue para a consulta alvo

#### Scenario: Missing local power syncs then consults

- **WHEN** a consulta exige poder e-CAC e a evidência local do cliente está ausente, revogada ou stale
- **THEN** o sistema chama Integra Procurações para o par outorgante/outorgado do cliente/office
- **AND** atualiza as tabelas locais de poderes/snapshot
- **AND** se após o sync o poder for usável, executa a consulta alvo

#### Scenario: Sync still insufficient blocks target consult

- **WHEN** o sync de procurações completa e o poder exigido continua ausente ou inválido
- **THEN** a consulta alvo NÃO é executada
- **AND** o resultado é bloqueado com código de procuração/elegibilidade apropriado

#### Scenario: SITFIS monitoring ensures power 00002

- **WHEN** um `FiscalMonitoringRun` SITFIS (monitoring ou refresh) é executado
- **THEN** o ensure de procuração para o poder `00002` roda antes da primeira chamada Integra SITFIS
- **AND** se o ensure falhar, SITFIS NÃO é chamado

### Requirement: Same ensure for monitoring and manual consult paths

O ensure de procuração pré-consulta SHALL aplicar-se aos caminhos de execução de runs fiscais Simples/MEI e SITFIS (monitoring/manual via adapter) e SHALL ser o mesmo contrato usado pelo fluxo de consulta manual, evitando políticas divergentes (só enfileirar sem atualizar antes da consulta).

#### Scenario: Monitoring run triggers ensure

- **WHEN** um `FiscalMonitoringRun` Simples/MEI ou SITFIS que exige poder e-CAC é executado
- **THEN** o ensure de procuração roda antes da avaliação final de elegibilidade/chamada Integra do serviço alvo

### Requirement: A1 invalidation clears stale procuracao projection

Quando a autorização do office invalidar poderes derivados (remoção/substituição de A1 ou mudança de autor), o sistema SHALL invalidar ou marcar como não verificada a projeção de procuração dos clientes afetados no ambiente, de modo que a próxima consulta dispare o ensure (e eventual sync) em vez de confiar em snapshot autorizado desatualizado.

#### Scenario: After A1 replace next consult re-syncs

- **WHEN** o A1 do escritório é substituído ou removido e poderes locais são revogados
- **THEN** a projeção de procuração deixa de ser tratada como autorizada/fresca sem novo sync
- **AND** a próxima consulta do cliente que exigir poder dispara o ensure com sync remoto se necessário

### Requirement: Tenant isolation and fail-closed

O ensure e o sync SHALL operar apenas no office do cliente da sessão (sem cruzar tenants). Em falha do sync remoto (timeout, driver indisponível, erro Integra), o sistema SHALL falhar fechado: NÃO inventar poder ACTIVE e NÃO executar a consulta alvo.

#### Scenario: Remote sync failure blocks consult

- **WHEN** o ensure precisa sincronizar e a chamada Integra Procurações falha
- **THEN** a consulta alvo NÃO executa
- **AND** o erro/código indica falha de sincronização ou indisponibilidade de procuração

## ADDED Requirements

### Requirement: Inventário de consultas manuais somente-leitura
O sistema SHALL expor ao escritório do contexto atual um inventário de
ações de consulta manual derivadas das superfícies de monitoramento e do
catálogo oficial, incluindo apenas operações com estado oficial PRODUCTION,
suporte de plataforma IMPLEMENTED e não mutantes.

#### Scenario: Listar ações elegíveis do office
- **WHEN** um usuário autenticado com contexto de office válido solicita o
  inventário de consultas manuais
- **THEN** a resposta lista ações com identificador estável, rótulo, módulo
  ou superfície, estado de elegibilidade e, quando aplicável, schema mínimo
  de parâmetros, sem incluir operações mutantes ou PROSPECTION/CANCELED

#### Scenario: Bloqueio sem contexto de office
- **WHEN** a sessão não possui office atual resolvido por `CurrentOffice`
- **THEN** o sistema recusa o inventário e não aceita `office_id` enviado
  pelo cliente

### Requirement: Elegibilidade explícita por ação
O sistema SHALL calcular elegibilidade por ação considerando, no mínimo,
flag de módulo do domínio, capability SERPRO da família, presença de token
de autor quando a operação exige representação, e existência de handler de
adapter para a `operation_key`.

#### Scenario: Ação pronta
- **WHEN** módulo e capability permitem a família, o token e poderes
  exigidos estão satisfeitos e existe handler de adapter
- **THEN** a elegibilidade da ação é `ready`

#### Scenario: Ação bloqueada por capability
- **WHEN** a capability SERPRO da família está `disabled` ou a flag do
  módulo está desligada para o office
- **THEN** a elegibilidade não é `ready` e a execução é recusada com código
  estável sanitizado

#### Scenario: Adapter ausente
- **WHEN** a operação está no catálogo/superfície mas não há handler de
  consulta implementado
- **THEN** a elegibilidade é `adapter_missing` e a execução não monta
  envelope genérico a partir do payload do cliente

### Requirement: Execução manual confirmada e tenant-scoped
O sistema SHALL executar consulta manual somente via ação explícita
confirmada, resolvendo o contribuinte no office atual, despachando para o
adapter existente e persistindo projeção ou enfileirando fluxo assíncrono
já suportado (ex. SITFIS), sem aceitar mutações.

#### Scenario: Consulta síncrona com confirmação
- **WHEN** o usuário envia `confirmed: true`, `client_id` do office atual e
  uma ação `ready` com parâmetros válidos
- **THEN** o sistema despacha o adapter correspondente, persiste projeção
  ou evidência sanitizada no office e devolve resumo sem tokens, XML
  canônico ou bytes do cofre

#### Scenario: Recusa sem confirmação
- **WHEN** o usuário tenta executar sem confirmação explícita
- **THEN** o sistema recusa a execução e não chama o transporte SERPRO

#### Scenario: Cliente de outro office
- **WHEN** o `client_id` não pertence ao office atual
- **THEN** o sistema recusa a execução e não lê nem grava projeção
  cross-tenant

#### Scenario: Ação mutante rejeitada
- **WHEN** o identificador de ação corresponde a operação mutante do
  catálogo
- **THEN** o sistema recusa a execução mesmo se a flag de mutações estiver
  ligada em outro módulo

### Requirement: Leitura local sem coleta automática
O sistema SHALL servir inventário, histórico e projeções de consultas
manuais por GET somente a partir de dados locais do office, sem disparar
chamada SERPRO ao abrir a UI.

#### Scenario: Abrir explorador ou histórico
- **WHEN** o usuário abre o explorador de consultas manuais ou o histórico
  de uma ação para um cliente do office
- **THEN** a interface obtém dados locais e nenhuma coleta SERPRO é
  iniciada até POST confirmado

### Requirement: UI do explorador e CTAs nos módulos
O sistema SHALL apresentar no painel de monitoramento um explorador de
consultas manuais e, nas superfícies de módulo cobertas, um CTA que permita
disparar a mesma ação com confirmação e navegar ou atualizar a projeção
local do módulo após sucesso.

#### Scenario: Explorador filtra por módulo e cliente
- **WHEN** o operador seleciona um cliente e um módulo no explorador
- **THEN** a lista mostra apenas ações daquele recorte com estado de
  elegibilidade e resumo da última projeção local quando existir

#### Scenario: CTA no módulo usa o mesmo contrato
- **WHEN** o operador confirma uma consulta a partir da página do módulo de
  monitoramento
- **THEN** a execução usa o mesmo contrato de ação/elegibilidade do
  explorador e a UI recarrega a carteira ou histórico local do módulo

#### Scenario: Ação não ready desabilita execução
- **WHEN** a elegibilidade da ação não é `ready`
- **THEN** a UI não permite confirmar a consulta e exibe o motivo de
  bloqueio sanitizado

### Requirement: Isolamento e sanidade de saída
O sistema SHALL garantir que respostas de inventário, execução e histórico
não exponham segredos (tokens, PFX, XML canônico, caminhos internos do
cofre) e que downloads de artefato, quando existirem, usem descritores
autorizados do office atual.

#### Scenario: Resposta sem segredos
- **WHEN** qualquer endpoint do explorador de consultas manuais responde
  com sucesso ou erro
- **THEN** o corpo não contém `autenticar_procurador_token`, consumer
  secret, PEM/PFX nem XML canônico do termo

#### Scenario: Download cross-tenant negado
- **WHEN** a sessão tenta baixar artefato de evidência de outro office
- **THEN** o sistema recusa o download

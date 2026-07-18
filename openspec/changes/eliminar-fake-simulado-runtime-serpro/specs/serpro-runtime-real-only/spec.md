## ADDED Requirements

### Requirement: Driver SERPRO não possui modo simulado
O sistema SHALL aceitar somente `disabled` ou `real` como driver de capability SERPRO em qualquer ambiente, com `disabled` como default universal, e MUST rejeitar `simulated`, Fake, Mock ou valor desconhecido antes de executar operação.

#### Scenario: Configuração legada simulated
- **WHEN** uma capability recebe o valor `simulated`
- **THEN** o boot/preflight ou a resolução falha fechada sem instanciar cliente e sem realizar HTTP

#### Scenario: Capability sem configuração
- **WHEN** uma capability não possui valor explícito
- **THEN** ela resolve como `disabled` e nenhuma resposta de negócio é fabricada

### Requirement: Ambientes SERPRO usam somente endpoints oficiais publicados
O sistema SHALL aceitar somente `TRIAL` e `PRODUCTION` como ambiente SERPRO. O
ambiente `TRIAL` MUST usar o gateway oficial de demonstração configurado
operacionalmente, sem cliente sintético local, e `HOMOLOGATION` MUST ser rejeitado
enquanto não houver endpoint e credenciais oficiais do contrato.

#### Scenario: Execução Trial habilitada explicitamente
- **WHEN** o ambiente é `TRIAL`, a capability está `real` e o kill switch permite a chamada
- **THEN** o cliente usa o endpoint Trial oficial configurado e registra a proveniência como demonstração oficial, nunca como `SERPRO_REAL`

#### Scenario: Ambiente HOMOLOGATION legado
- **WHEN** uma API, configuração ou registro novo informa `HOMOLOGATION`
- **THEN** a validação rejeita o valor e nenhuma chamada HTTP é realizada

### Requirement: Container de aplicação não registra doubles SERPRO
O runtime MUST NOT carregar, registrar ou resolver clientes Fake/Simulated para OAuth, gateway Integra, Autentica Procurador, procurações, mailbox, DTE, parcelamentos, guias ou mutações; capability desligada SHALL produzir bloqueio explícito.

#### Scenario: Procurações desabilitadas
- **WHEN** o driver de autorização está `disabled`
- **THEN** a consulta falha com estado bloqueado e não cria poder, token ou resultado `ACTIVE`

#### Scenario: Ambiente testing sobe a aplicação
- **WHEN** o container é criado com `APP_ENV=testing`
- **THEN** nenhum provider de aplicação troca automaticamente HTTP real por cliente sintético

### Requirement: Doubles de teste são isolados do runtime
Os testes SHALL poder usar doubles determinísticos apenas sob namespace/autoload de testes e MUST instalá-los explicitamente; esses resultados MUST NOT ser gravados ou apresentados como homologação externa.

#### Scenario: Teste de timeout ou redirect
- **WHEN** a suíte precisa reproduzir uma falha de transporte
- **THEN** ela usa um double local sem rede e a evidência é classificada somente como teste offline

#### Scenario: Build de produção sem autoload-dev
- **WHEN** dependências de desenvolvimento não são carregadas
- **THEN** nenhuma classe Fake/Simulated SERPRO fica disponível ao runtime

### Requirement: PASS real exige proveniência PRODUCTION_CANARY
O probe SHALL emitir `PASS_REAL_SYNC`, `PASS_REAL_EMPTY`, `PASS_REAL_ASYNC_COMPLETE` ou `PASS_REAL_CACHE` somente quando a execução comprovar endpoint contratado, ambiente produtivo, contrato/credencial real, run aprovado `PRODUCTION_CANARY`, resposta semanticamente válida e `simulated=false`; nenhum metadado isolado poderá satisfazer o gate.

#### Scenario: Client in-process declara SERPRO_REAL
- **WHEN** uma resposta local informa `simulated=false` ou `sourceProvenance=SERPRO_REAL` sem prova externa completa
- **THEN** o probe rejeita o resultado como evidência real

#### Scenario: Trial retorna HTTP 200
- **WHEN** uma execução Trial/mock oficial termina com HTTP 200
- **THEN** ela permanece não elegível para PASS real

#### Scenario: Canário produtivo válido
- **WHEN** todas as dimensões de proveniência e semântica são verificadas e a operação não é simulada
- **THEN** o probe registra uma classificação `PASS_REAL_*` com timestamp, correlation tag e hash sanitizados

### Requirement: Claims históricos simulados não representam prontidão
O ledger, a UI e relatórios MUST reclassificar Fake, Simulated, Trial, `PASS_BUSINESS`, 4xx/5xx, `BLOCKED_HUB` e HTTP 304 sem reuso comprovado como não prontos, preservando a trilha histórica sem promoção a `READY_PRODUCTION`.

#### Scenario: Linha antiga PASS_BUSINESS
- **WHEN** a execução não comprova `PRODUCTION_CANARY` e uma classificação `PASS_REAL_*`
- **THEN** a linha fica `BLOCKED` ou histórica inválida com a pendência explícita

#### Scenario: Campo histórico simulated é lido
- **WHEN** um registro legado contém origem simulada
- **THEN** o sistema o exibe como não elegível e não o reutiliza para autorizar nova operação

## ADDED Requirements

### Requirement: FGTS/eSocial falha fechado sem provider oficial real
O sistema SHALL resolver uma implementação desabilitada de `EsocialEventClient` enquanto não existir provider real explicitamente habilitado e MUST NOT tratar indisponibilidade ou fila vazia sintética como sincronização bem-sucedida.

#### Scenario: Sync solicitado sem provider real
- **WHEN** uma rota ou job solicita sincronização FGTS/eSocial e não há client real habilitado
- **THEN** a execução retorna bloqueio/indisponibilidade, realiza zero HTTP e não persiste evidência ou projeção como sucesso

#### Scenario: Container em testing
- **WHEN** a aplicação sobe em `APP_ENV=testing`
- **THEN** o provider de aplicação continua resolvendo o client Disabled e não troca automaticamente para Fake

### Requirement: Double eSocial é exclusivo de testes
O double programável e seus builders de amostra SHALL existir somente sob autoload de testes e SHALL ser instalado explicitamente pelo teste que o utiliza.

#### Scenario: Teste offline de evento eSocial
- **WHEN** a suíte precisa enfileirar totalizador ou fechamento
- **THEN** ela registra o double `Tests\Support` sem rede e a saída permanece inelegível para evidência real

### Requirement: Legado eSocial sintético é inelegível
Evidências com versão `fake-1`, origem simulada ou proveniência ausente MUST permanecer identificáveis e MUST NOT alimentar KPI, prontidão ou alegação de homologação real.

#### Scenario: Registro histórico fake-1
- **WHEN** uma consulta encontra evidência eSocial versionada como `fake-1`
- **THEN** o registro é apresentado como legado inválido/quarentenado e não como fonte oficial

## ADDED Requirements

### Requirement: Fluxo de Eventos de Atualização deve respeitar o contrato oficial PF e PJ

O sistema MUST executar solicitações PF/PJ com envelope de lote oficial
(`contribuinte.tipo` 3/4) e ambas as operações de obtenção com `protocolo` e
`evento` obtidos exclusivamente da run persistida. O campo de dados de PJ MUST
ser centralizado em adapter/fixture versionada e MUST NOT ser escolhido por
conveniência enquanto a fonte oficial permanecer contraditória.

#### Scenario: Solicitação PJ

- **WHEN** um usuário autorizado confirma uma solicitação de eventos PJ
- **THEN** o adapter MUST usar o contrato PJ reconciliado, envelope tipo 4 e
  MUST NOT aceitar coordenadas SERPRO ou protocolo do navegador

#### Scenario: Divergência oficial do campo PJ

- **WHEN** a tabela e o exemplo oficial divergirem sobre o nome do campo PJ
- **THEN** o sistema MUST registrar a divergência e MUST bloquear egress Trial
  e produção até haver reconciliação oficial versionada

#### Scenario: Obtenção de resultado pendente

- **WHEN** o usuário autorizado solicita a atualização de uma run elegível
- **THEN** o sistema MUST carregar o protocolo e o evento da run do escritório
  corrente, enviar ambos ao executor e preservar o consumo one-shot

### Requirement: API de Eventos de Atualização deve ser tenant-scoped e sanitizada

O sistema MUST expor histórico, solicitação manual e obtenção explícita apenas
para cliente pertencente ao `CurrentOffice`. A projeção HTTP MUST omitir
`office_id`, `client_id`, protocolo, correlação, chaves de operação, matriz de
elementos, NI, CPF/CNPJ e payload externo bruto.

#### Scenario: Leitura do histórico local

- **WHEN** o cliente autenticado abre o histórico de eventos
- **THEN** o sistema MUST consultar somente runs locais do escritório e MUST
  NOT realizar egress para a SERPRO

#### Scenario: Referência estrangeira ou office_id

- **WHEN** o request apresenta `office_id` ou uma run/cliente de outro escritório
- **THEN** o sistema MUST rejeitar a autoridade enviada pelo navegador e MUST
  negar a referência estrangeira sem revelar dados

### Requirement: Painel de Eventos de Atualização deve exigir ações explícitas

O painel do detalhe do cliente MUST apresentar o histórico sanitizado e estados
de espera, bloqueio, limite, erro e conclusão. Ele MUST iniciar solicitação ou
obtenção somente após clique explícito e confirmação aplicável, sem polling
automático.

#### Scenario: Abertura do painel

- **WHEN** o usuário abre o painel de eventos
- **THEN** a interface MUST carregar apenas o histórico local e MUST NOT criar
  uma solicitação nem obter resultado externo

#### Scenario: Janela oficial ainda não atingida

- **WHEN** uma run ainda está antes de `not_before_at`
- **THEN** a interface MUST informar a espera e o backend MUST retornar o
  estado local sem chamar a SERPRO

## ADDED Requirements

### Requirement: Departamentos operacionais por escritório
O sistema SHALL permitir que `ADMIN` crie, altere, desative e liste departamentos operacionais do escritório ativo, com nome, sigla, cor e estado, e MUST manter cada departamento restrito ao respectivo `office_id`.

#### Scenario: Administrador cria departamento
- **WHEN** um `ADMIN` autenticado cria um departamento com dados válidos
- **THEN** o sistema deriva `office_id` da sessão, persiste o departamento nesse escritório e registra a criação em auditoria

#### Scenario: Operador tenta administrar departamento
- **WHEN** um `OPERATOR` ou `VIEWER` tenta criar, alterar ou desativar um departamento
- **THEN** o sistema rejeita a ação sem modificar dados

#### Scenario: Nome repetido em escritórios distintos
- **WHEN** dois escritórios criam departamentos com o mesmo nome ou sigla
- **THEN** ambos podem existir e nenhuma consulta de um escritório retorna o registro do outro

### Requirement: Departamento primário da membership
O sistema SHALL permitir associar uma membership ativa a no máximo um departamento primário do mesmo escritório e MUST rejeitar referências cruzadas ou departamentos inativos em novas atribuições padrão.

#### Scenario: Associação válida
- **WHEN** um `ADMIN` associa uma membership ativa a um departamento ativo do mesmo escritório
- **THEN** a associação é salva para uso em filtros, defaults e fila sem alterar memberships da mesma pessoa em outros escritórios

#### Scenario: Departamento de outro escritório
- **WHEN** uma requisição tenta associar uma membership a departamento pertencente a outro `office_id`
- **THEN** o sistema responde como recurso inexistente ou inválido e não revela dados do outro escritório

### Requirement: Modelos de processo com tarefas ordenadas
O sistema SHALL permitir que `ADMIN` mantenha modelos com nome, descrição, departamento e prazo padrão opcionais e uma sequência ordenada de tarefas padrão contendo título, descrição, regra de prazo, departamento, responsável, obrigatoriedade, criticidade e exigência de evidência.

#### Scenario: Modelo válido
- **WHEN** um `ADMIN` salva um modelo com duas tarefas e ordens distintas
- **THEN** o sistema persiste o modelo e devolve as tarefas na ordem canônica definida

#### Scenario: Responsável padrão inelegível
- **WHEN** uma tarefa de modelo referencia membership inativa ou de outro escritório
- **THEN** o sistema rejeita o modelo sem persistir a referência inelegível

#### Scenario: Modelo com histórico de geração
- **WHEN** um modelo já utilizado é desativado
- **THEN** ele deixa de aceitar novas gerações, mas seus dados e snapshots de processos existentes permanecem consultáveis

### Requirement: Regras determinísticas de prazo
O sistema SHALL calcular prazos no timezone do escritório a partir da competência e SHALL suportar dia fixo do mês, dias corridos após o início da competência e dias corridos antes do prazo do processo.

#### Scenario: Dia inexistente no mês
- **WHEN** uma regra solicita dia 31 para uma competência cujo mês termina antes
- **THEN** o cálculo usa o último dia civil daquela competência e informa esse valor no preview

#### Scenario: Regra dependente sem prazo do processo
- **WHEN** uma tarefa usa dias antes do prazo do processo e nenhum prazo padrão ou override foi informado
- **THEN** o preview marca conflito bloqueante e a confirmação não pode ocorrer

#### Scenario: Virada de dia no escritório
- **WHEN** servidor e escritório estão em timezones distintos durante uma virada de data
- **THEN** o cálculo usa o timezone configurado do escritório e produz a mesma data em preview e persistência

### Requirement: Preview canônico antes da geração por modelo
O sistema MUST gerar preview server-side para toda criação por modelo, sem criar processos ou tarefas, mostrando clientes, competência, processos, tarefas, prazos, responsáveis, alertas e conflitos calculados.

#### Scenario: Preview para múltiplos clientes
- **WHEN** um usuário autorizado seleciona um modelo, uma competência e vários clientes ativos do escritório
- **THEN** o sistema devolve um item de preview por cliente com as tarefas e prazos calculados pelo backend

#### Scenario: Cliente inativo ou externo
- **WHEN** a seleção contém cliente inativo ou pertencente a outro escritório
- **THEN** o item é bloqueado ou a requisição é rejeitada sem criar trabalho e sem revelar dados externos

#### Scenario: Duplicidade existente
- **WHEN** já existe processo gerado pelo mesmo modelo, cliente e competência no escritório
- **THEN** o preview identifica a duplicidade como conflito e não apresenta o item como nova criação confirmável

### Requirement: Confirmação idempotente e resiliente à concorrência
O sistema MUST confirmar um preview vigente uma única vez, revalidar sua versão e elegibilidade e criar cada processo com suas tarefas em uma transação tenant-scoped.

#### Scenario: Confirmação válida
- **WHEN** usuário autorizado confirma preview vigente e sem conflitos
- **THEN** o batch é enfileirado uma única vez e cada item concluído referencia o processo criado

#### Scenario: Retry da mesma confirmação
- **WHEN** cliente ou worker repete uma confirmação com a mesma idempotency key
- **THEN** o sistema devolve o batch original e não duplica processos nem tarefas

#### Scenario: Modelo alterado após preview
- **WHEN** o `lock_version` do modelo muda antes da confirmação
- **THEN** o sistema rejeita o preview como desatualizado e exige nova prévia

#### Scenario: Duas confirmações concorrentes
- **WHEN** duas confirmações tentam criar o mesmo modelo, cliente e competência simultaneamente
- **THEN** a constraint tenant-scoped permite no máximo um processo e o outro item termina como duplicidade sem falha inconsistente

### Requirement: Snapshot das instâncias geradas
O sistema MUST preservar nas instâncias o snapshot do modelo e dos cálculos usados na geração, e SHALL NOT reescrever processos ou tarefas existentes quando o modelo for editado posteriormente.

#### Scenario: Edição posterior do modelo
- **WHEN** um `ADMIN` renomeia uma tarefa ou altera sua regra de prazo após uma geração
- **THEN** novas prévias usam a versão atual e o processo já gerado conserva título, prazo e regra materializados anteriormente

### Requirement: Isolamento da modelagem em relação aos canais fiscais
O sistema SHALL NOT chamar SERPRO, ADN, SEFAZ, alterar cursores NSU/nNF ou materializar mutação fiscal como efeito de criar, editar, visualizar ou gerar modelos operacionais.

#### Scenario: Geração de processo operacional
- **WHEN** um batch de processos é confirmado e concluído
- **THEN** somente dados operacionais tenant-scoped e auditoria são alterados, sem chamada fiscal externa ou avanço de cursor


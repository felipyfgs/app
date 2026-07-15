## ADDED Requirements

### Requirement: Catálogo de obrigações com cobertura e aplicabilidade
O sistema SHALL manter obrigações versionadas por regime, período, contribuinte e fonte oficial e MUST distinguir `APPLICABLE`, `NOT_APPLICABLE`, `UNKNOWN` e `UNSUPPORTED` antes de calcular pendência.

#### Scenario: Obrigação não aplicável
- **WHEN** regra oficial versionada e dados confirmados indicam que a obrigação não se aplica
- **THEN** a competência fica `NOT_APPLICABLE` com fundamento e versão da regra

#### Scenario: Regime desconhecido
- **WHEN** não há evidência suficiente para decidir aplicabilidade
- **THEN** o sistema mantém `UNKNOWN` e não cria pendência presumida

### Requirement: Situação de entrega exige evidência oficial
O sistema SHALL marcar declaração como entregue somente quando recibo, protocolo ou resposta oficial conclusiva estiver vinculado à obrigação e competência.

#### Scenario: Recibo localizado
- **WHEN** consulta retorna recibo válido para contribuinte e competência
- **THEN** a obrigação fica `UP_TO_DATE` com vínculo à evidência

#### Scenario: Arquivo interno sem protocolo
- **WHEN** existe documento enviado pelo usuário, mas não confirmação oficial de entrega
- **THEN** o sistema o apresenta como artefato interno e mantém entrega `UNKNOWN` ou `PENDING`

### Requirement: Prazos são versionados e timezone-aware
O sistema SHALL calcular vencimentos a partir de calendário/regra versionados, timezone aplicável e exceções oficiais, preservando a regra usada no snapshot.

#### Scenario: Prazo é alterado oficialmente
- **WHEN** nova regra prorroga vencimento antes da data original
- **THEN** competências abertas usam a nova versão e histórico preserva o cálculo anterior auditável

### Requirement: Central consolida sem apagar detalhes da origem
O sistema SHALL apresentar visão agregada por escritório, cliente, obrigação, competência e situação, permitindo navegar até evidência e módulo de origem.

#### Scenario: Cliente possui obrigações em módulos distintos
- **WHEN** o usuário abre a central do cliente
- **THEN** PGDAS, DASN-SIMEI e DCTFWeb aparecem separadamente com estados e fontes próprios

### Requirement: Transmissão não é consequência automática de pendência
O sistema MUST NOT transmitir declaração automaticamente ao detectar `PENDING`; qualquer transmissão exige capability mutante habilitada e controles específicos.

#### Scenario: Agendamento encontra declaração pendente
- **WHEN** reconciliação detecta ausência de entrega
- **THEN** o sistema cria pendência operacional e não envia declaração sem ação autorizada


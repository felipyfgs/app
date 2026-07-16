## ADDED Requirements

### Requirement: Contexto tenant fail-closed
Toda consulta ou mutação de dados de escritório MUST exigir contexto de tenant resolvido de uma membership ativa; ausência ou invalidez desse contexto MUST interromper a operação sem retornar dados.

#### Scenario: Global scope sem escritório
- **WHEN** um model de tenant é consultado sem `CurrentOffice` válido
- **THEN** o sistema lança falha segura e não remove silenciosamente o filtro de `office_id`

#### Scenario: Job de plataforma explicitamente privilegiado
- **WHEN** uma rotina global precisa enumerar escritórios
- **THEN** ela usa contexto privilegiado tipado, limita a operação ao propósito declarado e registra auditoria sem herdar acesso fiscal para o usuário de plataforma

### Requirement: Escritório selecionado vinculado à membership
O escritório ativo do usuário SHALL ser representado por uma membership ativa selecionada e MUST ser invalidado quando a membership for desativada, removida ou deixar de pertencer ao usuário.

#### Scenario: Seleção válida
- **WHEN** o usuário escolhe um escritório entre suas memberships ativas
- **THEN** a sessão passa a derivar o tenant dessa membership e não aceita `office_id` livre do navegador

#### Scenario: Membership revogada
- **WHEN** a membership selecionada é revogada
- **THEN** a sessão perde imediatamente o contexto daquele escritório e exige nova seleção autorizada

### Requirement: Coerência referencial por escritório
Relações persistentes entre entidades de tenant MUST incluir coerência de `office_id` no PostgreSQL e MUST NOT depender somente de policies ou scopes Laravel.

#### Scenario: FK simples válida, tenant inválido
- **WHEN** um filho usa o ID existente de um pai que pertence a outro escritório
- **THEN** a FK composta rejeita a escrita mesmo que ambos os IDs isolados existam

#### Scenario: Backfill encontra referência cruzada
- **WHEN** a auditoria pré-constraint encontra pai e filho em escritórios diferentes
- **THEN** o sistema não valida a constraint, registra apenas identificadores sanitizados e exige correção explícita


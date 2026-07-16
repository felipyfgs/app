## ADDED Requirements

### Requirement: Escritório como tenant comercial e de segurança
O sistema SHALL representar cada empresa contábil contratante como um `Office` isolado, com plano, estado de assinatura, vigência, limites e histórico próprios, e MUST derivar o `office_id` efetivo de uma membership autorizada.

#### Scenario: Ativação de escritório
- **WHEN** um escritório conclui o onboarding e possui plano válido
- **THEN** o sistema ativa o tenant sem criar acesso para os contribuintes finais cadastrados nele

#### Scenario: Office forjado
- **WHEN** uma requisição informa livremente um `office_id` diferente da membership ativa
- **THEN** o sistema ignora o valor, impede acesso cruzado e não revela a existência do outro tenant

### Requirement: Ciclo de vida do tenant preserva evidências
O sistema SHALL aplicar os estados `TRIAL`, `ACTIVE`, `PAST_DUE`, `SUSPENDED` e `CANCELED`; suspensão ou cancelamento MUST impedir novas chamadas externas e mutações, mas MUST NOT apagar ledger, auditoria, snapshots ou evidências fiscais sujeitos à retenção.

#### Scenario: Escritório suspenso
- **WHEN** um tenant passa para `SUSPENDED`
- **THEN** novos jobs externos não iniciam e o histórico autorizado permanece disponível em modo somente leitura

### Requirement: Troca explícita entre memberships autorizadas
O sistema SHALL permitir troca de tenant somente quando o usuário possuir membership ativa em ambos os escritórios e SHALL auditar origem, destino, usuário e horário da troca.

#### Scenario: Usuário com dois escritórios
- **WHEN** um usuário seleciona outro escritório no qual possui membership ativa
- **THEN** a sessão passa a usar o novo tenant e caches, queries e ações subsequentes são recalculados para esse `office_id`

#### Scenario: Escritório sem membership
- **WHEN** um usuário tenta selecionar um escritório sem membership ativa
- **THEN** o sistema rejeita a troca e mantém o tenant anterior sem revelar dados do alvo

### Requirement: Administração global separada dos papéis do tenant
O sistema MUST separar `PLATFORM_ADMIN` dos papéis `ADMIN`, `OPERATOR` e `VIEWER`; administração global MUST NOT conceder leitura implícita de conteúdo fiscal, mensagens, relatórios ou evidências de qualquer escritório.

#### Scenario: Administrador da plataforma consulta dado fiscal
- **WHEN** um `PLATFORM_ADMIN` sem membership do tenant solicita um recurso fiscal do escritório
- **THEN** o sistema nega o acesso mesmo que o administrador possa gerir estado, plano ou saúde sanitizada do tenant

### Requirement: Escopos globais e de tenant são estruturalmente distintos
O sistema MUST manter contrato SERPRO, credencial contratante, preços e consolidação de fatura em recursos globais e MUST manter autorizações, procurações, consumo atribuído e dados fiscais vinculados a `office_id` obrigatório.

#### Scenario: Recurso global recebe office_id
- **WHEN** um comando tenta criar contrato SERPRO global como se pertencesse a um escritório
- **THEN** o sistema rejeita a modelagem ambígua e preserva um único escopo global por ambiente


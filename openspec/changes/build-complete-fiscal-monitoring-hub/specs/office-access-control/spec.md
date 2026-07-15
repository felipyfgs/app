## MODIFIED Requirements

### Requirement: Isolamento por escritório
O sistema MUST derivar o escritório ativo de uma membership válida do usuário, MUST exigir troca explícita quando houver mais de uma opção e MUST impedir leitura ou alteração de recursos pertencentes a outro escritório em API, job, cache, lock, storage, exportação e métricas.

#### Scenario: Identificador de outro escritório
- **WHEN** um usuário solicita um recurso válido pertencente a outro escritório
- **THEN** o sistema responde como recurso não encontrado e não revela sua existência

#### Scenario: Troca autorizada de escritório
- **WHEN** um usuário seleciona outra membership ativa
- **THEN** o contexto autenticado é substituído, a troca é auditada e nenhum cache ou dado do tenant anterior permanece na resposta seguinte

#### Scenario: Job revalida tenant
- **WHEN** um job enfileirado é executado após a membership, assinatura ou autorização ser revogada
- **THEN** o job encerra antes da chamada externa e não usa o contexto antigo

### Requirement: Usuários exclusivamente internos
O sistema SHALL permitir acesso fiscal somente a funcionários associados ao escritório por membership e SHALL NOT criar contas para os contribuintes finais no MVP. Administradores da plataforma SHALL usar autorização global separada e MUST NOT receber acesso fiscal implícito aos tenants.

#### Scenario: Cadastro de cliente
- **WHEN** um funcionário cadastra um cliente contábil
- **THEN** o sistema não cria credenciais de login nem associação de usuário para esse cliente

#### Scenario: Administrador da plataforma sem membership
- **WHEN** um administrador global tenta abrir conteúdo fiscal de um escritório
- **THEN** o sistema nega o acesso, embora permita gerir metadados comerciais e saúde sanitizada da plataforma

## ADDED Requirements

### Requirement: 2FA recente para ações fiscais de alto risco
O sistema MUST exigir confirmação TOTP recente, além do login e papel, para gestão de credenciais SERPRO, Termos, procurações, limites, emissões e transmissões classificadas como alto risco.

#### Scenario: Sessão antiga de administrador
- **WHEN** `ADMIN` autenticado inicia ação de alto risco fora da janela de confirmação
- **THEN** o sistema exige novo TOTP antes de reservar consumo ou modificar estado

### Requirement: Papéis do tenant não administram recursos globais
Os papéis `ADMIN`, `OPERATOR` e `VIEWER` MUST restringir-se ao escritório ativo e MUST NOT gerir contrato global, credencial contratante, preço SERPRO, fatura consolidada ou outro tenant.

#### Scenario: Admin do escritório tenta alterar contrato SERPRO
- **WHEN** `ADMIN` do tenant chama rota global de contrato
- **THEN** o sistema nega a ação e não revela `Consumer Key`, contrato ou metadados restritos


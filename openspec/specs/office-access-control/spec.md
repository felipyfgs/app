# Office Access Control

## Purpose

Autenticação interna (sessão CSRF), TOTP para ADMIN, papéis e isolamento multi-tenant por office_id.

## Requirements

### Requirement: Autenticação interna segura
O sistema SHALL autenticar funcionários do escritório por sessão stateful protegida por CSRF e SHALL encerrar a sessão por solicitação explícita do usuário.

#### Scenario: Login válido
- **WHEN** um usuário ativo informa credenciais válidas
- **THEN** o sistema cria uma sessão vinculada ao usuário e ao escritório autorizado

#### Scenario: Requisição sem CSRF
- **WHEN** uma sessão tenta executar uma operação mutável sem token CSRF válido
- **THEN** o sistema rejeita a operação sem modificar dados

### Requirement: Segundo fator para administradores
O sistema MUST exigir TOTP configurado e confirmado para todo usuário com perfil `ADMIN` antes de liberar funções administrativas.

#### Scenario: Administrador sem segundo fator
- **WHEN** um administrador autenticado ainda não confirmou o TOTP
- **THEN** o sistema permite apenas concluir o segundo fator ou encerrar a sessão

### Requirement: Autorização por perfil
O sistema SHALL aplicar os perfis `ADMIN`, `OPERATOR` e `VIEWER` em todas as rotas e ações de negócio.

#### Scenario: Operador tenta administrar certificado
- **WHEN** um operador tenta enviar, substituir ou consultar o material de um certificado
- **THEN** o sistema nega a ação e registra a tentativa

#### Scenario: Usuário de consulta altera cliente
- **WHEN** um usuário `VIEWER` tenta criar ou alterar um cliente
- **THEN** o sistema nega a operação sem modificar o cadastro

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

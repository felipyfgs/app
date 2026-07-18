# Hub fiscal multi-tenant

Este contexto define a linguagem canônica de identidade, tenancy e autorização do hub fiscal.

## Linguagem

**Tenant**:
Espaço operacional isolado que possui usuários, clientes, configurações, credenciais, módulos e registros fiscais próprios.
_Evitar_: conta, organização ou plataforma quando a intenção for o espaço isolado; `Office` permanece apenas como nome técnico legado.

**Administrador da plataforma (`platform_admin`)**:
Usuário com autoridade global para administrar tenants e outros administradores da plataforma; sua autoridade global não constitui membership automática nem mistura dados de tenants.
_Evitar_: proprietário, superadmin, root, `PLATFORM_ADMIN`.

**Administrador do tenant (`tenant_admin`)**:
Membership com autoridade administrativa completa dentro de um único tenant.
_Evitar_: administrador da plataforma, `ADMIN`, admin sem qualificação.

**Usuário do tenant (`tenant_user`)**:
Membership comum cujas capacidades dentro de um tenant são determinadas por um perfil de permissão.
_Evitar_: `OPERATOR`, `VIEWER`, usuário global.

**Perfil de permissão**:
Conjunto nomeado de capacidades que um tenant atribui a seus `tenant_user`.
_Evitar_: papel, cargo, role global.

**Tenant principal**:
Tenant operacional provisionado para a carteira direta do primeiro `platform_admin`, no qual ele também possui membership `tenant_admin`.
_Evitar_: tenant plataforma, `platform_admin` como tenant.

**Contexto privilegiado de tenant**:
Seleção explícita e auditada pela qual um `platform_admin` atua temporariamente com capacidades efetivas de `tenant_admin` em um tenant ativo.
_Evitar_: bypass global, acesso implícito, impersonação.

**Carteira direta**:
Conjunto de clientes pertencentes ao tenant principal e operados diretamente pelo administrador inicial.
_Evitar_: clientes globais, carteira da plataforma.

**Desprovisionamento de tenant**:
Encerramento lógico e auditado que bloqueia a operação do tenant enquanto preserva dados sujeitos a retenção.
_Evitar_: hard delete, purge, cascade.

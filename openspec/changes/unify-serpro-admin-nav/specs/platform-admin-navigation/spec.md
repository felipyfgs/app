## MODIFIED Requirements

### Requirement: Sidebar Admin expõe o console SERPRO
Para usuários com acesso de plataforma (`PLATFORM_ADMIN` / `canAccessPlatformAdmin`), o grupo **Admin** da sidebar SHALL incluir um único atalho **SERPRO** (além de Escritórios e Módulos fiscais), apontando para `/admin/serpro` e ativo em qualquer path sob `/admin/serpro`. O sidebar MUST NOT listar Operação, Integração ou Canário DTE como filhos separados do grupo Admin.

#### Scenario: PLATFORM_ADMIN vê um SERPRO no Admin
- **WHEN** um usuário com `is_platform_admin` carrega a navegação principal
- **THEN** o grupo Admin inclui exatamente um filho com label `SERPRO` e `to` `/admin/serpro`, e MUST NOT incluir labels que comecem com `SERPRO ·`

#### Scenario: Item SERPRO ativo em qualquer subrota
- **WHEN** o path atual começa com `/admin/serpro`
- **THEN** o item Admin SERPRO aparece ativo

#### Scenario: Ordem estável do grupo Admin
- **WHEN** a lista de filhos de Admin é montada
- **THEN** a ordem é Escritórios, Módulos fiscais, SERPRO

### Requirement: Console SERPRO navega Operação, Integração e Canário no shell
O shell `/admin/serpro` SHALL expor navegação contextual derivada de `SERPRO_NAV_ITEMS` (Operação, Integração, Canário DTE) para alternar entre as rotas canônicas do console, sem depender de itens duplicados no sidebar global.

#### Scenario: Abas do console
- **WHEN** o PLATFORM_ADMIN abre `/admin/serpro` (ou qualquer subrota autorizada do console)
- **THEN** o shell mostra navegação de seção com Operação, Integração e Canário DTE

#### Scenario: Integração pela aba
- **WHEN** o usuário aciona Integração na navegação do console
- **THEN** a navegação aponta para `/admin/serpro/configuration`

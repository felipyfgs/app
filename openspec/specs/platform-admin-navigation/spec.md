## Purpose

Capability `platform-admin-navigation` — requisitos sincronizados das changes OpenSpec.

## Requirements

### Requirement: Sidebar Admin expõe o console SERPRO
Para usuários com acesso de plataforma (`PLATFORM_ADMIN` / `canAccessPlatformAdmin`), o grupo **Admin** da sidebar SHALL incluir atalhos para o console SERPRO além de Escritórios e Módulos fiscais. Os atalhos SHALL derivar de `SERPRO_NAV_ITEMS` e apontar para as rotas canônicas `/admin/serpro`, `/admin/serpro/configuration` e `/admin/serpro/dte-canary` (labels no formato `SERPRO · {label do catálogo}`).

#### Scenario: PLATFORM_ADMIN vê SERPRO no Admin
- **WHEN** um usuário com `is_platform_admin` (ou equivalente) carrega a navegação principal
- **THEN** o grupo Admin inclui filhos cujo label começa com `SERPRO ·` e cujos `to` cobrem Operação, Integração e Canário DTE

#### Scenario: Integração abre a configuração
- **WHEN** o usuário aciona o item Admin correspondente a Integração SERPRO
- **THEN** a navegação aponta para `/admin/serpro/configuration`

#### Scenario: Ordem estável do grupo Admin
- **WHEN** a lista de filhos de Admin é montada
- **THEN** Escritórios e Módulos fiscais aparecem antes dos itens SERPRO

#### Scenario: PLATFORM_ADMIN vê um SERPRO no Admin
- **WHEN** um usuário com `is_platform_admin` carrega a navegação principal
- **THEN** o grupo Admin inclui exatamente um filho com label `SERPRO` e `to` `/admin/serpro`, e MUST NOT incluir labels que comecem com `SERPRO ·`

#### Scenario: Item SERPRO ativo em qualquer subrota
- **WHEN** o path atual começa com `/admin/serpro`
- **THEN** o item Admin SERPRO aparece ativo

### Requirement: Console SERPRO navega Operação, Integração e Canário no shell
O shell `/admin/serpro` SHALL expor navegação contextual com exatamente dois itens derivados de `SERPRO_NAV_ITEMS`: **Visão geral** (`/admin/serpro`) e **Configuração** (`/admin/serpro/configuration`). O catálogo MUST NOT incluir Canário DTE como item de navegação do shell.

#### Scenario: Abas do console
- **WHEN** o PLATFORM_ADMIN abre `/admin/serpro` (ou qualquer subrota do console)
- **THEN** o shell mostra navegação de seção com Visão geral e Configuração (somente)

#### Scenario: Configuração pela aba
- **WHEN** o usuário aciona Configuração na navegação do console
- **THEN** a navegação aponta para `/admin/serpro/configuration`

#### Scenario: Canário fora do menu
- **WHEN** o catálogo `SERPRO_NAV_ITEMS` é renderizado no shell
- **THEN** não existe item cujo destino seja `/admin/serpro/dte-canary`

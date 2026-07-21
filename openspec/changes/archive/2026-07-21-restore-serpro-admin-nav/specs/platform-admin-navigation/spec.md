## ADDED Requirements

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

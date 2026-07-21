## MODIFIED Requirements

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

## ADDED Requirements

### Requirement: Visão geral SERPRO é a superfície operacional diária
A rota `/admin/serpro` SHALL apresentar a visão operacional (ambiente, prontidão/gates, kill switch, contrato ativo) sem abas locais de Consumo ou Liberação. Links secundários MAY apontar para deep-links de consumo, liberação e canário.

#### Scenario: Sem tabs Status/Consumo/Liberação
- **WHEN** o usuário abre `/admin/serpro`
- **THEN** a página MUST NOT renderizar navegação local `ShellScrollableTabs` com seções Consumo ou Liberação

#### Scenario: Conteúdo operacional presente
- **WHEN** o usuário abre `/admin/serpro` com acesso autorizado
- **THEN** a página expõe controles de ambiente, prontidão e kill switch

### Requirement: Configuração SERPRO é a superfície de credenciais e limites
A rota `/admin/serpro/configuration` SHALL apresentar configuração essencial (credenciais, onboarding de PRODUÇÃO quando aplicável, limites de ciclo) sem abas locais de Contratos ou Cobertura. A página MUST NOT exibir grade de escritórios pendentes nem histórico longo de credenciais no fluxo diário. Links secundários MAY apontar para deep-links de contratos e catálogo.

#### Scenario: Sem tabs Acesso/Contratos/Cobertura
- **WHEN** o usuário abre `/admin/serpro/configuration`
- **THEN** a página MUST NOT renderizar navegação local `ShellScrollableTabs` com seções Contratos ou Cobertura

#### Scenario: Sem pending offices nem histórico longo
- **WHEN** o usuário abre a Configuração no fluxo diário
- **THEN** a UI MUST NOT incluir a grade de escritórios pendentes nem a lista de histórico longo de credenciais

### Requirement: Deep-links ops permanecem
As rotas `/admin/serpro/usage`, `/admin/serpro/rollout`, `/admin/serpro/contracts`, `/admin/serpro/catalog` e `/admin/serpro/dte-canary` SHALL continuar acessíveis (direto ou via redirect), mesmo fora do menu do shell.

#### Scenario: Deep-link de consumo
- **WHEN** o usuário navega para `/admin/serpro/usage`
- **THEN** o sistema resolve a superfície de consumo (redirect ou página) sem exigir item no `SectionNavigation`

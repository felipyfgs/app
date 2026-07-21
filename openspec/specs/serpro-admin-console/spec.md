## Purpose

Capability `serpro-admin-console` — requisitos sincronizados das changes OpenSpec.

## Requirements

### Requirement: Visão geral SERPRO é a superfície operacional diária
A rota `/admin/serpro` SHALL apresentar a visão operacional (ambiente, prontidão/gates, kill switch, contrato ativo) sem abas locais de Consumo ou Liberação. Links secundários MAY apontar para deep-links de consumo, liberação e canário.

#### Scenario: Sem tabs Status/Consumo/Liberação
- **WHEN** o usuário abre `/admin/serpro`
- **THEN** a página MUST NOT renderizar navegação local `ShellScrollableTabs` com seções Consumo ou Liberação

#### Scenario: Conteúdo operacional presente
- **WHEN** o usuário abre `/admin/serpro` com acesso autorizado
- **THEN** a página expõe controles de ambiente, prontidão e kill switch

### Requirement: Configuração SERPRO é a superfície de credenciais e limites
A rota `/admin/serpro/configuration` SHALL apresentar configuração essencial (credenciais e limites de ciclo) sem abas locais de Contratos ou Cobertura. Em PRODUCTION, o aceite de ativação (consent) MAY aparecer no card Credenciais; a página MUST NOT exibir o card nem o fluxo de **Liberações externas** (gates documentais) no caminho diário. A página MUST NOT exibir grade de escritórios pendentes nem histórico longo de credenciais no fluxo diário. Links secundários MAY apontar para deep-links de contratos e catálogo. TRIAL e PRODUCTION SHALL compartilhar o mesmo shell de card **Credenciais** (certificado, key, secret, versão ativa/versões em preparação); PRODUCTION MUST NOT renderizar stepper de múltiplos passos nem formulário documental de evidências de gate. Identidade/CNPJ do contratante SHALL ser obtidos do certificado e do contexto do escritório/autorização — não de campos de liberação externa. Gates documentais externos MUST NOT bloquear egress faturável nem canário DTE em PRODUCTION na console operacional simplificada.

#### Scenario: Sem tabs Acesso/Contratos/Cobertura
- **WHEN** o usuário abre `/admin/serpro/configuration`
- **THEN** a página MUST NOT renderizar navegação local `ShellScrollableTabs` com seções Contratos ou Cobertura

#### Scenario: Sem pending offices nem histórico longo
- **WHEN** o usuário abre a Configuração no fluxo diário
- **THEN** a UI MUST NOT incluir a grade de escritórios pendentes nem a lista de histórico longo de credenciais

#### Scenario: Shell Credenciais unificado TRIAL e PRODUCTION
- **WHEN** o usuário alterna o ambiente entre Demonstração e Produção
- **THEN** ambos exibem o card de Credenciais com os mesmos campos principais (certificado, senha, key, secret)
- **AND** PRODUCTION NÃO exibe stepper de etapas (`serpro-prod-step-*`)
- **AND** a página NÃO renderiza `serpro-config-gates` nem o KPI de liberações externas no resumo

#### Scenario: Produção ativa sem liberações documentais na UI
- **WHEN** o ambiente é PRODUCTION e o onboarding produtivo está habilitado
- **THEN** o card Credenciais inclui o CTA de ativação (e consentimento se exigido pela API)
- **AND** não há seção de registro de evidência de gates (referência, responsável, data, resumo)

#### Scenario: Egress não bloqueia por gates documentais
- **WHEN** existem `serpro_external_gates` com status aberto e o ambiente é PRODUCTION
- **THEN** o gate de egress faturável MUST NOT falhar exclusivamente por gates documentais abertos

### Requirement: Deep-links ops permanecem
As rotas `/admin/serpro/usage`, `/admin/serpro/rollout`, `/admin/serpro/contracts`, `/admin/serpro/catalog` e `/admin/serpro/dte-canary` SHALL continuar acessíveis (direto ou via redirect), mesmo fora do menu do shell.

#### Scenario: Deep-link de consumo
- **WHEN** o usuário navega para `/admin/serpro/usage`
- **THEN** o sistema resolve a superfície de consumo (redirect ou página) sem exigir item no `SectionNavigation`

### Requirement: Avaliação local de Produção SERPRO
O stack local MAY operar com `FISCAL_PROFILE=production` para avaliação de consultas reais. Offices usados nessa avaliação MUST ter `serpro_segregation_class=PRODUCTION`. Com perfil `dev`, drivers MUST permanecer fixture. Egress faturável em PRODUCTION MUST continuar exigindo segregação explícita PRODUCTION (fail-closed para unset).

#### Scenario: Perfil production habilita drivers reais
- **WHEN** `FISCAL_PROFILE=production` no ambiente local
- **THEN** os drivers de capability SERPRO resolvem para `real` (não `fixture`)

#### Scenario: Office sem segregação PRODUCTION bloqueia egress
- **WHEN** um office tem `serpro_segregation_class` nulo ou distinto de PRODUCTION
- **AND** a rota é faturável em ambiente PRODUCTION
- **THEN** o egress MUST falhar com código de segregação

#### Scenario: Office PRODUCTION elegível
- **WHEN** o office tem `serpro_segregation_class=PRODUCTION`
- **AND** kill switch inativo e demais checks de egress passam
- **THEN** o gate de segregação MUST passar

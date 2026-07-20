## MODIFIED Requirements

### Requirement: Configuração SERPRO é a superfície de credenciais e limites
A rota `/admin/serpro/configuration` SHALL apresentar configuração essencial (credenciais, limites de ciclo e, em PRODUCTION, aceite de ativação + liberações externas) sem abas locais de Contratos ou Cobertura. A página MUST NOT exibir grade de escritórios pendentes nem histórico longo de credenciais no fluxo diário. Links secundários MAY apontar para deep-links de contratos e catálogo. TRIAL e PRODUCTION SHALL compartilhar o mesmo shell de card **Credenciais** (certificado, key, secret, versão ativa/versões em preparação); PRODUCTION MUST NOT renderizar um card paralelo de ativação com stepper de múltiplos passos. Em PRODUCTION, o aceite (consent) SHALL aparecer no card Credenciais e o submit SHALL usar o fluxo de onboarding produtivo existente.

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

#### Scenario: Produção exige aceite no mesmo card
- **WHEN** o ambiente é PRODUCTION e o onboarding produtivo está habilitado
- **THEN** o card Credenciais inclui o consentimento e o CTA de ativação
- **AND** liberações externas permanecem em seção própria após as credenciais
